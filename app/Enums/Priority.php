<?php

namespace App\Enums;

enum Priority: string
{
    case High = 'high';
    case Low  = 'low';

    public function queue(): string
    {
        return config('notifications.queues.' . $this->value, match ($this) {
            Priority::High => 'notifications.transactional',
            Priority::Low  => 'notifications.marketing',
        });
    }
}
