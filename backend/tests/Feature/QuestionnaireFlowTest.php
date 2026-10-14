<?php

use App\DTOs\WhatsApp\InboundMessage;
use App\Enums\DriverStatus;
use App\Enums\QuestionType;
use App\Enums\WhatsAppMessageDirection;
use App\Models\Driver;
use App\Models\DriverAnswer;
use App\Models\Question;
use App\Services\InboundMessageRouter;
use App\Services\QuestionnaireService;

beforeEach(function () {
    // Clear existing questions and seed 5 standard mixed questions
    Question::query()->delete();

    $this->q1 = Question::create([
        'key' => 'vehicle_type',
        'sort_order' => 1,
        'type' => QuestionType::SINGLE_CHOICE,
        'is_active' => true,
        'is_required' => true,
    ]);
    $this->q1->recordVersion('ما نوع سيارتك؟', QuestionType::SINGLE_CHOICE, [
        ['value' => 'pickup', 'label_ar' => 'ربع نقل', 'sort_order' => 1],
        ['value' => 'jumbo', 'label_ar' => 'جامبو', 'sort_order' => 2],
        ['value' => 'truck', 'label_ar' => 'تريلا', 'sort_order' => 3],
    ]);

    $this->q2 = Question::create([
        'key' => 'has_refrigeration',
        'sort_order' => 2,
        'type' => QuestionType::YES_NO,
        'is_active' => true,
        'is_required' => true,
    ]);
    $this->q2->recordVersion('هل السيارة مجهزة بصندوق تبريد؟', QuestionType::YES_NO);

    $this->q3 = Question::create([
        'key' => 'experience_years',
        'sort_order' => 3,
        'type' => QuestionType::NUMBER,
        'is_active' => true,
        'is_required' => true,
    ]);
    $this->q3->recordVersion('كم سنة خبرتك في النقل؟ (أرسل رقماً)', QuestionType::NUMBER);

    $this->q4 = Question::create([
        'key' => 'preferred_shift',
        'sort_order' => 4,
        'type' => QuestionType::SINGLE_CHOICE,
        'is_active' => true,
        'is_required' => true,
    ]);
    $this->q4->recordVersion('ما الفترة المفضلة للعمل؟', QuestionType::SINGLE_CHOICE, [
        ['value' => 'morning', 'label_ar' => 'صباحي', 'sort_order' => 1],
        ['value' => 'evening', 'label_ar' => 'مسائي', 'sort_order' => 2],
        ['value' => 'any', 'label_ar' => 'في أي وقت', 'sort_order' => 3],
        ['value' => 'night', 'label_ar' => 'ليلي', 'sort_order' => 4],
    ]);

    $this->q5 = Question::create([
        'key' => 'work_notes',
        'sort_order' => 5,
        'type' => QuestionType::TEXT,
        'is_active' => true,
        'is_required' => false,
    ]);
    $this->q5->recordVersion('هل تود إضافة أي ملاحظات أو مناطق عمل مفضلة؟', QuestionType::TEXT);

    $this->router = app(InboundMessageRouter::class);
    $this->questionnaire = app(QuestionnaireService::class);
});

