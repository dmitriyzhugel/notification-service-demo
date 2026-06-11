<?php

namespace App\Services;

use App\Exceptions\IdempotencyConflictException;
use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\Cache;

class IdempotencyGuard
{
    private const CACHE_PREFIX = 'idempotency:';

    public function check(string $key, string $payloadHash): ?array
    {
        $cached = Cache::get(self::CACHE_PREFIX . $key);

        if ($cached !== null) {
            if ($cached['hash'] !== $payloadHash) {
                throw new IdempotencyConflictException(
                    "Idempotency key '{$key}' was used with a different payload."
                );
            }

            return $cached['response'];
        }

        $record = IdempotencyKey::active()->where('key', $key)->first();

        if ($record !== null) {
            if ($record->payload_hash !== $payloadHash) {
                throw new IdempotencyConflictException(
                    "Idempotency key '{$key}' was used with a different payload."
                );
            }

            return $record->response;
        }

        return null;
    }

    public function store(string $key, string $payloadHash, array $response, int $batchId): void
    {
        $ttl       = config('notifications.idempotency_ttl');
        $expiresAt = now()->addSeconds($ttl);

        IdempotencyKey::create([
            'key'          => $key,
            'payload_hash' => $payloadHash,
            'response'     => $response,
            'batch_id'     => $batchId,
            'expires_at'   => $expiresAt,
            'created_at'   => now(),
        ]);

        Cache::put(
            self::CACHE_PREFIX . $key,
            ['hash' => $payloadHash, 'response' => $response, 'batch_id' => $batchId],
            $ttl
        );
    }

    public static function hashPayload(array $payload): string
    {
        $canonical = [
            'channel'       => $payload['channel'] ?? '',
            'message'       => $payload['message'] ?? '',
            'recipient_ids' => $payload['recipient_ids'] ?? [],
        ];

        sort($canonical['recipient_ids']);

        return hash('sha256', json_encode($canonical));
    }
}
