<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *   schema="NotificationResource",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="batch_id", type="integer", example=10),
 *   @OA\Property(property="channel", type="string", enum={"sms","email"}, example="sms"),
 *   @OA\Property(property="message", type="string", example="Your OTP is 123456"),
 *   @OA\Property(property="priority", type="string", enum={"high","low"}, example="high"),
 *   @OA\Property(property="status", type="string", enum={"queued","sent","delivered","discarded"}, example="sent"),
 *   @OA\Property(property="attempts", type="integer", example=1),
 *   @OA\Property(property="last_error", type="string", nullable=true, example=null),
 *   @OA\Property(property="created_at", type="string", format="date-time"),
 *   @OA\Property(property="sent_at", type="string", format="date-time", nullable=true),
 *   @OA\Property(property="delivered_at", type="string", format="date-time", nullable=true),
 *   @OA\Property(property="discarded_at", type="string", format="date-time", nullable=true),
 * )
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'batch_id'     => $this->batch_id,
            'channel'      => $this->channel,
            'message'      => $this->message,
            'priority'     => $this->priority,
            'status'       => $this->status,
            'attempts'     => $this->attempts,
            'last_error'   => $this->last_error,
            'created_at'   => $this->created_at?->toIso8601String(),
            'sent_at'      => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'discarded_at' => $this->discarded_at?->toIso8601String(),
        ];
    }
}
