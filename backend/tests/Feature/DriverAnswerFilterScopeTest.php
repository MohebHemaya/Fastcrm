<?php

use App\Enums\DriverStatus;
use App\Enums\QuestionType;
use App\Models\Driver;
use App\Models\DriverAnswer;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;

beforeEach(function () {
    // Set up a test single_choice question
    $this->question = Question::create([
        'key' => 'vehicle_type',
        'sort_order' => 1,
        'is_active' => true,
        'is_required' => true,
        'is_filter' => true,
        'type' => QuestionType::SINGLE_CHOICE,
    ]);

    $this->version = QuestionVersion::create([
        'question_id' => $this->question->id,
        'version' => 1,
        'text_ar' => 'ما هو نوع سيارتك؟',
        'type' => QuestionType::SINGLE_CHOICE,
        'options_snapshot' => [
            ['value' => 'pickup', 'label_ar' => 'ربع نقل'],
            ['value' => 'van', 'label_ar' => 'فان'],
            ['value' => 'jumbo', 'label_ar' => 'جامبو'],
        ],
    ]);

    $this->optPickup = QuestionOption::create([
        'question_id' => $this->question->id,
        'value' => 'pickup',
        'label_ar' => 'ربع نقل',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->optVan = QuestionOption::create([
        'question_id' => $this->question->id,
        'value' => 'van',
        'label_ar' => 'فان',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $this->optJumbo = QuestionOption::create([
        'question_id' => $this->question->id,
        'value' => 'jumbo',
        'label_ar' => 'جامبو',
        'sort_order' => 3,
        'is_active' => true,
    ]);
});

test('Driver without an answer and driver with skipped answer are excluded by work-order filter', function () {
    // Driver A: has answered pickup
    $driverA = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverAnswer::create([
        'driver_id' => $driverA->id,
        'question_id' => $this->question->id,
        'question_version_id' => $this->version->id,
        'option_id' => $this->optPickup->id,
        'is_current' => true,
        'is_skipped' => false,
        'answered_at' => now(),
    ]);

    // Driver B: no answer at all
    $driverB = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    // Driver C: skipped answer (needs review)
    $driverC = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'needs_review' => true]);
    DriverAnswer::create([
        'driver_id' => $driverC->id,
        'question_id' => $this->question->id,
        'question_version_id' => $this->version->id,
        'option_id' => null,
        'is_current' => true,
        'is_skipped' => true,
        'answered_at' => now(),
    ]);

    // Query filter matching "pickup"
    $matched = Driver::answeredWith('vehicle_type', '=', 'pickup')->pluck('id');

    expect($matched)->toContain($driverA->id)
        ->and($matched)->not->toContain($driverB->id)
        ->and($matched)->not->toContain($driverC->id);
});

test('Driver whose current answer was edited manually is matched by the new answer only', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    // Historical answer: pickup (archived)
    DriverAnswer::create([
        'driver_id' => $driver->id,
        'question_id' => $this->question->id,
        'question_version_id' => $this->version->id,
        'option_id' => $this->optPickup->id,
        'is_current' => false,
        'is_skipped' => false,
        'answered_at' => now()->subDay(),
    ]);

    // New active answer: van (current)
    DriverAnswer::create([
        'driver_id' => $driver->id,
        'question_id' => $this->question->id,
        'question_version_id' => $this->version->id,
        'option_id' => $this->optVan->id,
        'is_current' => true,
        'is_skipped' => false,
        'answered_at' => now(),
    ]);

    // Old answer filter must NOT match
    $matchedPickup = Driver::answeredWith('vehicle_type', '=', 'pickup')->pluck('id');
    expect($matchedPickup)->not->toContain($driver->id);

    // New answer filter MUST match
    $matchedVan = Driver::answeredWith('vehicle_type', '=', 'van')->pluck('id');
    expect($matchedVan)->toContain($driver->id);
});

test('Inactive or deactivated option does not break matching of historical answers', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    // Driver answered "jumbo" when it was active
    DriverAnswer::create([
        'driver_id' => $driver->id,
        'question_id' => $this->question->id,
        'question_version_id' => $this->version->id,
        'option_id' => $this->optJumbo->id,
        'is_current' => true,
        'is_skipped' => false,
        'answered_at' => now(),
    ]);

    // Later: Admin deactivates the "jumbo" option
    $this->optJumbo->update(['is_active' => false]);

    // Scope should STILL match historical answers by value or ID
    $matchedBySlug = Driver::answeredWith('vehicle_type', '=', 'jumbo')->pluck('id');
    expect($matchedBySlug)->toContain($driver->id);

    $matchedById = Driver::answeredWith($this->question, '=', $this->optJumbo->id)->pluck('id');
    expect($matchedById)->toContain($driver->id);
});

test('Numeric question scope matches correctly with comparison operators and excludes missing/skipped', function () {
    $numQuestion = Question::create([
        'key' => 'experience_years',
        'sort_order' => 2,
        'is_active' => true,
        'is_required' => true,
        'is_filter' => true,
        'type' => QuestionType::NUMBER,
    ]);

    $numVersion = QuestionVersion::create([
        'question_id' => $numQuestion->id,
        'version' => 1,
        'text_ar' => 'كم سنة خبرة في القيادة؟',
        'type' => QuestionType::NUMBER,
        'options_snapshot' => null,
    ]);

    $driver5 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverAnswer::create([
        'driver_id' => $driver5->id,
        'question_id' => $numQuestion->id,
        'question_version_id' => $numVersion->id,
        'value_number' => 5.0,
        'is_current' => true,
        'is_skipped' => false,
        'answered_at' => now(),
    ]);

    $driver2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverAnswer::create([
        'driver_id' => $driver2->id,
        'question_id' => $numQuestion->id,
        'question_version_id' => $numVersion->id,
        'value_number' => 2.0,
        'is_current' => true,
        'is_skipped' => false,
        'answered_at' => now(),
    ]);

    $driverSkipped = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'needs_review' => true]);
    DriverAnswer::create([
        'driver_id' => $driverSkipped->id,
        'question_id' => $numQuestion->id,
        'question_version_id' => $numVersion->id,
        'value_number' => null,
        'is_current' => true,
        'is_skipped' => true,
        'answered_at' => now(),
    ]);

    // >= 3 years
    $matched = Driver::answeredWith('experience_years', '>=', 3)->pluck('id');
    expect($matched)->toContain($driver5->id)
        ->and($matched)->not->toContain($driver2->id)
        ->and($matched)->not->toContain($driverSkipped->id);
});
