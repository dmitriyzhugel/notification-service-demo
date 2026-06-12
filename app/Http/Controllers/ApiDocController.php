<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *   title="API сервиса уведомлений",
 *   version="1.0.0",
 *   description="Распределённый сервис уведомлений, поддерживающий массовую рассылку SMS/Email с приоритетными очередями, идемпотентной доставкой и отслеживанием статуса по каждому подписчику.",
 *   @OA\Contact(email="dvzhugel@gmail.com")
 * )
 *
 * @OA\Server(
 *   url=L5_SWAGGER_CONST_HOST,
 *   description="Локальный сервер разработки"
 * )
 *
 * @OA\Tag(name="Notifications", description="Эндпоинты отправки уведомлений и получения статусов")
 */
class ApiDocController extends Controller
{
}
