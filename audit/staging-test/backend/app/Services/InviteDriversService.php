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
        $estimatedMinutes = (int) ceil($count / max(1, $sendRate));

        return [
            'count' => $count,
            'send_rate_per_min' => $sendRate,
            'estimated_minutes' => $estimatedMinutes,
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

        $invitedCount = 0;

        foreach ($drivers as $driver) {
            // 1. Transition status from NEW to INVITED
            DriverStatusService::transition(
                $driver,
                DriverStatus::INVITED,
                'إرسال دعوة انضمام عبر واتساب',
                $user
            );

            // 2. Send invitation template
            $this->messageSender->sendTemplate(
                driver: $driver,
                templateName: $templateName,
                lang: 'ar',
                params: [$driver->name ?: 'كابتن'],
                buttons: $buttons,
            );

            $invitedCount++;
        }

        $sendRate = (int) config('whatsapp.send_rate_per_minute', 30);
        $estimatedMinutes = (int) ceil($invitedCount / max(1, $sendRate));

        return [
            'invited_count' => $invitedCount,
            'estimated_minutes' => $estimatedMinutes,
        ];
    }
}
