<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantAccessCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tenantName,
        private readonly string $tenantUrl,
        private readonly string $loginUrl,
        private readonly string $email,
        private readonly string $password,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Acesso inicial do tenant ' . $this->tenantName)
            ->greeting('Ola, ' . $notifiable->name . '!')
            ->line('Seu acesso inicial ao tenant foi criado com sucesso.')
            ->line('Tenant: ' . $this->tenantName)
            ->line('Email de acesso: ' . $this->email)
            ->line('Senha inicial: ' . $this->password)
            ->line('URL do tenant: ' . $this->tenantUrl)
            ->action('Entrar no painel', $this->loginUrl)
            ->line('Troque a senha imediatamente no primeiro acesso.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'tenant_access_created',
            'title' => 'Acesso inicial liberado para ' . $this->tenantName,
            'message' => 'Seu acesso inicial ao tenant foi criado. Entre no painel e troque a senha no primeiro acesso.',
            'tenant_name' => $this->tenantName,
            'tenant_url' => $this->tenantUrl,
            'login_url' => $this->loginUrl,
            'email' => $this->email,
            'temporary_password' => $this->password,
            'password_warning' => 'Troque a senha no primeiro acesso.',
            'level' => 'success',
        ];
    }
}
