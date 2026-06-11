<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Exceptions\ProviderException;
use App\Models\Notification;
use App\Services\ProviderFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessNotification implements ShouldQueue
{
    use Queueable;

    public int $tries         = 0;
    public int $maxExceptions = 5;

    public function __construct(public readonly int $notificationId) {}

    public function handle(ProviderFactory $factory): void
    {
        $notification = Notification::with('subscriber')
            ->lockForUpdate()
            ->findOrFail($this->notificationId);

        // Защита идемпотентности: статус уже финальный, пропускаем (обработка повторных доставок)
        if (in_array($notification->status, [
            NotificationStatus::Sent,
            NotificationStatus::Delivered,
            NotificationStatus::Discarded,
        ])) {
            return;
        }

        $notification->attempts += 1;
        $notification->save();

        try {
            $factory->make($notification->channel)->send($notification);

            $notification->status  = NotificationStatus::Sent;
            $notification->sent_at = now();
            $notification->save();
        } catch (ProviderException $e) {
            $maxAttempts = config('notifications.retry_max_attempts');

            if ($notification->attempts >= $maxAttempts) {
                $notification->status       = NotificationStatus::Discarded;
                $notification->discarded_at = now();
                $notification->last_error   = $e->getMessage();
                $notification->save();

                $this->fail($e);

                return;
            }

            throw $e;
        }
    }

    public function backoff(): array
    {
        $base = config('notifications.retry_base_delay', 5);
        $max  = config('notifications.retry_max_attempts', 5);

        return array_map(
            static fn (int $attempt): int => ($base * (2 ** $attempt)) + random_int(0, 5),
            range(0, $max - 1)
        );
    }

    public function failed(Throwable $e): void
    {
        $notification = Notification::find($this->notificationId);

        if ($notification && $notification->status !== NotificationStatus::Discarded) {
            $notification->status       = NotificationStatus::Discarded;
            $notification->discarded_at = now();
            $notification->last_error   = $e->getMessage();
            $notification->save();
        }
    }
}
