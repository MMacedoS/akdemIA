<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
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
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Atualizacao da geracao do treino #' . $this->workoutId)
            ->markdown('emails.notifications.workout-generation-finished', [
                'recipientName' => $this->resolveRecipientName($notifiable),
                'workoutId' => $this->workoutId,
                'status' => $this->status,
                'statusLabel' => $this->status === 'done' ? 'Gerado com sucesso' : 'Falha na geracao',
                'messageBody' => $this->message,
            ]);
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

    private function resolveRecipientName(object $notifiable): string
    {
        return isset($notifiable->name) ? (string) $notifiable->name : 'usuario';
    }
}
