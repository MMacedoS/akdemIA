<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDeletionRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $confirmationUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Confirmacao de exclusao da conta')
            ->markdown('emails.notifications.account-deletion-request', [
                'recipientName' => $this->resolveRecipientName($notifiable),
                'confirmationUrl' => $this->confirmationUrl,
                'contactEmail' => (string) config('legal.contact_email'),
            ]);
    }

    private function resolveRecipientName(object $notifiable): string
    {
        return isset($notifiable->name) ? (string) $notifiable->name : 'usuario';
    }
}