test('Full happy-path questionnaire for 5 mixed question types transitions driver to active', function () {
    $driver = Driver::factory()->create([
        'phone' => '+201099887766',
        'status' => DriverStatus::INVITED,
    ]);

    // 1. Driver replies YES to invite
    $this->router->handle(new InboundMessage(
        from: '+201099887766',
        type: 'button',
        text: 'نعم مهتم',
        payloadId: 'invite_yes',
        providerMessageId: 'in_inv_1',
        timestamp: now()->timestamp,
    ));

    $driver->refresh();
    expect($driver->status)->toBe(DriverStatus::QUESTIONNAIRE_IN_PROGRESS);

    // Q1 (single_choice: pickup, jumbo, truck) was sent.
    // 2. Driver answers Q1 by tapping 'pickup' button
    $optPickup = $this->q1->activeOptions()->where('value', 'pickup')->first();
    $this->router->handle(new InboundMessage(
        from: '+201099887766',
        type: 'button',
        text: 'ربع نقل',
        payloadId: "q_{$this->q1->id}_v_{$this->q1->current_version_id}_opt_{$optPickup->id}",
        providerMessageId: 'in_q1_ans',
        timestamp: now()->timestamp,
    ));

    expect(DriverAnswer::where('driver_id', $driver->id)->where('question_id', $this->q1->id)->exists())->toBeTrue();

    // 3. Driver answers Q2 (yes_no) with free text: 'ايوه' (ArabicReplyNormalizer)
    $this->router->handle(new InboundMessage(
        from: '+201099887766',
        type: 'text',
        text: 'ايوه مجهزة',
        payloadId: null,
        providerMessageId: 'in_q2_ans',
        timestamp: now()->timestamp,
    ));

    $q2Answer = DriverAnswer::where('driver_id', $driver->id)->where('question_id', $this->q2->id)->first();
    expect($q2Answer)->not->toBeNull()
        ->and($q2Answer->value_text)->toBe('نعم');

    // 4. Driver answers Q3 (number) with Arabic-Indic numerals: '٥'
    $this->router->handle(new InboundMessage(
        from: '+201099887766',
        type: 'text',
        text: '٥ سنوات',
        payloadId: null,
        providerMessageId: 'in_q3_ans',
        timestamp: now()->timestamp,
    ));

    $q3Answer = DriverAnswer::where('driver_id', $driver->id)->where('question_id', $this->q3->id)->first();
    expect($q3Answer)->not->toBeNull()
        ->and((float) $q3Answer->value_number)->toBe(5.0);

    // 5. Driver answers Q4 (single_choice list > 3 options) by matching option label 'صباحي'
    $this->router->handle(new InboundMessage(
        from: '+201099887766',
        type: 'text',
        text: 'بفضل اشتغل صباحي',
        payloadId: null,
        providerMessageId: 'in_q4_ans',
        timestamp: now()->timestamp,
    ));

    $q4Answer = DriverAnswer::where('driver_id', $driver->id)->where('question_id', $this->q4->id)->first();
    expect($q4Answer)->not->toBeNull()
        ->and($q4Answer->option->value)->toBe('morning');

    // 6. Driver answers Q5 (text) with free text
    $this->router->handle(new InboundMessage(
        from: '+201099887766',
        type: 'text',
        text: 'أفضل العمل في القاهرة والجيزة',
        payloadId: null,
        providerMessageId: 'in_q5_ans',
        timestamp: now()->timestamp,
    ));

    $q5Answer = DriverAnswer::where('driver_id', $driver->id)->where('question_id', $this->q5->id)->first();
    expect($q5Answer)->not->toBeNull()
        ->and($q5Answer->value_text)->toBe('أفضل العمل في القاهرة والجيزة');

    // Definition of Done Check: Driver is now ACTIVE!
    $driver->refresh();
    expect($driver->status)->toBe(DriverStatus::ACTIVE);

    // Assert thank you message was dispatched
    $this->assertDatabaseHas('whatsapp_messages', [
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT->value,
        'body' => 'شكراً جزيلاً لك يا كابتن! تم استكمال بياناتك بنجاح وأصبحت الآن شغال معانا في شبكة السائقين. سنرسل لك تفاصيل الرحلات أولاً بأول.',
    ]);
});

