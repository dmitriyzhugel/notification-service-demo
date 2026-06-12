<?php

namespace App\DTOs;

use App\Enums\Channel;
use App\Enums\Priority;

readonly class DispatchRequest
{
    public function __construct(
        public Channel  $channel,
        public string   $message,
        public array    $recipientIds,
        public Priority $priority,
        public ?string  $idempotencyKey,
    ) {
    }
}
