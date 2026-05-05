<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if ((bool) $request->user()?->isSystemAdmin()) {
            return redirect()->route('system-admin.dashboard');
        }

        return view('web.v1.system_admin.auth.login');
    }

    /**
     * @throws ValidationException
     */
    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $user = User::query()
            ->where('email', strtolower((string) $data['email']))
            ->first();

        if (
            $user === null
            || ! (bool) $user->is_active
            || ! $user->isSystemAdmin()
            || ! Hash::check((string) $data['password'], (string) $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => 'Credenciais invalidas para o administrador do sistema.',
            ]);
        }

        Auth::login($user, (bool) ($data['remember'] ?? false));
        $request->session()->regenerate();
        $request->session()->forget('tenant_id');
        $request->session()->forget('url.intended');

        return redirect()->route('system-admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('system-admin.login');
    }
}
