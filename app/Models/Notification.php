<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Enums\Priority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'subscriber_id',
        'channel',
        'message',
        'priority',
        'status',
        'attempts',
        'last_error',
        'sent_at',
        'delivered_at',
        'discarded_at',
    ];

    protected $casts = [
        'channel'      => Channel::class,
        'priority'     => Priority::class,
        'status'       => NotificationStatus::class,
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
        'discarded_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(NotificationBatch::class, 'batch_id');
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }
}
