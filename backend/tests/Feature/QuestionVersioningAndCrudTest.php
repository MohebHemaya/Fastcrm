<?php

use App\Enums\QuestionType;
use App\Models\Driver;
use App\Models\DriverAnswer;
use App\Models\Question;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('Admin can list questions ordered by sort_order', function () {
    Question::query()->delete();

    $q2 = Question::create([
        'key' => 'q2',
        'sort_order' => 2,
        'type' => QuestionType::NUMBER,
        'is_active' => true,
    ]);
    $q2->recordVersion('السؤال الثاني', QuestionType::NUMBER);

    $q1 = Question::create([
        'key' => 'q1',
        'sort_order' => 1,
        'type' => QuestionType::TEXT,
        'is_active' => true,
    ]);
    $q1->recordVersion('السؤال الأول', QuestionType::TEXT);

    $response = $this->actingAs($this->user)->getJson('/api/v1/questions');

    $response->assertOk()
        ->assertJsonPath('data.0.key', 'q1')
        ->assertJsonPath('data.1.key', 'q2');
});

test('Validates options count and character limits on single_choice questions', function () {
    // 1. Less than 2 options
    $res1 = $this->actingAs($this->user)->postJson('/api/v1/questions', [
        'key' => 'test_choice',
        'type' => 'single_choice',
        'text_ar' => 'سؤال اختيار',
        'options' => [
            ['value' => 'opt1', 'label_ar' => 'خيار 1'],
        ],
    ]);
    $res1->assertStatus(422)->assertJsonValidationErrors(['options']);

    // 2. Buttons <= 3 with label > 20 characters
    $res2 = $this->actingAs($this->user)->postJson('/api/v1/questions', [
        'key' => 'test_choice_long_btn',
        'type' => 'single_choice',
        'text_ar' => 'سؤال اختيار',
        'options' => [
            ['value' => 'opt1', 'label_ar' => 'هذا خيار نص طويل جدا يتعدى عشرين حرف'],
            ['value' => 'opt2', 'label_ar' => 'خيار قصير'],
        ],
    ]);
    $res2->assertStatus(422)->assertJsonValidationErrors(['options']);

    // 3. List > 3 (e.g. 4) with label > 24 characters
    $res3 = $this->actingAs($this->user)->postJson('/api/v1/questions', [
        'key' => 'test_choice_long_list',
        'type' => 'single_choice',
        'text_ar' => 'سؤال اختيار قائمة',
        'options' => [
            ['value' => 'opt1', 'label_ar' => 'خيار عادي 1'],
            ['value' => 'opt2', 'label_ar' => 'خيار عادي 2'],
            ['value' => 'opt3', 'label_ar' => 'خيار عادي 3'],
            ['value' => 'opt4', 'label_ar' => 'هذا الخيار يتعدى اربعة وعشرين حرفا بكثير'],
        ],
    ]);
    $res3->assertStatus(422)->assertJsonValidationErrors(['options']);

    // 4. Valid single_choice question creates successfully
    $res4 = $this->actingAs($this->user)->postJson('/api/v1/questions', [
        'key' => 'test_valid_choice',
        'type' => 'single_choice',
        'text_ar' => 'ما نوع رخصتك؟',
        'is_filter' => true,
        'options' => [
            ['value' => 'private', 'label_ar' => 'خاصة'],
            ['value' => 'professional_3', 'label_ar' => 'درجة ثالثة'],
            ['value' => 'professional_2', 'label_ar' => 'درجة ثانية'],
        ],
    ]);
    $res4->assertStatus(201)
        ->assertJsonPath('data.key', 'test_valid_choice')
        ->assertJsonPath('data.options_count', 3);
});

test('Editing question text or options creates a new QuestionVersion and keeps old answers linked to old version', function () {
    $question = Question::create([
        'key' => 'experience_years',
        'sort_order' => 1,
        'type' => QuestionType::NUMBER,
        'is_active' => true,
    ]);
    $v1 = $question->recordVersion('كم عدد سنوات خبرتك؟', QuestionType::NUMBER);

    $driver = Driver::factory()->create();

    // Driver answers v1
    $answer = DriverAnswer::create([
        'driver_id' => $driver->id,
        'question_id' => $question->id,
        'question_version_id' => $v1->id,
        'value_number' => 5,
        'is_current' => true,
        'answered_at' => now(),
    ]);

    expect($question->versions()->count())->toBe(1);

    // Now admin edits question text via API
    $response = $this->actingAs($this->user)->putJson("/api/v1/questions/{$question->id}", [
        'key' => 'experience_years',
        'type' => 'number',
        'text_ar' => 'كم سنة خبرة لديك في قيادة الشاحنات؟ (نص معدل)',
        'is_required' => true,
        'is_filter' => true,
    ]);

    $response->assertOk();

    $question->refresh();
    expect($question->versions()->count())->toBe(2);

    $v2 = $question->currentVersion;
    expect($v2->id)->not->toBe($v1->id)
        ->and($v2->version)->toBe(2)
        ->and($v2->text_ar)->toBe('كم سنة خبرة لديك في قيادة الشاحنات؟ (نص معدل)');

    // Crucial check: old driver answer is STILL linked to v1!
    $answer->refresh();
    expect($answer->question_version_id)->toBe($v1->id)
        ->and($answer->version->text_ar)->toBe('كم عدد سنوات خبرتك؟');
});

test('Reordering questions updates sort orders', function () {
    $q1 = Question::create(['key' => 'reorder_q1', 'sort_order' => 1, 'type' => QuestionType::TEXT]);
    $q1->recordVersion('سؤال 1', QuestionType::TEXT);

    $q2 = Question::create(['key' => 'reorder_q2', 'sort_order' => 2, 'type' => QuestionType::TEXT]);
    $q2->recordVersion('سؤال 2', QuestionType::TEXT);

    $response = $this->actingAs($this->user)->postJson('/api/v1/questions/reorder', [
        'orders' => [
            ['id' => $q1->id, 'sort_order' => 10],
            ['id' => $q2->id, 'sort_order' => 5],
        ],
    ]);

    $response->assertOk();
    expect($q1->fresh()->sort_order)->toBe(10)
        ->and($q2->fresh()->sort_order)->toBe(5);
});

test('Toggle active flips is_active state', function () {
    $q = Question::create(['key' => 'toggle_q', 'sort_order' => 1, 'type' => QuestionType::TEXT, 'is_active' => true]);
    $q->recordVersion('سؤال تجريبي', QuestionType::TEXT);

    $res = $this->actingAs($this->user)->patchJson("/api/v1/questions/{$q->id}/toggle-active");
    $res->assertOk()->assertJsonPath('data.is_active', false);
    expect($q->fresh()->is_active)->toBeFalse();

    $res2 = $this->actingAs($this->user)->patchJson("/api/v1/questions/{$q->id}/toggle-active");
    $res2->assertOk()->assertJsonPath('data.is_active', true);
    expect($q->fresh()->is_active)->toBeTrue();
});
