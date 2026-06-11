# Notification Service Demo

REST API сервис для массовой рассылки уведомлений по каналам SMS и Email.

## Описание

Сервис принимает запросы на отправку уведомлений через HTTP API и асинхронно доставляет их подписчикам через очереди. Поддерживает до 10 000 получателей в одном запросе.

**Ключевые возможности:**

- Два канала доставки: SMS и Email
- Приоритетные очереди: транзакционные (высокий) и маркетинговые (низкий)
- Идемпотентность — повторные запросы с одним `Idempotency-Key` не создают дублей
- Повторные попытки с экспоненциальной задержкой при ошибках провайдера
- Жизненный цикл уведомления: `Queued → Sent → Delivered / Discarded`
- История уведомлений по подписчику (пагинация, 15 записей на страницу)

**Стек:** PHP 8.3+, Laravel 13, PostgreSQL 15, Redis 7, RabbitMQ 3.12, Nginx, Docker

## API

| Метод | URL | Описание |
|-------|-----|----------|
| `POST` | `/api/v1/notifications` | Отправить уведомления |
| `GET` | `/api/v1/subscribers/{id}/notifications` | История уведомлений подписчика |

**Пример запроса на отправку:**

```http
POST /api/v1/notifications
Content-Type: application/json
Idempotency-Key: unique-request-id-123

{
  "channel": "sms",
  "message": "Ваш заказ отправлен",
  "recipient_ids": [1, 2, 3],
  "priority": "high"
}
```

**Коды ответа:**

- `202 Created` — уведомления поставлены в очередь
- `200 OK` — повторный запрос с тем же `Idempotency-Key` (возвращает кешированный ответ)
- `412 Precondition Failed` — конфликт идемпотентности (тот же ключ, другой payload)
- `422 Unprocessable Entity` — ошибка валидации

## Развёртывание

### Требования

- Docker и Docker Compose

### Запуск

```bash
git clone <repository-url>
cd notification-service-demo

cp .env.example .env

docker compose up -d --build
```

Миграции базы данных запускаются автоматически при старте контейнера `app`.

**После запуска доступны:**

- API: `http://localhost:8081`
- RabbitMQ Management UI: `http://localhost:15672` (guest / guest)

### Основные переменные окружения

| Переменная | По умолчанию | Описание |
|------------|-------------|----------|
| `NGINX_PORT` | `8081` | Порт сервиса |
| `SMS_PROVIDER` | `mock` | Провайдер SMS (`mock` или реальный) |
| `EMAIL_PROVIDER` | `mock` | Провайдер Email (`mock` или реальный) |
| `NOTIFICATION_RETRY_MAX_ATTEMPTS` | `5` | Максимум попыток при ошибке |
| `NOTIFICATION_RETRY_BASE_DELAY` | `5` | Базовая задержка повтора (секунды) |
| `NOTIFICATION_IDEMPOTENCY_TTL` | `86400` | Время жизни ключа идемпотентности (секунды) |

### Воркеры очередей

Два воркера запускаются автоматически через `docker-compose`:

- `queue-transactional` — очередь `notifications.transactional` (высокий приоритет)
- `queue-marketing` — очередь `notifications.marketing` (низкий приоритет)

## Запуск тестов

```bash
# Установить зависимости (при первом запуске)
composer install

# Скопировать .env (если не сделано)
cp .env.example .env

# Запустить все тесты
composer test

# Или напрямую через artisan
php artisan test
```

Тестовое окружение использует SQLite in-memory и синхронные очереди (`QUEUE_CONNECTION=sync`). Все провайдеры в тестах работают в режиме `mock`.

**Тестовые наборы:**

- `Unit` — тесты идемпотентности и изолированной логики
- `Feature` — тесты API: отправка, валидация, идемпотентность, маршрутизация по приоритету, история уведомлений
