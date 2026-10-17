<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a driver answers a questionnaire question.
 * Broadcasts on private-whatsapp.driver.{driverId} for live chat tracking.
 */
class QuestionnaireProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $driverId,
        public readonly string $questionText,
        public readonly string $answer,
        public readonly int $answeredCount,
        public readonly int $totalQuestions,
        public readonly string $updatedAt,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("whatsapp.driver.{$this->driverId}"),
            new PrivateChannel('drivers'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'QuestionnaireProgressUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driverId,
            'question_text' => $this->questionText,
            'answer' => $this->answer,
            'answered_count' => $this->answeredCount,
            'total_questions' => $this->totalQuestions,
            'updated_at' => $this->updatedAt,
        ];
    }
}
