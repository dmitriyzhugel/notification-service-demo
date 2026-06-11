<?php

namespace App\Contracts;

use App\DTOs\SendResult;
use App\Models\Notification;

interface NotificationProviderInterface
{
    public function send(Notification $notification): SendResult;
}
