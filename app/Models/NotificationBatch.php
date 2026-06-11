<?php

namespace App\Models;

use App\Enums\BatchStatus;
use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Enums\Priority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'idempotency_key',
        'channel',
        'message',
        'priority',
        'total_recipients',
        'status',
    ];

    protected $casts = [
        'channel'  => Channel::class,
        'priority' => Priority::class,
        'status'   => BatchStatus::class,
    ];

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'batch_id');
    }

    public function isComplete(): bool
    {
        return ! $this->notifications()
            ->whereIn('status', [
                NotificationStatus::Queued->value,
                NotificationStatus::Sent->value,
            ])
            ->exists();
    }
}
