<?php

namespace App\Http\Controllers\Web\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\FormPatterns;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(
        private readonly AuthManager $auth,
    ) {}

    public function redirect(): RedirectResponse
    {
        abort_unless($this->isGoogleConfigured(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($this->isGoogleConfigured(), 404);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors([
                'email' => 'Nao foi possivel autenticar com o Google. Tente novamente.',
            ]);
        }

        $email = FormPatterns::normalizeEmail($googleUser->getEmail());

        if ($email === null) {
            return redirect()->route('login')->withErrors([
                'email' => 'Sua conta Google nao retornou um e-mail valido.',
            ]);
        }

        $user = User::query()
            ->where('google_id', $googleUser->getId())
            ->orWhere('email', $email)
            ->first();

        if (! $user instanceof User) {
            $user = User::query()->create([
                'name' => trim((string) ($googleUser->getName() ?: 'Usuario Google')),
                'email' => $email,
                'password' => Str::password(32),
                'email_verified_at' => now(),
                'google_id' => (string) $googleUser->getId(),
                'auth_provider' => 'google',
                'profile_type' => null,
                'is_active' => true,
            ]);
        } else {
            if (! (bool) $user->is_active || $user->isSystemAdmin()) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Esta conta nao pode usar login pelo Google neste acesso.',
                ]);
            }

            $user->forceFill([
                'google_id' => (string) $googleUser->getId(),
                'auth_provider' => 'google',
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        $this->auth->guard('web')->login($user, true);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    private function isGoogleConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}
