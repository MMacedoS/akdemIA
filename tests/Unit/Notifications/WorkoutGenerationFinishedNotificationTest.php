<?php

namespace Tests\Unit\Notifications;

use App\Notifications\WorkoutGenerationFinishedNotification;
use stdClass;
use Tests\TestCase;

class WorkoutGenerationFinishedNotificationTest extends TestCase
{
    public function test_it_uses_database_and_mail_channels(): void
    {
        $notification = new WorkoutGenerationFinishedNotification(
            workoutId: 123,
            status: 'done',
            message: 'Treino gerado.',
        );

        $this->assertSame(['database', 'mail'], $notification->via(new stdClass()));
    }

    public function test_it_builds_expected_database_payload(): void
    {
        $notification = new WorkoutGenerationFinishedNotification(
            workoutId: 123,
            status: 'done',
            message: 'Treino gerado.',
        );

        $payload = $notification->toArray(new stdClass());

        $this->assertSame(123, $payload['workout_id']);
        $this->assertSame('done', $payload['status']);
        $this->assertSame('Treino gerado.', $payload['message']);
    }

    public function test_it_builds_markdown_mail_message_with_status_context(): void
    {
        $notification = new WorkoutGenerationFinishedNotification(
            workoutId: 123,
            status: 'error',
            message: 'Falha ao gerar o treino.',
        );

        $notifiable = new stdClass();
        $notifiable->name = 'Carlos';

        $mailMessage = $notification->toMail($notifiable);

        $this->assertSame('Atualizacao da geracao do treino #123', $mailMessage->subject);
        $this->assertSame('emails.notifications.workout-generation-finished', $mailMessage->markdown);
        $this->assertSame('Carlos', $mailMessage->viewData['recipientName']);
        $this->assertSame('Falha na geracao', $mailMessage->viewData['statusLabel']);
    }
}
