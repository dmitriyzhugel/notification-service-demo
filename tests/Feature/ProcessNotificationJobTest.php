<?php

namespace Tests\Feature;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Enums\Priority;
use App\Jobs\ProcessNotification;
use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Models\Subscriber;
use App\Services\ProviderFactory;
use App\Services\Providers\FailingMockProvider;
use App\Services\Providers\MockEmailProvider;
use App\Services\Providers\MockSmsProvider;
use Tests\TestCase;

class ProcessNotificationJobTest extends TestCase
{
    private function makeNotification(array $overrides = []): Notification
    {
        $subscriber = Subscriber::factory()->create();
        $batch      = NotificationBatch::factory()->create();

        return Notification::factory()->create(array_merge([
            'subscriber_id' => $subscriber->id,
            'batch_id'      => $batch->id,
            'channel'       => Channel::SMS->value,
            'priority'      => Priority::High->value,
        ], $overrides));
    }

    private function factoryWithMock(Channel $channel, object $provider): ProviderFactory
    {
        $factory = $this->createMock(ProviderFactory::class);
        $factory->method('make')->willReturn($provider);

        return $factory;
    }

    public function test_job_marks_sms_notification_as_sent(): void
    {
        $notification = $this->makeNotification(['channel' => Channel::SMS->value]);
        $factory      = $this->factoryWithMock(Channel::SMS, new MockSmsProvider());

        (new ProcessNotification($notification->id))->handle($factory);

        $notification->refresh();
        $this->assertEquals(NotificationStatus::Sent, $notification->status);
        $this->assertNotNull($notification->sent_at);
        $this->assertEquals(1, $notification->attempts);
    }

    public function test_job_marks_email_notification_as_sent(): void
    {
        $notification = $this->makeNotification(['channel' => Channel::Email->value]);
        $factory      = $this->factoryWithMock(Channel::Email, new MockEmailProvider());

        (new ProcessNotification($notification->id))->handle($factory);

        $notification->refresh();
        $this->assertEquals(NotificationStatus::Sent, $notification->status);
        $this->assertNotNull($notification->sent_at);
    }

    public function test_job_increments_attempts_on_failure(): void
    {
        $notification = $this->makeNotification();
        $factory      = $this->factoryWithMock(Channel::SMS, new FailingMockProvider());

        try {
            (new ProcessNotification($notification->id))->handle($factory);
        } catch (\Throwable) {
            // Expected exception
        }

        $notification->refresh();
        $this->assertEquals(1, $notification->attempts);
        // Below max attempts (3), so still queued
        $this->assertEquals(NotificationStatus::Queued, $notification->status);
    }

    public function test_job_discards_after_max_attempts(): void
    {
        $notification = $this->makeNotification();
        $factory      = $this->factoryWithMock(Channel::SMS, new FailingMockProvider());
        $maxAttempts  = config('notifications.retry_max_attempts');

        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                (new ProcessNotification($notification->id))->handle($factory);
            } catch (\Throwable) {
                // Expected
            }
        }

        $notification->refresh();
        $this->assertEquals(NotificationStatus::Discarded, $notification->status);
        $this->assertNotNull($notification->discarded_at);
        $this->assertNotNull($notification->last_error);
    }

    public function test_job_is_idempotent_on_already_sent_notification(): void
    {
        $notification = $this->makeNotification(['status' => NotificationStatus::Sent->value]);
        $provider     = $this->createMock(\App\Contracts\NotificationProviderInterface::class);
        $provider->expects($this->never())->method('send');

        $factory = $this->createMock(ProviderFactory::class);
        $factory->method('make')->willReturn($provider);

        (new ProcessNotification($notification->id))->handle($factory);

        $notification->refresh();
        $this->assertEquals(NotificationStatus::Sent, $notification->status);
    }

    public function test_job_is_idempotent_on_discarded_notification(): void
    {
        $notification = $this->makeNotification(['status' => NotificationStatus::Discarded->value]);
        $provider     = $this->createMock(\App\Contracts\NotificationProviderInterface::class);
        $provider->expects($this->never())->method('send');

        $factory = $this->createMock(ProviderFactory::class);
        $factory->method('make')->willReturn($provider);

        (new ProcessNotification($notification->id))->handle($factory);

        $notification->refresh();
        $this->assertEquals(NotificationStatus::Discarded, $notification->status);
    }
}
