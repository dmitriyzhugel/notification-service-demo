<?php

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Enums\Priority;
use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'batch_id'      => NotificationBatch::factory(),
            'subscriber_id' => Subscriber::factory(),
            'channel'       => fake()->randomElement(Channel::cases())->value,
            'message'       => fake()->sentence(),
            'priority'      => Priority::Low->value,
            'status'        => NotificationStatus::Queued->value,
            'attempts'      => 0,
            'last_error'    => null,
            'sent_at'       => null,
            'delivered_at'  => null,
            'discarded_at'  => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(['status' => NotificationStatus::Sent->value, 'sent_at' => now()]);
    }

    public function discarded(): static
    {
        return $this->state([
            'status'       => NotificationStatus::Discarded->value,
            'discarded_at' => now(),
            'last_error'   => 'Provider unavailable',
        ]);
    }
}
