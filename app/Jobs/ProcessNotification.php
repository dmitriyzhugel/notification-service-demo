<?php

namespace App\Jobs;

use App\Enums\BatchStatus;
use App\Enums\NotificationStatus;
use App\Exceptions\ProviderException;
use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Services\ProviderFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessNotification implements ShouldQueue
{
    use Queueable;

    public int $tries         = 0;
    public int $maxExceptions = 5;

    public function __construct(public readonly int $notificationId)
    {
    }

    public function handle(ProviderFactory $factory): void
    {
        // Инкрементируем попытки атомарно вне транзакции, чтобы счётчик сохранялся даже при повторном выбросе исключения для retry
        Notification::where('id', $this->notificationId)
            ->whereNotIn('status', [
                NotificationStatus::Sent->value,
                NotificationStatus::Delivered->value,
                NotificationStatus::Discarded->value,
            ])
            ->increment('attempts');

        DB::transaction(function () use ($factory): void {
            $notification = Notification::with('subscriber')
                ->lockForUpdate()
                ->findOrFail($this->notificationId);

            // Пропускаем, если параллельный воркер уже обработал это уведомление
            if (in_array($notification->status, [
                NotificationStatus::Sent,
                NotificationStatus::Delivered,
                NotificationStatus::Discarded,
            ])) {
                return;
            }

            try {
                $result = $factory->make($notification->channel)->send($notification);

                if (!$result->success) {
                    throw new ProviderException($result->errorMessage ?: 'Provider returned failure');
                }

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
        });

        $this->tryFinalizeBatch();
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

        $this->tryFinalizeBatch();
    }

    private function tryFinalizeBatch(): void
    {
        $batchId = Notification::where('id', $this->notificationId)->value('batch_id');
        if (!$batchId) {
            return;
        }

        $batch = NotificationBatch::find($batchId);
        if (!$batch || $batch->status !== BatchStatus::Processing) {
            return;
        }

        if ($batch->isComplete()) {
            $hasFailed = $batch->notifications()
                ->where('status', NotificationStatus::Discarded->value)
                ->exists();
            $batch->status = $hasFailed ? BatchStatus::Failed : BatchStatus::Completed;
            $batch->save();
        }
    }
}
