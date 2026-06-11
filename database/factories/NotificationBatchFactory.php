<?php

namespace Database\Factories;

use App\Enums\BatchStatus;
use App\Enums\Channel;
use App\Enums\Priority;
use App\Models\NotificationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationBatchFactory extends Factory
{
    protected $model = NotificationBatch::class;

    public function definition(): array
    {
        return [
            'idempotency_key'  => null,
            'channel'          => fake()->randomElement(Channel::cases())->value,
            'message'          => fake()->sentence(),
            'priority'         => Priority::Low->value,
            'total_recipients' => fake()->numberBetween(1, 100),
            'status'           => BatchStatus::Processing->value,
        ];
    }
}
