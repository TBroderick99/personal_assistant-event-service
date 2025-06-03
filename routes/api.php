<?php

use App\Http\Controllers\EventController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Health check route (no authentication required for monitoring)
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy', 
        'service' => 'event-service',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
});

// Protected API routes with API key validation
Route::middleware('validate.api.key')->group(function () {
    // Events routes
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{id}', [EventController::class, 'show'])->name('events.show');
    Route::put('/events/{id}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{id}', [EventController::class, 'destroy'])->name('events.destroy');

    // Event participants routes
    Route::post('/events/{id}/participants', [EventController::class, 'inviteParticipant'])->name('events.participants.invite');
    Route::put('/events/{eventId}/participants/{userId}', [EventController::class, 'updateParticipant'])->name('events.participants.update');
    Route::delete('/events/{eventId}/participants/{userId}', [EventController::class, 'removeParticipant'])->name('events.participants.remove');
});