test('Invalid reply increments retries and max 2 retries marks question skipped and flags driver for review', function () {
    $driver = Driver::factory()->create([
        'phone' => '+201011112222',
        'status' => DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
    ]);

    // Current question is Q1 (single_choice)
    expect($driver->questionnaire_retries)->toBe(0);

    // Attempt 1: gibberish
    $this->router->handle(new InboundMessage(
        from: '+201011112222',
        type: 'text',
        text: 'كلام مش مفهوم خالص',
        payloadId: null,
        providerMessageId: 'in_retry_1',
        timestamp: now()->timestamp,
    ));

    $driver->refresh();
    expect($driver->questionnaire_retries)->toBe(1);

    // Attempt 2: second invalid reply -> exceeds max 2 retries
    $this->router->handle(new InboundMessage(
        from: '+201011112222',
        type: 'text',
        text: 'لسه كلام غير صالح',
        payloadId: null,
        providerMessageId: 'in_retry_2',
        timestamp: now()->timestamp,
    ));

    $driver->refresh();
    expect($driver->needs_review)->toBeTrue()
        ->and($driver->questionnaire_retries)->toBe(0);

    // Verify Q1 was saved as skipped
    $skippedAnswer = DriverAnswer::where('driver_id', $driver->id)
        ->where('question_id', $this->q1->id)
        ->first();

    expect($skippedAnswer)->not->toBeNull()
        ->and($skippedAnswer->is_skipped)->toBeTrue();

    // Verify system advanced to Q2
    $next = $this->questionnaire->getNextUnansweredQuestion($driver);
    expect($next->id)->toBe($this->q2->id);
});

test('Late or duplicate button taps for already answered question are ignored safely', function () {
    $driver = Driver::factory()->create([
        'phone' => '+201044445555',
        'status' => DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
    ]);

    // Answer Q1
    $optPickup = $this->q1->activeOptions()->first();
    $this->questionnaire->saveAnswer(
        driver: $driver,
        question: $this->q1,
        version: $this->q1->currentVersion,
        valueText: $optPickup->label_ar,
        option: $optPickup
    );

    // Driver accidentally taps Q1 button again while now on Q2
    $this->router->handle(new InboundMessage(
        from: '+201044445555',
        type: 'button',
        text: 'ربع نقل',
        payloadId: "q_{$this->q1->id}_v_{$this->q1->current_version_id}_opt_{$optPickup->id}",
        providerMessageId: 'in_duplicate_tap',
        timestamp: now()->timestamp,
    ));

    // Must not create answer for Q2 or corrupt Q1
    expect(DriverAnswer::where('driver_id', $driver->id)->where('question_id', $this->q2->id)->exists())->toBeFalse();
    expect(DriverAnswer::where('driver_id', $driver->id)->where('question_id', $this->q1->id)->count())->toBe(1);
});

test('Audio or image replies send polite guidance asking to reply with buttons or text', function () {
    $driver = Driver::factory()->create([
        'phone' => '+201066667777',
        'status' => DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
    ]);

    $this->router->handle(new InboundMessage(
        from: '+201066667777',
        type: 'audio',
        text: null,
        payloadId: null,
        providerMessageId: 'in_audio_msg',
        timestamp: now()->timestamp,
    ));

    $this->assertDatabaseHas('whatsapp_messages', [
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT->value,
        'body' => 'عفواً، لا يمكننا قبول الرسائل الصوتية أو الصور في هذه المرحلة. برجاء الإجابة على السؤال بالأزرار أو كتابة نص:',
    ]);
});

test('Answering driver_name question automatically updates driver name in drivers table', function () {
    $qName = Question::create([
        'key' => 'driver_name',
        'sort_order' => 0,
        'type' => QuestionType::TEXT,
        'is_active' => true,
        'is_required' => true,
    ]);
    $qName->recordVersion('ما هو اسمك بالكامل يا كابتن؟', QuestionType::TEXT);

    $driver = Driver::factory()->create([
        'phone' => '+201088776655',
        'name' => null,
        'status' => DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
    ]);

    $this->router->handle(new InboundMessage(
        from: '+201088776655',
        type: 'text',
        text: 'محمود عبد الرحيم',
        payloadId: null,
        providerMessageId: 'in_name_ans',
        timestamp: now()->timestamp,
    ));

    $driver->refresh();
    expect($driver->name)->toBe('محمود عبد الرحيم');

    $answer = DriverAnswer::where('driver_id', $driver->id)->where('question_id', $qName->id)->first();
    expect($answer)->not->toBeNull()
        ->and($answer->value_text)->toBe('محمود عبد الرحيم');
});

