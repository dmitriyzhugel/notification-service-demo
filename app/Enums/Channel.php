<?php

namespace App\Enums;

enum Channel: string
{
    case SMS   = 'sms';
    case Email = 'email';
}
