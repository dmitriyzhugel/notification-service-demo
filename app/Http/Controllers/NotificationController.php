<?php

namespace App\Http\Controllers;

use App\DTOs\DispatchRequest;
use App\Enums\Priority;
use App\Exceptions\IdempotencyConflictException;
use App\Http\Requests\DispatchNotificationRequest;
use App\Http\Resources\BatchResource;
use App\Http\Resources\NotificationResource;
use App\Models\Subscriber;
use App\Services\IdempotencyGuard;
use App\Services\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly IdempotencyGuard       $idempotencyGuard,
    ) {
    }

    /**
     * @OA\Post(
     *   path="/api/v1/notifications",
     *   summary="Отправить массовую рассылку уведомлений",
     *   tags={"Notifications"},
     *   @OA\Parameter(
     *     name="Idempotency-Key",
     *     in="header",
     *     required=false,
     *     description="Опциональный уникальный ключ для предотвращения дублирования при повторных запросах",
     *     @OA\Schema(type="string", example="order-456-otp")
     *   ),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"channel","message","recipient_ids"},
     *       @OA\Property(property="channel", type="string", enum={"sms","email"}, example="sms"),
     *       @OA\Property(property="message", type="string", maxLength=2000, example="Your OTP code is 123456"),
     *       @OA\Property(
     *         property="recipient_ids",
     *         type="array",
     *         minItems=1,
     *         maxItems=10000,
     *         @OA\Items(type="string", example="user-001")
     *       ),
     *       @OA\Property(property="priority", type="string", enum={"high","low"}, default="low", example="high"),
     *     )
     *   ),
     *   @OA\Response(
     *     response=202,
     *     description="Пакет принят и поставлен в очередь",
     *     @OA\JsonContent(ref="#/components/schemas/BatchResource")
     *   ),
     *   @OA\Response(response=200, description="Идемпотентный повтор — возвращён тот же пакет"),
     *   @OA\Response(response=412, description="Конфликт ключа идемпотентности — тело запроса не совпадает"),
     *   @OA\Response(response=422, description="Ошибка валидации")
     * )
     */
    public function dispatch(DispatchNotificationRequest $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        try {
            $dto = new DispatchRequest(
                channel:        $request->enum('channel', \App\Enums\Channel::class),
                message:        $request->string('message')->toString(),
                recipientIds:   $request->input('recipient_ids'),
                priority:       $request->enum('priority', Priority::class) ?? Priority::Low,
                idempotencyKey: $idempotencyKey,
            );

            $batch   = $this->dispatcher->dispatch($dto);
            $isReplay = $idempotencyKey && $batch->wasRecentlyCreated === false;

            return (new BatchResource($batch))
                ->response()
                ->setStatusCode($isReplay ? 200 : 202);
        } catch (IdempotencyConflictException $e) {
            return response()->json(['message' => $e->getMessage()], 412);
        }
    }

    /**
     * @OA\Get(
     *   path="/api/v1/subscribers/{subscriberId}/notifications",
     *   summary="Получить историю уведомлений подписчика",
     *   tags={"Notifications"},
     *   @OA\Parameter(
     *     name="subscriberId",
     *     in="path",
     *     required=true,
     *     description="Внешний идентификатор подписчика",
     *     @OA\Schema(type="string", example="user-001")
     *   ),
     *   @OA\Parameter(
     *     name="page",
     *     in="query",
     *     required=false,
     *     @OA\Schema(type="integer", default=1)
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Постраничный список уведомлений",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/NotificationResource")),
     *       @OA\Property(property="links", type="object"),
     *       @OA\Property(property="meta", type="object")
     *     )
     *   ),
     *   @OA\Response(response=404, description="Подписчик не найден")
     * )
     */
    public function history(string $subscriberId): AnonymousResourceCollection
    {
        $subscriber = Subscriber::where('external_id', $subscriberId)->firstOrFail();

        $notifications = $subscriber->notifications()
            ->orderByDesc('created_at')
            ->paginate(15);

        return NotificationResource::collection($notifications);
    }
}
