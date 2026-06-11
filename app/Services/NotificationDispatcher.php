<?php

namespace App\Services;

use App\DTOs\DispatchRequest;
use App\Enums\BatchStatus;
use App\Enums\NotificationStatus;
use App\Jobs\ProcessNotification;
use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Models\Subscriber;
use Illuminate\Database\UniqueConstraintViolationException;
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

        if ($dto->idempotencyKey) {
            $cached = $this->idempotencyGuard->check($dto->idempotencyKey, $payloadHash);

            if ($cached !== null) {
                return NotificationBatch::findOrFail($cached['batch_id']);
            }
        }

        try {
            $batch = DB::transaction(function () use ($dto, $payloadHash): NotificationBatch {
                $recipientIds = array_values(array_unique($dto->recipientIds));

                $batch = NotificationBatch::create([
                    'idempotency_key'  => $dto->idempotencyKey,
                    'channel'          => $dto->channel,
                    'message'          => $dto->message,
                    'priority'         => $dto->priority,
                    'total_recipients' => count($recipientIds),
                    'status'           => BatchStatus::Processing,
                ]);

                foreach (array_chunk($recipientIds, 500) as $chunk) {
                    // Bulk insert to avoid N+1 queries and unique constraint races
                    Subscriber::insertOrIgnore(
                        array_map(fn ($id) => [
                            'external_id' => $id,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ], $chunk)
                    );

                    $subscriberMap = Subscriber::whereIn('external_id', $chunk)
                        ->pluck('id', 'external_id');

                    $rows = [];
                    foreach ($chunk as $externalId) {
                        $rows[] = [
                            'batch_id'      => $batch->id,
                            'subscriber_id' => $subscriberMap[$externalId],
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

                if ($dto->idempotencyKey) {
                    $this->idempotencyGuard->store(
                        $dto->idempotencyKey,
                        $payloadHash,
                        ['batch_id' => $batch->id],
                        $batch->id
                    );
                }

                return $batch;
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request with the same idempotency key committed first
            if ($dto->idempotencyKey) {
                $cached = $this->idempotencyGuard->check($dto->idempotencyKey, $payloadHash);
                if ($cached !== null) {
                    return NotificationBatch::findOrFail($cached['batch_id']);
                }
            }
            throw $e;
        }

        $queue = $dto->priority->queue();
        $batch->notifications()->select('id')->chunkById(100, function ($notifications) use ($queue): void {
            foreach ($notifications as $notification) {
                ProcessNotification::dispatch($notification->id)->onQueue($queue);
            }
        });

        return $batch;
    }
}
