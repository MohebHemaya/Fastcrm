<?php

use App\Enums\DriverStatus;
use App\Enums\QuestionType;
use App\Models\Driver;
use App\Models\DriverAnswer;
use App\Models\Question;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    Question::query()->delete();

    $this->question = Question::create([
        'key' => 'vehicle_type',
        'sort_order' => 1,
        'type' => QuestionType::SINGLE_CHOICE,
        'is_active' => true,
    ]);
    $this->v1 = $this->question->recordVersion('نوع المركبة؟', QuestionType::SINGLE_CHOICE, [
        ['value' => 'pickup', 'label_ar' => 'بيك أب', 'sort_order' => 1],
        ['value' => 'truck', 'label_ar' => 'شاحنة', 'sort_order' => 2],
    ]);
});

test('Authenticated user can view driver answers and history', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    $opt = $this->question->activeOptions()->first();
    DriverAnswer::create([
        'driver_id' => $driver->id,
        'question_id' => $this->question->id,
        'question_version_id' => $this->v1->id,
        'option_id' => $opt->id,
        'value_text' => $opt->label_ar,
        'is_current' => true,
        'answered_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->getJson("/api/v1/drivers/{$driver->id}/answers");

    $response->assertOk()
        ->assertJsonPath('data.driver_id', $driver->id)
        ->assertJsonPath('data.answers.0.question_key', 'vehicle_type')
        ->assertJsonPath('data.answers.0.display_value', 'بيك أب');
});

test('Authenticated user can manually update a driver answer and it is audited', function () {
    $driver = Driver::factory()->create();

    $opt1 = $this->question->activeOptions()->where('value', 'pickup')->first();
    $opt2 = $this->question->activeOptions()->where('value', 'truck')->first();

    $answer = DriverAnswer::create([
        'driver_id' => $driver->id,
        'question_id' => $this->question->id,
        'question_version_id' => $this->v1->id,
        'option_id' => $opt1->id,
        'value_text' => $opt1->label_ar,
        'is_current' => true,
        'answered_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->putJson("/api/v1/drivers/{$driver->id}/answers/{$answer->id}", [
        'option_id' => $opt2->id,
        'value_text' => $opt2->label_ar,
    ]);

    $response->assertOk();

    $answer->refresh();
    expect($answer->option_id)->toBe($opt2->id)
        ->and($answer->value_text)->toBe('شاحنة');

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'update_driver_answer',
        'subject_id' => $driver->id,
    ]);
});
