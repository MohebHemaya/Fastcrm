<?php

namespace App\Console\Commands;

use App\Services\QuestionnaireService;
use Illuminate\Console\Command;

class CheckQuestionnaireTimeoutsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'questionnaire:check-timeouts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process questionnaire reminders and timeouts for drivers in progress';

    /**
     * Execute the console command.
     */
    public function handle(QuestionnaireService $questionnaireService): int
    {
        $this->info('Checking questionnaire reminders and timeouts...');

        $result = $questionnaireService->processRemindersAndTimeouts();

        $this->info("Reminders sent: {$result['reminded']}");
        $this->info("Timeouts processed: {$result['timed_out']}");

        return self::SUCCESS;
    }
}
