<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

class QueueServiceProvider extends ServiceProvider
{
    private static bool $topologyDeclared = false;

    public function boot(): void
    {
        if ($this->app->runningUnitTests() || config('queue.default') !== 'rabbitmq') {
            return;
        }

        if (self::$topologyDeclared) {
            return;
        }

        $this->declareTopology();
        self::$topologyDeclared = true;
    }

    private function declareTopology(): void
    {
        try {
            $config = config('queue.connections.rabbitmq.hosts.0');

            $connection = new AMQPStreamConnection(
                $config['host'],
                $config['port'],
                $config['user'],
                $config['password'],
                $config['vhost'],
            );

            $channel = $connection->channel();

            // Объявляем обменники
            $channel->exchange_declare('notifications', 'direct', false, true, false);
            $channel->exchange_declare('notifications.dlx', 'fanout', false, true, false);

            $dlxArgs = new AMQPTable(['x-dead-letter-exchange' => 'notifications.dlx']);

            // Объявляем основные очереди с маршрутизацией через DLX
            $channel->queue_declare('notifications.transactional', false, true, false, false, false, $dlxArgs);
            $channel->queue_declare('notifications.marketing', false, true, false, false, false, $dlxArgs);

            // Объявляем очередь недоставленных сообщений
            $channel->queue_declare('notifications.dead', false, true, false, false);

            // Привязываем основные очереди к обменнику
            $channel->queue_bind('notifications.transactional', 'notifications', 'transactional');
            $channel->queue_bind('notifications.marketing', 'notifications', 'marketing');

            // Привязываем очередь недоставленных сообщений к DLX
            $channel->queue_bind('notifications.dead', 'notifications.dlx', '#');

            $channel->close();
            $connection->close();
        } catch (\Throwable $e) {
            // Логируем, но не прерываем запуск приложения, если RabbitMQ временно недоступен
            logger()->warning('QueueServiceProvider: failed to declare RabbitMQ topology', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
