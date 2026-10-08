<?php

use App\Enums\DriverStatus;
use App\Exceptions\InvalidDriverStatusTransitionException;
use App\Models\Driver;
use App\Models\DriverStatusHistory;
use App\Models\User;
use App\Services\DriverStatusService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->driver = Driver::factory()->create([
        'status' => DriverStatus::NEW,
    ]);
});

test('canTransition correctly identifies allowed and forbidden transitions', function () {
    // Allowed from new
    expect(DriverStatusService::canTransition(DriverStatus::NEW, DriverStatus::INVITED))->toBeTrue();
    expect(DriverStatusService::canTransition(DriverStatus::NEW, DriverStatus::SUSPENDED))->toBeTrue();
    expect(DriverStatusService::canTransition(DriverStatus::NEW, DriverStatus::BLOCKED))->toBeTrue();
    expect(DriverStatusService::canTransition(DriverStatus::NEW, DriverStatus::OPTED_OUT))->toBeTrue();

    // Forbidden from new
    expect(DriverStatusService::canTransition(DriverStatus::NEW, DriverStatus::ACTIVE))->toBeFalse();
    expect(DriverStatusService::canTransition(DriverStatus::NEW, DriverStatus::DECLINED))->toBeFalse();
    expect(DriverStatusService::canTransition(DriverStatus::NEW, DriverStatus::NEW))->toBeFalse();

    // From terminal state opted_out
    expect(DriverStatusService::canTransition(DriverStatus::OPTED_OUT, DriverStatus::NEW))->toBeFalse();
    expect(DriverStatusService::canTransition(DriverStatus::OPTED_OUT, DriverStatus::ACTIVE))->toBeFalse();

    // From blocked (admin manual reset only to suspended or new)
    expect(DriverStatusService::canTransition(DriverStatus::BLOCKED, DriverStatus::NEW))->toBeTrue();
    expect(DriverStatusService::canTransition(DriverStatus::BLOCKED, DriverStatus::SUSPENDED))->toBeTrue();
    expect(DriverStatusService::canTransition(DriverStatus::BLOCKED, DriverStatus::ACTIVE))->toBeFalse();
    expect(DriverStatusService::canTransition(DriverStatus::BLOCKED, DriverStatus::INVITED))->toBeFalse();
});

test('transition updates status, records history, and creates audit log', function () {
    $driver = DriverStatusService::transition(
        $this->driver,
        DriverStatus::INVITED,
        'تم إرسال رسالة الدعوة عبر واتساب',
        $this->user
    );

    expect($driver->status)->toBe(DriverStatus::INVITED);

    $this->assertDatabaseHas('drivers', [
        'id' => $driver->id,
        'status' => DriverStatus::INVITED->value,
    ]);

    $this->assertDatabaseHas('driver_status_histories', [
        'driver_id' => $driver->id,
        'from' => DriverStatus::NEW->value,
        'to' => DriverStatus::INVITED->value,
        'reason' => 'تم إرسال رسالة الدعوة عبر واتساب',
        'changed_by' => $this->user->id,
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'driver_status_change',
        'subject_type' => Driver::class,
        'subject_id' => $driver->id,
    ]);
});

test('transition to opted_out records opted_out_at timestamp', function () {
    expect($this->driver->opted_out_at)->toBeNull();

    $driver = DriverStatusService::transition(
        $this->driver,
        DriverStatus::OPTED_OUT,
        'طلب السائق إيقاف الرسائل'
    );

    expect($driver->status)->toBe(DriverStatus::OPTED_OUT);
    expect($driver->opted_out_at)->not->toBeNull();
});

test('forbidden transition throws InvalidDriverStatusTransitionException and does not alter driver', function () {
    expect(fn () => DriverStatusService::transition(
        $this->driver,
        DriverStatus::ACTIVE,
        'محاولة تفعيل غير قانونية'
    ))->toThrow(InvalidDriverStatusTransitionException::class);

    $this->driver->refresh();
    expect($this->driver->status)->toBe(DriverStatus::NEW);
    expect(DriverStatusHistory::where('driver_id', $this->driver->id)->count())->toBe(0);
});
