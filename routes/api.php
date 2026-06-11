<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('notifications', [NotificationController::class, 'dispatch']);
    Route::get('subscribers/{subscriberId}/notifications', [NotificationController::class, 'history']);
});
