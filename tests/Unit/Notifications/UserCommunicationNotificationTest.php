<?php

namespace Tests\Unit\Notifications;

use App\Notifications\UserCommunicationNotification;
use stdClass;
use Tests\TestCase;

class UserCommunicationNotificationTest extends TestCase
{
    public function test_it_uses_database_and_mail_channels(): void
    {
        $notification = new UserCommunicationNotification(
            subject: 'Aviso importante',
            message: 'Esta e uma mensagem de comunicacao.',
        );

        $this->assertSame(['database', 'mail'], $notification->via(new stdClass()));
    }

    public function test_it_builds_database_payload_with_expected_fields(): void
    {
        $notification = new UserCommunicationNotification(
            subject: 'Aviso importante',
            message: 'Esta e uma mensagem de comunicacao.',
        );

        $payload = $notification->toArray(new stdClass());

        $this->assertSame('manual_email_communication', $payload['type']);
        $this->assertSame('Aviso importante', $payload['subject']);
        $this->assertSame('Esta e uma mensagem de comunicacao.', $payload['message']);
    }

    public function test_it_builds_mail_message_with_subject(): void
    {
        $notification = new UserCommunicationNotification(
            subject: 'Aviso importante',
            message: 'Esta e uma mensagem de comunicacao.',
        );

        $notifiable = new stdClass();
        $notifiable->name = 'Maria';

        $mailMessage = $notification->toMail($notifiable);

        $this->assertSame('Aviso importante', $mailMessage->subject);
    }
}
