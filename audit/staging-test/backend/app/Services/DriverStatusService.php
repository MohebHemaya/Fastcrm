<?php

namespace App\Services;

use App\Enums\DriverStatus;
use App\Exceptions\InvalidDriverStatusTransitionException;
use App\Models\Driver;
use App\Models\DriverStatusHistory;
use App\Models\User;

class DriverStatusService
{
    /**
     * Complete map of allowed transitions from each state.
     *
     * @var array<string, list<DriverStatus>>
     */
    protected static array $transitions = [
        DriverStatus::NEW->value => [
            DriverStatus::INVITED,
            DriverStatus::SUSPENDED,
            DriverStatus::BLOCKED,
            DriverStatus::OPTED_OUT,
        ],
        DriverStatus::INVITED->value => [
            DriverStatus::INTERESTED,
            DriverStatus::DECLINED,
            DriverStatus::NO_RESPONSE,
            DriverStatus::SUSPENDED,
            DriverStatus::BLOCKED,
            DriverStatus::OPTED_OUT,
        ],
        DriverStatus::INTERESTED->value => [
            DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
            DriverStatus::DECLINED,
            DriverStatus::SUSPENDED,
            DriverStatus::BLOCKED,
            DriverStatus::OPTED_OUT,
        ],
        DriverStatus::QUESTIONNAIRE_IN_PROGRESS->value => [
            DriverStatus::ACTIVE,
            DriverStatus::DECLINED,
            DriverStatus::NO_RESPONSE,
            DriverStatus::SUSPENDED,
            DriverStatus::BLOCKED,
            DriverStatus::OPTED_OUT,
        ],
        DriverStatus::ACTIVE->value => [
            DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
            DriverStatus::SUSPENDED,
            DriverStatus::BLOCKED,
            DriverStatus::OPTED_OUT,
        ],
        DriverStatus::DECLINED->value => [
            DriverStatus::INTERESTED,
            DriverStatus::SUSPENDED,
            DriverStatus::BLOCKED,
            DriverStatus::OPTED_OUT,
        ],
        DriverStatus::NO_RESPONSE->value => [
            DriverStatus::INVITED,
            DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
            DriverStatus::INTERESTED,
            DriverStatus::SUSPENDED,
            DriverStatus::BLOCKED,
            DriverStatus::OPTED_OUT,
        ],
        DriverStatus::SUSPENDED->value => [
            DriverStatus::NEW,
            DriverStatus::INVITED,
            DriverStatus::INTERESTED,
            DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
            DriverStatus::ACTIVE,
            DriverStatus::BLOCKED,
            DriverStatus::OPTED_OUT,
        ],
        DriverStatus::BLOCKED->value => [
            DriverStatus::SUSPENDED,
            DriverStatus::NEW,
        ],
        DriverStatus::OPTED_OUT->value => [
            // Strictly terminal
        ],
    ];

    /**
     * Check if a transition between two statuses is allowed.
     */
    public static function canTransition(DriverStatus $from, DriverStatus $to): bool
    {
        if ($from === $to) {
            return false;
        }

        $allowed = self::$transitions[$from->value] ?? [];

        return in_array($to, $allowed, true);
    }

    /**
     * Get list of allowed target statuses from a given status.
     *
     * @return list<DriverStatus>
     */
    public static function getAllowedTransitions(DriverStatus $from): array
    {
        return self::$transitions[$from->value] ?? [];
    }

    /**
     * Transition a driver to a new status.
     *
     * @throws InvalidDriverStatusTransitionException
     */
    public static function transition(
        Driver $driver,
        DriverStatus $to,
        string $reason,
        ?User $user = null
    ): Driver {
        $from = $driver->status;

        if (! self::canTransition($from, $to)) {
            throw new InvalidDriverStatusTransitionException($from, $to);
        }

        $driver->status = $to;

        if ($to === DriverStatus::OPTED_OUT && $driver->opted_out_at === null) {
            $driver->opted_out_at = now();
        }

        $driver->save();

        DriverStatusHistory::create([
            'driver_id' => $driver->id,
            'from' => $from->value,
            'to' => $to->value,
            'reason' => $reason,
            'changed_by' => $user?->id,
            'created_at' => now(),
        ]);

        Audit::log('driver_status_change', $driver, [
            'from' => $from->value,
            'to' => $to->value,
            'reason' => $reason,
            'user_id' => $user?->id,
        ]);

        return $driver;
    }
}
