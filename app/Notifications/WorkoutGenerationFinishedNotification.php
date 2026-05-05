<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WorkoutGenerationFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $workoutId,
        private readonly string $status,
        private readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'workout_generation_finished',
            'title' => 'Atualizacao da geracao do treino #' . $this->workoutId,
            'workout_id' => $this->workoutId,
            'status' => $this->status,
            'message' => $this->message,
            'level' => $this->status === 'done' ? 'success' : 'error',
        ];
    }
}
