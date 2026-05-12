<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreAccountDeletionRequest;
use App\Models\User;
use App\Notifications\AccountDeletionRequestNotification;
use App\Services\Auth\UserAccountDeletionService;
use App\Support\FormPatterns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class AccountDeletionController extends Controller
{
    public function create(Request $request): View
    {
        return view('account-deletion.request', [
            'prefilledEmail' => old('email', $request->user()?->email),
            'contactEmail' => (string) config('legal.contact_email'),
        ]);
    }

    public function store(StoreAccountDeletionRequest $request): RedirectResponse
    {
        $email = FormPatterns::normalizeEmail($request->string('email')->toString());

        $user = User::query()
            ->where('email', $email)
            ->first();

        if ($user !== null && ! $user->isSystemAdmin()) {
            $confirmationUrl = URL::temporarySignedRoute(
                'drop-account.confirm',
                now()->addMinutes(60),
                [
                    'user' => $user->id,
                    'hash' => sha1((string) $user->email),
                ],
            );

            $user->notify(new AccountDeletionRequestNotification($confirmationUrl));
        }

        return to_route('drop-account.create')->with(
            'status',
            'Se existir uma conta para este e-mail, enviamos um link de confirmacao para concluir a exclusao.',
        );
    }

    public function confirm(Request $request, User $user, string $hash): View
    {
        abort_unless($this->hasValidEmailHash($user, $hash), 403);

        return view('account-deletion.confirm', [
            'user' => $user,
            'signedActionUrl' => $request->fullUrl(),
            'contactEmail' => (string) config('legal.contact_email'),
        ]);
    }

    public function destroy(
        Request $request,
        User $user,
        string $hash,
        UserAccountDeletionService $userAccountDeletionService,
    ): RedirectResponse {
        abort_unless($this->hasValidEmailHash($user, $hash), 403);

        $authenticatedUser = $request->user();

        if ($authenticatedUser !== null && $authenticatedUser->is($user)) {
            Auth::logout();
        }

        $userAccountDeletionService->delete($user);

        if ($authenticatedUser !== null && $authenticatedUser->is($user)) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return to_route('drop-account.create')->with(
            'status',
            'Conta e dados vinculados removidos com sucesso.',
        );
    }

    private function hasValidEmailHash(User $user, string $hash): bool
    {
        return hash_equals(sha1((string) $user->email), $hash) && ! $user->isSystemAdmin();
    }
}
