<?php

namespace App\Services\Providers;

use App\Contracts\NotificationProviderInterface;
use App\DTOs\SendResult;
use App\Exceptions\ProviderException;
use App\Models\Notification;

class FailingMockProvider implements NotificationProviderInterface
{
    public function send(Notification $notification): SendResult
    {
        throw new ProviderException('Provider unavailable (test failure)');
    }
}
