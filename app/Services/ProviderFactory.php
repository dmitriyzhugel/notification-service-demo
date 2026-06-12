<?php

namespace App\Services;

use App\Contracts\NotificationProviderInterface;
use App\Enums\Channel;
use App\Services\Providers\EmailProvider;
use App\Services\Providers\MockEmailProvider;
use App\Services\Providers\MockSmsProvider;
use App\Services\Providers\SmsProvider;

class ProviderFactory
{
    public function make(Channel $channel): NotificationProviderInterface
    {
        $driver = config('notifications.providers.' . $channel->value);

        return match ($channel) {
            Channel::SMS   => $driver === 'mock' ? new MockSmsProvider() : new SmsProvider(),
            Channel::Email => $driver === 'mock' ? new MockEmailProvider() : new EmailProvider(),
        };
    }
}
