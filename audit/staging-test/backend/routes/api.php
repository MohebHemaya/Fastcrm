<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AdminHealthController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DriverAnswerController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Api\WhatsappGroupController;
use App\Http\Controllers\Api\WorkOrderController;
use App\Http\Controllers\Api\WorkOrderFollowUpController;
use Illuminate\Support\Facades\Route;

// Public endpoints
Route::get('/health', HealthController::class);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
});

// Authenticated endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/password', [AuthController::class, 'updatePassword']);
    });

    Route::get('/activity-logs', [ActivityLogController::class, 'index']);

    // Imports
    Route::get('/imports/template', [ImportController::class, 'template']);
    Route::post('/imports', [ImportController::class, 'upload'])->middleware('throttle:import_upload');
    Route::get('/imports/{import}', [ImportController::class, 'show']);
    Route::get('/imports/{import}/rows', [ImportController::class, 'rows']);
    Route::post('/imports/{import}/commit', [ImportController::class, 'commit']);
    Route::post('/imports/{import}/cancel', [ImportController::class, 'cancel']);
    Route::get('/imports/{import}/export-invalid', [ImportController::class, 'exportInvalid']);

    // Drivers
    Route::get('/drivers', [DriverController::class, 'index']);
    Route::post('/drivers', [DriverController::class, 'store']);
    Route::get('/drivers/{driver}', [DriverController::class, 'show']);
    Route::patch('/drivers/{driver}', [DriverController::class, 'update']);
    Route::post('/drivers/{driver}/status', [DriverController::class, 'changeStatus']);

    // Driver Questionnaire Answers & Actions
    Route::get('/drivers/{driver}/answers', [DriverAnswerController::class, 'index']);
    Route::put('/drivers/{driver}/answers/{answer}', [DriverAnswerController::class, 'update']);
    Route::post('/drivers/{driver}/resend-question', [DriverAnswerController::class, 'resendQuestion']);
    Route::post('/drivers/{driver}/request-answer-update', [DriverAnswerController::class, 'requestAnswerUpdate']);

    // WhatsApp Groups
    Route::get('/whatsapp-groups', [WhatsappGroupController::class, 'index']);

    // WhatsApp & Messaging
    Route::post('/drivers/invite-estimate', [WhatsAppController::class, 'estimateInvites']);
    Route::post('/drivers/invite', [WhatsAppController::class, 'invite']);
    Route::get('/drivers/{driver}/messages', [WhatsAppController::class, 'driverMessages']);

    Route::get('/whatsapp/messages', [WhatsAppController::class, 'index']);
    Route::post('/whatsapp/messages/{message}/retry', [WhatsAppController::class, 'retry']);
    Route::get('/whatsapp/provider-status', [WhatsAppController::class, 'providerStatus']);

    // Questions Management
    Route::post('/questions/reorder', [QuestionController::class, 'reorder']);
    Route::patch('/questions/{question}/toggle-active', [QuestionController::class, 'toggleActive']);
    Route::apiResource('/questions', QuestionController::class);

    // General System Settings
    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update']);

    // Work Orders & Dispatching
    Route::post('/work-orders/match-preview', [WorkOrderController::class, 'matchPreview']);
    Route::get('/work-orders', [WorkOrderController::class, 'index']);
    Route::post('/work-orders', [WorkOrderController::class, 'store']);
    Route::get('/work-orders/{workOrder}', [WorkOrderController::class, 'show']);
    Route::put('/work-orders/{workOrder}', [WorkOrderController::class, 'update']);
    Route::post('/work-orders/{workOrder}/send', [WorkOrderController::class, 'send']);
    Route::post('/work-orders/{workOrder}/cancel', [WorkOrderController::class, 'cancel']);
    Route::post('/work-orders/{workOrder}/duplicate', [WorkOrderController::class, 'duplicate']);
    Route::get('/work-orders/{workOrder}/recipients', [WorkOrderController::class, 'recipients']);
    Route::post('/work-orders/{workOrder}/recipients/{recipient}/retry', [WorkOrderController::class, 'retryRecipient']);

    // Work Orders Follow-Up & Operations (Phase 6)
    Route::post('/work-orders/{workOrder}/remove-confirmed', [WorkOrderFollowUpController::class, 'removeConfirmed']);
    Route::post('/work-orders/{workOrder}/promote-backup', [WorkOrderFollowUpController::class, 'promoteBackup']);
    Route::post('/work-orders/{workOrder}/remove-backup', [WorkOrderFollowUpController::class, 'removeBackup']);
    Route::post('/work-orders/{workOrder}/add-driver', [WorkOrderFollowUpController::class, 'addDriver']);
    Route::post('/work-orders/{workOrder}/resend-offers', [WorkOrderFollowUpController::class, 'resendOffers']);
    Route::post('/work-orders/{workOrder}/status', [WorkOrderFollowUpController::class, 'changeStatus']);
    Route::post('/work-orders/{workOrder}/outcomes', [WorkOrderFollowUpController::class, 'recordOutcomes']);
    Route::get('/work-orders/{workOrder}/events', [WorkOrderFollowUpController::class, 'events']);

    // Dashboard & Reports (Phase 7)
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/group-performance', [DashboardController::class, 'groupPerformance']);
    Route::post('/dashboard/bust-cache', [DashboardController::class, 'bustCache']);

    // Exports & PDF (Phase 7)
    Route::get('/exports', [ExportController::class, 'index']);
    Route::post('/exports', [ExportController::class, 'store'])->middleware('throttle:exports');
    Route::get('/exports/{export}', [ExportController::class, 'show']);
    Route::get('/exports/{export}/download', [ExportController::class, 'download']);
    Route::post('/exports/{export}/retry', [ExportController::class, 'retry']);

    // Direct PDF Downloads (Phase 7)
    Route::get('/work-orders/{workOrder}/pdf', [ExportController::class, 'directOrderPdf']);
    Route::get('/drivers/{driver}/pdf', [ExportController::class, 'directDriverPdf']);

    // System Health & Monitoring (Phase 8)
    Route::get('/admin/health', [AdminHealthController::class, 'systemHealth']);
    Route::get('/admin/health/backups/latest', [AdminHealthController::class, 'downloadLatestBackup']);
    Route::post('/admin/health/failed-jobs/{id}/retry', [AdminHealthController::class, 'retryFailedJob']);
    Route::delete('/admin/health/failed-jobs/{id}', [AdminHealthController::class, 'deleteFailedJob']);
    Route::post('/admin/health/failed-jobs/retry-all', [AdminHealthController::class, 'retryAllFailedJobs']);
    Route::delete('/admin/health/failed-jobs/flush', [AdminHealthController::class, 'flushFailedJobs']);

    // Dev Simulation (Fake provider / local testing)
    Route::post('/whatsapp/simulate-inbound', [WhatsAppController::class, 'simulateInbound']);
    Route::post('/whatsapp/simulate-status', [WhatsAppController::class, 'simulateStatus']);
});
