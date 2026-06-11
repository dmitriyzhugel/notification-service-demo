<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchNotificationTest extends TestCase
{
    public function test_dispatch_creates_batch_and_notifications(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/notifications', [
            'channel'       => 'sms',
            'message'       => 'Hello world',
            'recipient_ids' => ['user-1', 'user-2', 'user-3'],
            'priority'      => 'high',
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure(['data' => ['batch_id', 'status', 'total_recipients']]);

        $this->assertDatabaseCount('notification_batches', 1);
        $this->assertDatabaseHas('notification_batches', [
            'channel'          => 'sms',
            'priority'         => 'high',
            'total_recipients' => 3,
        ]);
        $this->assertDatabaseCount('notifications', 3);
    }

    public function test_sync_queue_transitions_notification_to_sent(): void
    {
        // QUEUE_CONNECTION=sync in phpunit.xml — job runs inline
        $response = $this->postJson('/api/v1/notifications', [
            'channel'       => 'sms',
            'message'       => 'Test OTP',
            'recipient_ids' => ['user-sync-1'],
            'priority'      => 'high',
        ]);

        $response->assertStatus(202);

        $this->assertDatabaseHas('notifications', [
            'status' => NotificationStatus::Sent->value,
        ]);
    }

    public function test_email_channel_transitions_to_sent_via_sync(): void
    {
        $response = $this->postJson('/api/v1/notifications', [
            'channel'       => 'email',
            'message'       => 'Welcome aboard',
            'recipient_ids' => ['user-email-1'],
            'priority'      => 'low',
        ]);

        $response->assertStatus(202);

        $this->assertDatabaseHas('notifications', [
            'channel' => 'email',
            'status'  => NotificationStatus::Sent->value,
        ]);
    }

    public function test_idempotency_key_prevents_duplicate_batch(): void
    {
        $payload = [
            'channel'       => 'sms',
            'message'       => 'Duplicate test',
            'recipient_ids' => ['user-idem-1'],
        ];

        $first = $this->postJson('/api/v1/notifications', $payload, [
            'Idempotency-Key' => 'unique-key-001',
        ]);
        $first->assertStatus(202);

        $second = $this->postJson('/api/v1/notifications', $payload, [
            'Idempotency-Key' => 'unique-key-001',
        ]);
        $second->assertStatus(200);

        $this->assertDatabaseCount('notification_batches', 1);
    }

    public function test_idempotency_conflict_returns_412(): void
    {
        $this->postJson('/api/v1/notifications', [
            'channel'       => 'sms',
            'message'       => 'Original message',
            'recipient_ids' => ['user-conflict-1'],
        ], ['Idempotency-Key' => 'conflict-key-001']);

        $response = $this->postJson('/api/v1/notifications', [
            'channel'       => 'sms',
            'message'       => 'Different message!',
            'recipient_ids' => ['user-conflict-1'],
        ], ['Idempotency-Key' => 'conflict-key-001']);

        $response->assertStatus(412);
    }

    public function test_marketing_priority_dispatches_to_correct_queue(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/notifications', [
            'channel'       => 'email',
            'message'       => 'Big sale!',
            'recipient_ids' => ['user-mkt-1'],
            'priority'      => 'low',
        ]);

        Queue::assertPushedOn('notifications.marketing', \App\Jobs\ProcessNotification::class);
    }

    public function test_transactional_priority_dispatches_to_correct_queue(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/notifications', [
            'channel'       => 'sms',
            'message'       => 'Your OTP is 111222',
            'recipient_ids' => ['user-txn-1'],
            'priority'      => 'high',
        ]);

        Queue::assertPushedOn('notifications.transactional', \App\Jobs\ProcessNotification::class);
    }

    public function test_dispatch_validates_unknown_channel(): void
    {
        $response = $this->postJson('/api/v1/notifications', [
            'channel'       => 'fax',
            'message'       => 'Hello',
            'recipient_ids' => ['user-1'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['channel']);
    }

    public function test_dispatch_validates_missing_recipient_ids(): void
    {
        $response = $this->postJson('/api/v1/notifications', [
            'channel' => 'sms',
            'message' => 'Hello',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recipient_ids']);
    }

    public function test_history_returns_paginated_notifications(): void
    {
        $subscriber = Subscriber::factory()->create(['external_id' => 'history-user-1']);
        $batch      = NotificationBatch::factory()->create();

        Notification::factory()->count(20)->create([
            'subscriber_id' => $subscriber->id,
            'batch_id'      => $batch->id,
        ]);

        $response = $this->getJson('/api/v1/subscribers/history-user-1/notifications');

        $response->assertStatus(200);
        $response->assertJsonCount(15, 'data');
        $response->assertJsonPath('meta.total', 20);
    }

    public function test_history_returns_404_for_unknown_subscriber(): void
    {
        $this->getJson('/api/v1/subscribers/does-not-exist/notifications')
            ->assertStatus(404);
    }
}
