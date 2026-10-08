<?php

use App\DTOs\WhatsApp\InboundMessage;
use App\Enums\DriverStatus;
use App\Enums\QuestionType;
use App\Enums\WhatsAppMessageDirection;
use App\Models\Driver;
use App\Models\DriverAnswer;
use App\Models\Question;
use App\Models\Setting;
use App\Models\User;
use App\Services\InboundMessageRouter;
use App\Services\QuestionnaireService;

beforeEach(function () {
    Question::query()->delete();

    $this->q1 = Question::create([
        'key' => 'vehicle_type',
        'sort_order' => 1,
        'type' => QuestionType::SINGLE_CHOICE,
        'is_active' => true,
        'is_required' => true,
    ]);
    $this->q1->recordVersion('ما هو نوع مركبتك؟', QuestionType::SINGLE_CHOICE, [
        ['value' => 'pickup', 'label_ar' => 'بيك أب', 'sort_order' => 1],
        ['value' => 'truck', 'label_ar' => 'شاحنة', 'sort_order' => 2],
    ]);

    $this->q2 = Question::create([
        'key' => 'experience',
        'sort_order' => 2,
        'type' => QuestionType::NUMBER,
        'is_active' => true,
        'is_required' => true,
    ]);
    $this->q2->recordVersion('سنوات الخبرة؟', QuestionType::NUMBER);

    $this->questionnaire = app(QuestionnaireService::class);
    $this->router = app(InboundMessageRouter::class);
    $this->user = User::factory()->create();
});

test('Reminders are sent once after configured reminder hours', function () {
    Setting::set('questionnaire_reminder_hours', 24);

    $driver = Driver::factory()->create([
        'status' => DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
        'questionnaire_last_sent_at' => now()->subHours(25),
        'questionnaire_reminder_sent_at' => null,
    ]);

    $result = $this->questionnaire->processRemindersAndTimeouts();

    expect($result['reminded'])->toBe(1);

    $driver->refresh();
    expect($driver->questionnaire_reminder_sent_at)->not->toBeNull();

    // Verify reminder message sent
    $this->assertDatabaseHas('whatsapp_messages', [
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT->value,
        'body' => 'تذكير: مرحباً بك يا كابتن! نود تذكيرك باستكمال الإجابة على الأسئلة لتفعيل حسابك والبدء في استلام الرحلات:',
    ]);

    // Running again does not send duplicate reminder
    $result2 = $this->questionnaire->processRemindersAndTimeouts();
    expect($result2['reminded'])->toBe(0);
});

test('Timeouts transition driver to NO_RESPONSE after configured timeout days', function () {
    Setting::set('questionnaire_timeout_days', 3);

    $driver = Driver::factory()->create([
        'status' => DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
        'questionnaire_last_sent_at' => now()->subDays(4),
    ]);

    $result = $this->questionnaire->processRemindersAndTimeouts();

    expect($result['timed_out'])->toBe(1);

    $driver->refresh();
    expect($driver->status)->toBe(DriverStatus::NO_RESPONSE);
});

test('Driver in NO_RESPONSE who replies later resumes questionnaire where they stopped', function () {
    $driver = Driver::factory()->create([
        'phone' => '+201088776655',
        'status' => DriverStatus::NO_RESPONSE,
    ]);

    // Driver already had answered Q1 previously
    $optPickup = $this->q1->activeOptions()->first();
    DriverAnswer::create([
        'driver_id' => $driver->id,
        'question_id' => $this->q1->id,
        'question_version_id' => $this->q1->current_version_id,
        'option_id' => $optPickup->id,
        'value_text' => $optPickup->label_ar,
        'is_current' => true,
        'answered_at' => now()->subDays(5),
    ]);

    // Driver sends message to resume
    $this->router->handle(new InboundMessage(
        from: '+201088776655',
        type: 'text',
        text: 'أنا عايز أكمل معاكم',
        payloadId: null,
        providerMessageId: 'in_resume_1',
        timestamp: now()->timestamp,
    ));

    $driver->refresh();
    expect($driver->status)->toBe(DriverStatus::QUESTIONNAIRE_IN_PROGRESS);

    // Assert welcome back message was sent
    $this->assertDatabaseHas('whatsapp_messages', [
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT->value,
        'body' => 'أهلاً بك مجدداً يا كابتن! يسعدنا تواصلك، سنستكمل استبيانك من حيث توقفت:',
    ]);

    // Next question sent must be Q2!
    $this->assertDatabaseHas('whatsapp_messages', [
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT->value,
        'body' => 'سنوات الخبرة؟',
    ]);
});

test('Admin can resend current question and request answer update for active driver', function () {
    $driver = Driver::factory()->create([
        'status' => DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
    ]);

    // 1. Resend current question
    $res1 = $this->actingAs($this->user)->postJson("/api/v1/drivers/{$driver->id}/resend-question");
    $res1->assertOk();

    // 2. Complete questionnaire for driver
    DriverAnswer::create([
        'driver_id' => $driver->id,
        'question_id' => $this->q1->id,
        'question_version_id' => $this->q1->current_version_id,
        'value_text' => 'بيك أب',
        'is_current' => true,
        'answered_at' => now(),
    ]);
    DriverAnswer::create([
        'driver_id' => $driver->id,
        'question_id' => $this->q2->id,
        'question_version_id' => $this->q2->current_version_id,
        'value_number' => 3,
        'is_current' => true,
        'answered_at' => now(),
    ]);
    $driver->update(['status' => DriverStatus::ACTIVE]);

    // 3. Request answer update when no new questions exist returns 400
    $res2 = $this->actingAs($this->user)->postJson("/api/v1/drivers/{$driver->id}/request-answer-update");
    $res2->assertStatus(400);

    // 4. Add a new question Q3
    $q3 = Question::create([
        'key' => 'new_city',
        'sort_order' => 3,
        'type' => QuestionType::TEXT,
        'is_active' => true,
    ]);
    $q3->recordVersion('المدينة المفضلة؟', QuestionType::TEXT);

    // 5. Request answer update now succeeds and transitions driver to questionnaire_in_progress
    $res3 = $this->actingAs($this->user)->postJson("/api/v1/drivers/{$driver->id}/request-answer-update");
    $res3->assertOk();

    $driver->refresh();
    expect($driver->status)->toBe(DriverStatus::QUESTIONNAIRE_IN_PROGRESS);
});
