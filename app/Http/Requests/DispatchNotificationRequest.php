<?php

namespace App\Http\Requests;

use App\Enums\Channel;
use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DispatchNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel'         => ['required', Rule::enum(Channel::class)],
            'message'         => ['required', 'string', 'max:2000'],
            'recipient_ids'   => ['required', 'array', 'min:1', 'max:10000'],
            'recipient_ids.*' => ['required', 'string', 'max:128'],
            'priority'        => ['sometimes', 'nullable', Rule::enum(Priority::class)],
        ];
    }
}
