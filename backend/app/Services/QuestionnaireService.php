<?php

namespace App\Services;

use App\DTOs\WhatsApp\InboundMessage;
use App\Enums\DriverStatus;
use App\Enums\QuestionType;
use App\Events\QuestionnaireProgressUpdated;
use App\Models\Driver;
use App\Models\DriverAnswer;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Setting;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuestionnaireService
{
    public function __construct(
        protected MessageSender $messageSender,
        protected ArabicReplyNormalizer $normalizer,
    ) {}

    /**
     * Start the questionnaire for a driver transitioning to INTERESTED.
     */
    public function startQuestionnaire(Driver $driver): void
    {
        if (DriverStatusService::canTransition($driver->status, DriverStatus::QUESTIONNAIRE_IN_PROGRESS)) {
            DriverStatusService::transition(
                $driver,
                DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
                'بدء الاستبيان بعد إبداء الاهتمام'
            );
        }

        $driver->update([
            'questionnaire_retries' => 0,
            'questionnaire_reminder_sent_at' => null,
        ]);

        $this->sendNextQuestionOrComplete($driver);
    }

    /**
     * Get the next unanswered active question for this driver.
     */
    public function getNextUnansweredQuestion(Driver $driver): ?Question
    {
        $answeredQuestionIds = DriverAnswer::where('driver_id', $driver->id)
            ->where('is_current', true)
            ->pluck('question_id')
            ->all();

        return Question::active()
            ->ordered()
            ->with(['currentVersion', 'activeOptions'])
            ->whereNotIn('id', $answeredQuestionIds)
            ->first();
    }

    /**
     * Send the next question to the driver, or complete the questionnaire if all questions are answered.
     */
    public function sendNextQuestionOrComplete(Driver $driver): ?WhatsAppMessage
    {
        $nextQuestion = $this->getNextUnansweredQuestion($driver);

        if (! $nextQuestion) {
            $this->completeQuestionnaire($driver);

            return null;
        }

        return $this->sendQuestion($driver, $nextQuestion);
    }

    /**
     * Send a specific question to the driver.
     */
    public function sendQuestion(Driver $driver, Question $question): WhatsAppMessage
    {
        $version = $question->currentVersion;
        if (! $version) {
            // Lazy-create version if missing
            $version = $question->recordVersion(
                $question->key,
                $question->type,
                $question->activeOptions->toArray()
            );
        }

        $text = $version->text_ar;
        $driver->update([
            'questionnaire_last_sent_at' => now(),
        ]);

        // Send based on question type
        if ($question->type === QuestionType::YES_NO) {
            $buttons = [
                ['id' => "q_{$question->id}_v_{$version->id}_opt_yes", 'title' => 'نعم'],
                ['id' => "q_{$question->id}_v_{$version->id}_opt_no", 'title' => 'لا'],
            ];

            return $this->messageSender->sendButtons($driver, $text, $buttons);
        }

        if ($question->type === QuestionType::SINGLE_CHOICE) {
            $activeOptions = $question->activeOptions;
            $count = $activeOptions->count();

            if ($count <= 3 && $count > 0) {
                $buttons = [];
                foreach ($activeOptions as $opt) {
                    $buttons[] = [
                        'id' => "q_{$question->id}_v_{$version->id}_opt_{$opt->id}",
                        'title' => mb_substr($opt->label_ar, 0, 20),
                    ];
                }

                return $this->messageSender->sendButtons($driver, $text, $buttons);
            }

            if ($count > 3) {
                $rows = [];
                foreach ($activeOptions as $opt) {
                    $rows[] = [
                        'id' => "q_{$question->id}_v_{$version->id}_opt_{$opt->id}",
                        'title' => mb_substr($opt->label_ar, 0, 24),
                    ];
                }

                return $this->messageSender->sendList($driver, $text, 'اختر إجابة', $rows);
            }
        }

        // NUMBER or TEXT or single_choice with no options -> plain text
        return $this->messageSender->sendText($driver, $text);
    }

    /**
     * Complete questionnaire: transition driver to ACTIVE and send thank-you message.
     */
    public function completeQuestionnaire(Driver $driver): void
    {
        if (DriverStatusService::canTransition($driver->status, DriverStatus::ACTIVE)) {
            DriverStatusService::transition(
                $driver,
                DriverStatus::ACTIVE,
                'اكتمال إجابة جميع أسئلة الاستبيان'
            );
        }

        $thankYouBody = 'شكراً جزيلاً لك يا كابتن! تم استكمال بياناتك بنجاح وأصبحت الآن شغال معانا في شبكة السائقين. سنرسل لك تفاصيل الرحلات أولاً بأول.';

        try {
            $this->messageSender->sendText($driver, $thankYouBody);
        } catch (\Throwable $e) {
            Log::warning("[QuestionnaireService] Could not send thank you message to driver {$driver->id}: {$e->getMessage()}");
        }
    }

    /**
     * Handle an inbound message from a driver currently in QUESTIONNAIRE_IN_PROGRESS.
     */
    public function handleInboundReply(Driver $driver, InboundMessage $inbound): void
    {
        $currentQuestion = $this->getNextUnansweredQuestion($driver);

        if (! $currentQuestion) {
            // Already finished all questions
            $this->completeQuestionnaire($driver);

            return;
        }

        $version = $currentQuestion->currentVersion;
        if (! $version) {
            return;
        }

        // Check if message is audio/image/other
        if (in_array($inbound->type, ['audio', 'image', 'other'], true)) {
            $this->messageSender->sendText(
                $driver,
                'عفواً، لا يمكننا قبول الرسائل الصوتية أو الصور في هذه المرحلة. برجاء الإجابة على السؤال بالأزرار أو كتابة نص:'
            );
            $this->sendQuestion($driver, $currentQuestion);

            return;
        }

        $payloadId = (string) ($inbound->payloadId ?? '');
        $rawText = (string) ($inbound->text ?? '');

        // 1. Check if it's an interactive button or list reply
        if ($payloadId !== '') {
            $this->handlePayloadReply($driver, $currentQuestion, $version, $payloadId, $rawText);

            return;
        }

        // 2. Free text / number reply
        $this->handleTextReply($driver, $currentQuestion, $version, $rawText);
    }

    /**
     * Handle button or list payload reply.
     */
    protected function handlePayloadReply(
        Driver $driver,
        Question $question,
        $version,
        string $payloadId,
        string $rawText
    ): void {
        // Safe check for payload format: q_{questionId}_v_{versionId}_opt_{optId}
        if (preg_match('/^q_(\d+)_v_(\d+)_opt_(.+)$/', $payloadId, $matches)) {
            $targetQuestionId = (int) $matches[1];
            $optKey = $matches[3];

            // If the payload corresponds to an older or different question, ignore safely
            if ($targetQuestionId !== $question->id) {
                Log::info("[QuestionnaireService] Ignoring reply to already answered or different question id {$targetQuestionId}");

                return;
            }

            if ($question->type === QuestionType::YES_NO) {
                $isYes = in_array(strtolower($optKey), ['yes', '1'], true) || $this->normalizer->isYes($rawText);
                $this->saveAnswer(
                    driver: $driver,
                    question: $question,
                    version: $version,
                    valueText: $isYes ? 'نعم' : 'لا',
                    option: null
                );
                $this->sendNextQuestionOrComplete($driver);

                return;
            }

            if ($question->type === QuestionType::SINGLE_CHOICE) {
                $option = QuestionOption::where('question_id', $question->id)
                    ->where(function ($q) use ($optKey) {
                        $q->where('id', $optKey)->orWhere('value', $optKey);
                    })
                    ->first();

                if ($option) {
                    $this->saveAnswer(
                        driver: $driver,
                        question: $question,
                        version: $version,
                        valueText: $option->label_ar,
                        option: $option
                    );
                    $this->sendNextQuestionOrComplete($driver);

                    return;
                }
            }
        }

        // Fallback: treat text as free text
        $this->handleTextReply($driver, $question, $version, $rawText ?: $payloadId);
    }

    /**
     * Handle free text reply based on question type.
     */
    protected function handleTextReply(
        Driver $driver,
        Question $question,
        $version,
        string $rawText
    ): void {
        $type = $question->type;

        // A. YES_NO question answered with text
        if ($type === QuestionType::YES_NO) {
            if ($this->normalizer->isYes($rawText)) {
                $this->saveAnswer($driver, $question, $version, 'نعم');
                $this->sendNextQuestionOrComplete($driver);

                return;
            }

            if ($this->normalizer->isNo($rawText)) {
                $this->saveAnswer($driver, $question, $version, 'لا');
                $this->sendNextQuestionOrComplete($driver);

                return;
            }

            $this->handleInvalidAnswer($driver, $question, 'برجاء الضغط على أحد الأزرار أو الرد بـ (نعم) أو (لا):');

            return;
        }

        // B. SINGLE_CHOICE question answered with text
        if ($type === QuestionType::SINGLE_CHOICE) {
            $matchedOption = $this->normalizer->matchOption($rawText, $question->activeOptions);

            if ($matchedOption) {
                $this->saveAnswer(
                    driver: $driver,
                    question: $question,
                    version: $version,
                    valueText: $matchedOption->label_ar,
                    option: $matchedOption
                );
                $this->sendNextQuestionOrComplete($driver);

                return;
            }

            $this->handleInvalidAnswer($driver, $question, 'عفواً، لم نتمكن من مطابقة إجابتك مع الخيارات المتاحة. برجاء الاختيار من القائمة:');

            return;
        }

        // C. NUMBER question
        if ($type === QuestionType::NUMBER) {
            $number = $this->normalizer->extractNumber($rawText);

            if ($number !== null) {
                $this->saveAnswer(
                    driver: $driver,
                    question: $question,
                    version: $version,
                    valueText: (string) $number,
                    valueNumber: $number
                );
                $this->sendNextQuestionOrComplete($driver);

                return;
            }

            $this->handleInvalidAnswer($driver, $question, 'عفواً، برجاء إدخال رقم صحيح (مثال: 5):');

            return;
        }

        // D. TEXT question (free text)
        if (trim($rawText) !== '') {
            $this->saveAnswer(
                driver: $driver,
                question: $question,
                version: $version,
                valueText: trim($rawText)
            );
            $this->sendNextQuestionOrComplete($driver);

            return;
        }

        $this->handleInvalidAnswer($driver, $question, 'برجاء إرسال إجابتك نصياً:');
    }

    /**
     * Handle invalid answer with retry counter (max 2 retries, then skip & flag for review).
     */
    protected function handleInvalidAnswer(Driver $driver, Question $question, string $retryPrompt): void
    {
        $driver->increment('questionnaire_retries');
        $retries = $driver->questionnaire_retries;

        if ($retries < 2) {
            $this->messageSender->sendText($driver, $retryPrompt);
            $this->sendQuestion($driver, $question);

            return;
        }

        // Reached max retries (>= 2): mark question skipped and flag driver for review
        $version = $question->currentVersion;
        $this->saveAnswer(
            driver: $driver,
            question: $question,
            version: $version,
            valueText: 'تم التخطي بسبب تكرار الإجابة غير الصحيحة',
            isSkipped: true
        );

        $driver->update([
            'needs_review' => true,
            'review_reason' => "تعذر الحصول على إجابة صحيحة لسؤال: {$version?->text_ar}",
            'questionnaire_retries' => 0,
        ]);

        $this->messageSender->sendText(
            $driver,
            'تم تخطي هذا السؤال وسنتابع معك لاحقاً. ننتقل للسؤال التالي:'
        );

        $this->sendNextQuestionOrComplete($driver);
    }

    /**
     * Save an answer to driver_answers table maintaining current flag and history.
     */
    public function saveAnswer(
        Driver $driver,
        Question $question,
        $version,
        ?string $valueText = null,
        ?float $valueNumber = null,
        ?QuestionOption $option = null,
        bool $isSkipped = false
    ): DriverAnswer {
        return DB::transaction(function () use ($driver, $question, $version, $valueText, $valueNumber, $option, $isSkipped) {
            // Unset previous current answer for this question
            DriverAnswer::where('driver_id', $driver->id)
                ->where('question_id', $question->id)
                ->update(['is_current' => false]);

            $answer = DriverAnswer::create([
                'driver_id' => $driver->id,
                'question_id' => $question->id,
                'question_version_id' => $version->id,
                'option_id' => $option?->id,
                'value_text' => $valueText,
                'value_number' => $valueNumber,
                'is_current' => true,
                'is_skipped' => $isSkipped,
                'answered_at' => now(),
            ]);

            $updateData = ['questionnaire_retries' => 0];

            if ($question->key === 'driver_name' && ! empty($valueText) && ! $isSkipped) {
                $updateData['name'] = trim($valueText);
            }

            $driver->update($updateData);

            try {
                $answeredCount = DriverAnswer::where('driver_id', $driver->id)->where('is_current', true)->count();
                $totalQuestions = Question::active()->count();

                broadcast(new QuestionnaireProgressUpdated(
                    driverId: $driver->id,
                    questionText: $version?->text_ar ?? $question->key,
                    answer: $valueText ?? (string) ($valueNumber ?? ''),
                    answeredCount: $answeredCount,
                    totalQuestions: $totalQuestions,
                    updatedAt: now()->toISOString(),
                ));
            } catch (\Throwable $e) {
                Log::warning("[QuestionnaireService] Broadcast failed: {$e->getMessage()}");
            }

            return $answer;
        });
    }

    /**
     * Resend current question (Admin action).
     */
    public function resendCurrentQuestion(Driver $driver): ?WhatsAppMessage
    {
        $currentQuestion = $this->getNextUnansweredQuestion($driver);

        if (! $currentQuestion) {
            return null;
        }

        return $this->sendQuestion($driver, $currentQuestion);
    }

    /**
     * Request answer update from an active driver (Admin action).
     * Only asks new active questions added after the driver completed the previous questionnaire.
     */
    public function requestAnswerUpdate(Driver $driver): bool
    {
        $nextQuestion = $this->getNextUnansweredQuestion($driver);

        if (! $nextQuestion) {
            return false;
        }

        if (DriverStatusService::canTransition($driver->status, DriverStatus::QUESTIONNAIRE_IN_PROGRESS)) {
            DriverStatusService::transition(
                $driver,
                DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
                'طلب تحديث الإجابات لأسئلة جديدة'
            );
        }

        $this->messageSender->sendText(
            $driver,
            'أهلاً بك يا كابتن! نود تحديث بعض بياناتك في النظام من خلال الإجابة على الأسئلة التالية:'
        );

        $this->sendQuestion($driver, $nextQuestion);

        return true;
    }

    /**
     * Process reminders and timeouts for questionnaire in progress (run by scheduled command every 5 min).
     *
     * @return array{reminded: int, timed_out: int}
     */
    public function processRemindersAndTimeouts(): array
    {
        $reminderHours = (int) Setting::get('questionnaire_reminder_hours', 24);
        $timeoutDays = (int) Setting::get('questionnaire_timeout_days', 3);

        $remindedCount = 0;
        $timedOutCount = 0;

        // 1. Timeouts: status is questionnaire_in_progress and last sent was > timeoutDays ago
        $timedOutDrivers = Driver::where('status', DriverStatus::QUESTIONNAIRE_IN_PROGRESS)
            ->where('questionnaire_last_sent_at', '<=', now()->subDays($timeoutDays))
            ->get();

        foreach ($timedOutDrivers as $driver) {
            if (DriverStatusService::canTransition($driver->status, DriverStatus::NO_RESPONSE)) {
                DriverStatusService::transition(
                    $driver,
                    DriverStatus::NO_RESPONSE,
                    'انتهاء مهلة الاستبيان دون استكمال الإجابات'
                );
                $timedOutCount++;
            }
        }

        // 2. Reminders: status is questionnaire_in_progress, reminder not sent yet, and last sent > reminderHours ago
        $reminderDrivers = Driver::where('status', DriverStatus::QUESTIONNAIRE_IN_PROGRESS)
            ->whereNull('questionnaire_reminder_sent_at')
            ->where('questionnaire_last_sent_at', '<=', now()->subHours($reminderHours))
            ->get();

        foreach ($reminderDrivers as $driver) {
            try {
                $this->messageSender->sendText(
                    $driver,
                    'تذكير: مرحباً بك يا كابتن! نود تذكيرك باستكمال الإجابة على الأسئلة لتفعيل حسابك والبدء في استلام الرحلات:'
                );

                $currentQuestion = $this->getNextUnansweredQuestion($driver);
                if ($currentQuestion) {
                    $this->sendQuestion($driver, $currentQuestion);
                }

                $driver->update([
                    'questionnaire_reminder_sent_at' => now(),
                ]);

                $remindedCount++;
            } catch (\Throwable $e) {
                Log::warning("[QuestionnaireService] Could not send reminder to driver {$driver->id}: {$e->getMessage()}");
            }
        }

        return ['reminded' => $remindedCount, 'timed_out' => $timedOutCount];
    }

    /**
     * Resume questionnaire for a driver who replies after a timeout (NO_RESPONSE).
     */
    public function resumeQuestionnaire(Driver $driver, InboundMessage $inbound): void
    {
        if (DriverStatusService::canTransition($driver->status, DriverStatus::QUESTIONNAIRE_IN_PROGRESS)) {
            DriverStatusService::transition(
                $driver,
                DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
                'استئناف الاستبيان بعد انتهاء المهلة بناءً على رد السائق'
            );
        }

        $this->messageSender->sendText(
            $driver,
            'أهلاً بك مجدداً يا كابتن! يسعدنا تواصلك، سنستكمل استبيانك من حيث توقفت:'
        );

        $nextQuestion = $this->getNextUnansweredQuestion($driver);
        if ($nextQuestion) {
            $this->sendQuestion($driver, $nextQuestion);
        } else {
            $this->completeQuestionnaire($driver);
        }
    }
}
