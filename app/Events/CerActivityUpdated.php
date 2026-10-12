<?php

namespace App\Events;

use App\Models\CerQuiz;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CerActivityUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ?CerQuiz $quiz = null,
        public string $action = 'updated'
    ) {}

    /**
     * Broadcast ke channel publik 'cer-activities'
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('cer-activities'),
        ];
    }

    /**
     * Nama alias event yang didengarkan oleh Echo di Frontend (.activity.updated)
     */
    public function broadcastAs(): string
    {
        return 'activity.updated';
    }
}