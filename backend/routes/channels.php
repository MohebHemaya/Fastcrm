<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channel Definitions
|--------------------------------------------------------------------------
| All channels are private — require Sanctum auth token.
*/

/**
 * Private channel per driver chat.
 * Accessible by any authenticated user who can view WhatsApp.
 */
Broadcast::channel('whatsapp.driver.{driverId}', function ($user, int $driverId) {
    // Any authenticated user can listen to driver chats
    return (bool) $user;
});

/**
 * Shared inbox channel — all conversations list updates.
 */
Broadcast::channel('whatsapp.inbox', function ($user) {
    return (bool) $user;
});

/**
 * Drivers channel — status badge and questionnaire progress.
 */
Broadcast::channel('drivers', function ($user) {
    return (bool) $user;
});

/**
 * Per-import-job progress channel.
 */
Broadcast::channel('import.{jobId}', function ($user, string $jobId) {
    return (bool) $user;
});

/**
 * Work orders channels for real-time dispatch and responses.
 */
Broadcast::channel('work-orders', function ($user) {
    return (bool) $user;
});

Broadcast::channel('work-order.{workOrderId}', function ($user, int $workOrderId) {
    return (bool) $user;
});
