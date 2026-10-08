<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

class Audit
{
    /**
     * Record an audit activity log entry.
     * Queued-safe, never throws an exception.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function log(string $action, mixed $subject = null, array $properties = []): ?ActivityLog
    {
        try {
            $userId = null;
            try {
                $userId = Auth::id();
            } catch (Throwable) {
                // If called outside auth context
            }

            $subjectType = null;
            $subjectId = null;

            if ($subject instanceof Model) {
                $subjectType = get_class($subject);
                $subjectId = $subject->getKey();
            } elseif (is_object($subject)) {
                $subjectType = get_class($subject);
            }

            $ip = null;
            $userAgent = null;

            try {
                $ip = Request::ip();
                $userAgent = Request::userAgent();
            } catch (Throwable) {
                // In CLI or queue worker contexts
            }

            return ActivityLog::create([
                'user_id' => $userId,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'properties' => ! empty($properties) ? $properties : null,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning("Audit logging failed for action '{$action}': ".$e->getMessage());

            return null;
        }
    }
}
