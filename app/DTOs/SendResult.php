<?php

namespace App\DTOs;

readonly class SendResult
{
    public function __construct(
        public bool   $success,
        public string $providerMessageId = '',
        public string $errorMessage      = '',
    ) {}
}
