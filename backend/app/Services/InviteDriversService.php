<?php

namespace App\Services;

use App\Enums\DriverStatus;
use App\Models\Driver;
use App\Models\User;

class InviteDriversService
{
    public function __construct(
        protected MessageSender $messageSender,
    ) {}

    /**
     * Preview count and estimated duration for invitation.
     *
     * @param  array<int>  $driverIds
     * @return array{count: int, send_rate_per_min: int, estimated_minutes: int}
     */
    public function estimate(array $driverIds = [], bool $allNew = false): array
    {
        $query = Driver::where('status', DriverStatus::NEW);

        if (! $allNew && ! empty($driverIds)) {
            $query->whereIn('id', $driverIds);
        }

        $count = $query->count();
        $sendRate = (int) config('whatsapp.send_rate_per_minute', 30);

        /** @var array{enabled?: bool, min_delay_seconds?: int, max_delay_seconds?: int, batch_size?: int, batch_pause_seconds?: int} $antiBan */
        $antiBan = (array) config('whatsapp.anti_ban', []);
        $antiBanEnabled = (bool) ($antiBan['enabled'] ?? true);
        $minDelay = (int) ($antiBan['min_delay_seconds'] ?? 2);
        $maxDelay = (int) ($antiBan['max_delay_seconds'] ?? 5);
        $batchSize = (int) ($antiBan['batch_size'] ?? 20);
        $batchPause = (int) ($antiBan['batch_pause_seconds'] ?? 15);

        $avgDelayPerMsg = ($minDelay + $maxDelay) / 2.0;
        $totalAntiBanSeconds = $count > 1 && $antiBanEnabled
            ? (int) (($count - 1) * $avgDelayPerMsg + (floor(($count - 1) / max(1, $batchSize)) * $batchPause))
            : 0;

        $baseRateSeconds = (int) ceil(($count / max(1, $sendRate)) * 60);
        $estimatedMinutes = (int) ceil(max($baseRateSeconds, $totalAntiBanSeconds) / 60);

        return [
            'count' => $count,
            'send_rate_per_min' => $sendRate,
            'estimated_minutes' => max(1, $estimatedMinutes),
            'anti_ban_enabled' => $antiBanEnabled,
            'anti_ban_min_delay' => $minDelay,
            'anti_ban_max_delay' => $maxDelay,
        ];
    }

    /**
     * Invite drivers with status NEW in staged batches.
     *
     * @param  array<int>  $driverIds
     * @return array{invited_count: int, estimated_minutes: int}
     */
    public function invite(array $driverIds = [], bool $allNew = false, ?User $user = null): array
    {
        $query = Driver::where('status', DriverStatus::NEW);

        if (! $allNew) {
            if (empty($driverIds)) {
                return ['invited_count' => 0, 'estimated_minutes' => 0];
            }
            $query->whereIn('id', $driverIds);
        }

        $drivers = $query->get();
        $count = $drivers->count();

        if ($count === 0) {
            return ['invited_count' => 0, 'estimated_minutes' => 0];
        }

        $templateName = config('whatsapp.invite_template_name', 'driver_invitation');
        $buttons = [
            ['id' => 'invite_yes', 'title' => 'نعم، مهتم'],
            ['id' => 'invite_no', 'title' => 'لا، شكرًا'],
        ];

        /** @var array{enabled?: bool, min_delay_seconds?: int, max_delay_seconds?: int, batch_size?: int, batch_pause_seconds?: int} $antiBan */
        $antiBan = (array) config('whatsapp.anti_ban', []);
        $antiBanEnabled = (bool) ($antiBan['enabled'] ?? true);
        $minDelay = (int) ($antiBan['min_delay_seconds'] ?? 2);
        $maxDelay = (int) ($antiBan['max_delay_seconds'] ?? 5);
        $batchSize = (int) ($antiBan['batch_size'] ?? 20);
        $batchPause = (int) ($antiBan['batch_pause_seconds'] ?? 15);

        $invitedCount = 0;
        $accumulatedDelay = 0;

        foreach ($drivers as $index => $driver) {
            // Anti-ban jitter calculation
            if ($index > 0 && $antiBanEnabled) {
                $accumulatedDelay += random_int($minDelay, $maxDelay);
                if ($index % $batchSize === 0) {
                    $accumulatedDelay += $batchPause;
                }
            }

            // 1. Transition status from NEW to INVITED
            DriverStatusService::transition(
                $driver,
                DriverStatus::INVITED,
                'إرسال دعوة انضمام عبر واتساب',
                $user
            );

            // 2. Send invitation template with anti-ban jitter
            $this->messageSender->sendTemplate(
                driver: $driver,
                templateName: $templateName,
                lang: 'ar',
                params: [$driver->name ?: 'كابتن'],
                buttons: $buttons,
                delaySeconds: $accumulatedDelay,
            );

            $invitedCount++;
        }

        $sendRate = (int) config('whatsapp.send_rate_per_minute', 30);
        $baseRateSeconds = (int) ceil(($invitedCount / max(1, $sendRate)) * 60);
        $estimatedMinutes = (int) ceil(max($baseRateSeconds, $accumulatedDelay) / 60);

        return [
            'invited_count' => $invitedCount,
            'estimated_minutes' => max(1, $estimatedMinutes),
        ];
    }
}
