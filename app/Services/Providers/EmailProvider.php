<?php

namespace App\Services\Providers;

use App\Contracts\NotificationProviderInterface;
use App\DTOs\SendResult;
use App\Exceptions\ProviderException;
use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EmailProvider implements NotificationProviderInterface
{
    public function send(Notification $notification): SendResult
    {
        $response = Http::timeout(10)->post(config('services.email_gateway.url'), [
            'to'      => $notification->subscriber->email,
            'message' => $notification->message,
        ]);

        if (! $response->successful()) {
            throw new ProviderException('Email gateway error: ' . $response->status());
        }

        return new SendResult(true, $response->json('message_id', Str::uuid()->toString()));
    }
}
