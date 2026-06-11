<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *   schema="BatchResource",
 *   @OA\Property(property="batch_id", type="integer", example=1),
 *   @OA\Property(property="channel", type="string", enum={"sms","email"}, example="sms"),
 *   @OA\Property(property="priority", type="string", enum={"high","low"}, example="high"),
 *   @OA\Property(property="status", type="string", enum={"pending","processing","completed","failed"}, example="processing"),
 *   @OA\Property(property="total_recipients", type="integer", example=100),
 *   @OA\Property(property="created_at", type="string", format="date-time"),
 * )
 */
class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'batch_id'         => $this->id,
            'channel'          => $this->channel,
            'priority'         => $this->priority,
            'status'           => $this->status,
            'total_recipients' => $this->total_recipients,
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
