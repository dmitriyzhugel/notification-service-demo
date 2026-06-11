<?php

namespace Tests\Unit;

use App\Exceptions\IdempotencyConflictException;
use App\Models\NotificationBatch;
use App\Services\IdempotencyGuard;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class IdempotencyGuardTest extends TestCase
{
    private IdempotencyGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new IdempotencyGuard();
    }

    public function test_returns_null_for_new_key(): void
    {
        $result = $this->guard->check('brand-new-key', hash('sha256', 'payload'));

        $this->assertNull($result);
    }

    public function test_returns_cached_response_after_store(): void
    {
        $batch = NotificationBatch::factory()->create();
        $key   = 'test-key-001';
        $hash  = hash('sha256', 'canonical-payload');

        $this->guard->store($key, $hash, ['batch_id' => $batch->id], $batch->id);

        $result = $this->guard->check($key, $hash);

        $this->assertNotNull($result);
        $this->assertEquals($batch->id, $result['batch_id']);
    }

    public function test_throws_conflict_for_mismatched_hash(): void
    {
        $this->expectException(IdempotencyConflictException::class);

        $batch        = NotificationBatch::factory()->create();
        $key          = 'conflict-key-001';
        $originalHash = hash('sha256', 'original-payload');
        $differentHash = hash('sha256', 'different-payload');

        $this->guard->store($key, $originalHash, ['batch_id' => $batch->id], $batch->id);

        $this->guard->check($key, $differentHash);
    }

    public function test_falls_back_to_db_when_cache_is_empty(): void
    {
        $batch = NotificationBatch::factory()->create();
        $key   = 'db-fallback-key';
        $hash  = hash('sha256', 'db-payload');

        $this->guard->store($key, $hash, ['batch_id' => $batch->id], $batch->id);

        // Manually flush cache to force DB fallback
        Cache::flush();

        $result = $this->guard->check($key, $hash);

        $this->assertNotNull($result);
        $this->assertEquals($batch->id, $result['batch_id']);
    }

    public function test_hash_payload_produces_consistent_hash(): void
    {
        $hash1 = IdempotencyGuard::hashPayload([
            'channel'       => 'sms',
            'message'       => 'Hello',
            'recipient_ids' => ['b', 'a'],
        ]);

        $hash2 = IdempotencyGuard::hashPayload([
            'channel'       => 'sms',
            'message'       => 'Hello',
            'recipient_ids' => ['a', 'b'],
        ]);

        $this->assertEquals($hash1, $hash2, 'Hash should be order-independent for recipient_ids');
    }
}
