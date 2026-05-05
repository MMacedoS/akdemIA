<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserCommunicationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $subject,
        private readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject($this->subject)
            ->markdown('emails.notifications.user-communication', [
                'recipientName' => $this->resolveRecipientName($notifiable),
                'subjectLine' => $this->subject,
                'messageBody' => $this->message,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'manual_email_communication',
            'title' => $this->subject,
            'subject' => $this->subject,
            'message' => $this->message,
            'level' => 'info',
        ];
    }

    private function resolveRecipientName(object $notifiable): string
    {
        return isset($notifiable->name) ? (string) $notifiable->name : 'usuario';
    }
}
