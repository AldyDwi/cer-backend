<?php

namespace App\Events;

use App\Models\QuizAttempt;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizAttemptSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public QuizAttempt $attempt
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('cer-activities'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'attempt.submitted';
    }
}