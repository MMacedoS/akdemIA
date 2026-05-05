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
}
