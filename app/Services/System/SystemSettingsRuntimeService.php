<?php

namespace App\Services\System;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemSettingsRuntimeService
{
    public function apply(): void
    {
        if (! $this->settingsTableExists()) {
            return;
        }

        $settings = SystemSetting::query()
            ->whereIn('key', [
                'payment.provider_name',
                'payment.api_base_url',
                'payment.api_token',
                'payment.pix_key',
                'payment.mercadopago_webhook_secret',
                'mail.mailer',
                'mail.host',
                'mail.port',
                'mail.username',
                'mail.password',
                'mail.encryption',
                'mail.from_address',
                'mail.from_name',
                'google.client_id',
                'google.client_secret',
                'google.redirect_uri',
                'workoutx.enabled',
                'workoutx.api_base_url',
                'workoutx.api_key',
                'workoutx.auth_mode',
                'workoutx.request_timeout',
                'workoutx.default_limit',
                'workoutx.sync_page_delay_seconds',
                'workoutx.allow_fallback',
                'workout.generate_cost',
                'workout.reuse_cost',
                'workout.reactivate_cost',
                'workout.active_days',
            ])
            ->pluck('value', 'key');

        $mailMailer = $this->stringOrNull($settings->get('mail.mailer'));
        $mailPort = $this->intOrNull($settings->get('mail.port'));
        $mailScheme = $this->resolveMailScheme($settings->get('mail.encryption'));

        config([
            'mail.default' => $mailMailer ?: config('mail.default'),
            'mail.mailers.smtp.host' => $this->stringOrNull($settings->get('mail.host')) ?: config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => $mailPort ?: config('mail.mailers.smtp.port'),
            'mail.mailers.smtp.username' => $this->stringOrNull($settings->get('mail.username')) ?: config('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password' => $this->stringOrNull($settings->get('mail.password')) ?: config('mail.mailers.smtp.password'),
            'mail.mailers.smtp.scheme' => $mailScheme ?? config('mail.mailers.smtp.scheme'),
            'mail.from.address' => $this->stringOrNull($settings->get('mail.from_address')) ?: config('mail.from.address'),
            'mail.from.name' => $this->stringOrNull($settings->get('mail.from_name')) ?: config('mail.from.name'),
            'services.google.client_id' => $this->stringOrNull($settings->get('google.client_id')) ?: config('services.google.client_id'),
            'services.google.client_secret' => $this->stringOrNull($settings->get('google.client_secret')) ?: config('services.google.client_secret'),
            'services.google.redirect' => $this->stringOrNull($settings->get('google.redirect_uri')) ?: config('services.google.redirect'),
            'services.payment.provider_name' => $this->stringOrNull($settings->get('payment.provider_name')) ?: 'mercadopago',
            'services.payment.api_base_url' => $this->stringOrNull($settings->get('payment.api_base_url')) ?: config('services.payment.api_base_url'),
            'services.payment.api_token' => $this->stringOrNull($settings->get('payment.api_token')) ?: config('services.payment.api_token'),
            'services.payment.pix_key' => $this->stringOrNull($settings->get('payment.pix_key')) ?: config('services.payment.pix_key'),
            'services.mercadopago.webhook_secret' => $this->stringOrNull($settings->get('payment.mercadopago_webhook_secret')) ?: config('services.mercadopago.webhook_secret'),
            'services.pix.key' => $this->stringOrNull($settings->get('payment.pix_key')) ?: config('services.pix.key'),
            'services.workoutx.enabled' => $this->boolOrNull($settings->get('workoutx.enabled')) ?? config('services.workoutx.enabled'),
            'services.workoutx.api_base_url' => $this->stringOrNull($settings->get('workoutx.api_base_url')) ?: config('services.workoutx.api_base_url'),
            'services.workoutx.api_key' => $this->stringOrNull($settings->get('workoutx.api_key')) ?: config('services.workoutx.api_key'),
            'services.workoutx.auth_mode' => $this->stringOrNull($settings->get('workoutx.auth_mode')) ?: config('services.workoutx.auth_mode'),
            'services.workoutx.request_timeout' => $this->intOrNull($settings->get('workoutx.request_timeout')) ?: config('services.workoutx.request_timeout'),
            'services.workoutx.default_limit' => $this->intOrNull($settings->get('workoutx.default_limit')) ?: config('services.workoutx.default_limit'),
            'services.workoutx.sync_page_delay_seconds' => $this->intOrNull($settings->get('workoutx.sync_page_delay_seconds')) ?: config('services.workoutx.sync_page_delay_seconds'),
            'services.workoutx.allow_fallback' => $this->boolOrNull($settings->get('workoutx.allow_fallback')) ?? config('services.workoutx.allow_fallback'),
            'workouts.credits.generate' => $this->intOrNull($settings->get('workout.generate_cost')) ?: config('workouts.credits.generate'),
            'workouts.credits.reuse' => $this->intOrNull($settings->get('workout.reuse_cost')) ?: config('workouts.credits.reuse'),
            'workouts.credits.reactivate' => $this->intOrNull($settings->get('workout.reactivate_cost')) ?: config('workouts.credits.reactivate'),
            'workouts.active_days' => $this->intOrNull($settings->get('workout.active_days')) ?: config('workouts.active_days'),
        ]);
    }

    private function settingsTableExists(): bool
    {
        try {
            return Schema::hasTable('system_settings');
        } catch (Throwable) {
            return false;
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function boolOrNull(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    private function resolveMailScheme(mixed $value): ?string
    {
        $normalized = $this->stringOrNull($value);

        if ($normalized === null) {
            return null;
        }

        return match (mb_strtolower($normalized)) {
            'ssl', 'smtps' => 'smtps',
            'tls', 'starttls', 'smtp' => 'smtp',
            default => null,
        };
    }
}
