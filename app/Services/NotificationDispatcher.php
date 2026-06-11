<?php

namespace App\Services;

use App\DTOs\DispatchRequest;
use App\Enums\BatchStatus;
use App\Enums\NotificationStatus;
use App\Jobs\ProcessNotification;
use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Models\Subscriber;
use Illuminate\Support\Facades\DB;

class NotificationDispatcher
{
    public function __construct(
        private readonly IdempotencyGuard $idempotencyGuard,
    ) {}

    public function dispatch(DispatchRequest $dto): NotificationBatch
    {
        $payloadHash = $dto->idempotencyKey
            ? IdempotencyGuard::hashPayload([
                'channel'       => $dto->channel->value,
                'message'       => $dto->message,
                'recipient_ids' => $dto->recipientIds,
            ])
            : null;

        if ($dto->idempotencyKey && $payloadHash) {
            $cached = $this->idempotencyGuard->check($dto->idempotencyKey, $payloadHash);

            if ($cached !== null) {
                return NotificationBatch::findOrFail($cached['batch_id']);
            }
        }

        $batch = DB::transaction(function () use ($dto): NotificationBatch {
            $batch = NotificationBatch::create([
                'idempotency_key' => $dto->idempotencyKey,
                'channel'         => $dto->channel,
                'message'         => $dto->message,
                'priority'        => $dto->priority,
                'total_recipients'=> count($dto->recipientIds),
                'status'          => BatchStatus::Processing,
            ]);

            foreach (array_chunk($dto->recipientIds, 500) as $chunk) {
                $rows = [];

                foreach ($chunk as $externalId) {
                    $subscriber = Subscriber::firstOrCreate(['external_id' => $externalId]);

                    $rows[] = [
                        'batch_id'      => $batch->id,
                        'subscriber_id' => $subscriber->id,
                        'channel'       => $dto->channel->value,
                        'message'       => $dto->message,
                        'priority'      => $dto->priority->value,
                        'status'        => NotificationStatus::Queued->value,
                        'attempts'      => 0,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                }

                Notification::insert($rows);
            }

            return $batch;
        });

        if ($dto->idempotencyKey && $payloadHash) {
            $this->idempotencyGuard->store(
                $dto->idempotencyKey,
                $payloadHash,
                ['batch_id' => $batch->id],
                $batch->id
            );
        }

        $batch->notifications()->each(function (Notification $notification) use ($dto): void {
            ProcessNotification::dispatch($notification->id)
                ->onQueue($dto->priority->queue());
        });

        return $batch;
    }
}
