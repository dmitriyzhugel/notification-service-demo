<?php

namespace App\Services\Providers;

use App\Contracts\NotificationProviderInterface;
use App\DTOs\SendResult;
use App\Models\Notification;
use Illuminate\Support\Str;

class MockEmailProvider implements NotificationProviderInterface
{
    public function send(Notification $notification): SendResult
    {
        return new SendResult(true, 'mock-email-' . Str::uuid()->toString());
    }
}
