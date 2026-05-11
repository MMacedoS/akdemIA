<?php

namespace App\Http\Controllers\Web\V1\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'name', 'email', 'credits_balance', 'is_add_credit', 'is_active', 'is_system_admin', 'profile_type']);

        return view('web.v1.system_admin.users.index', [
            'users' => $users,
        ]);
    }

    public function updateAddCredit(Request $request, int $id): RedirectResponse
    {
        $actor = $request->user();

        if ($actor === null) {
            abort(401, 'Unauthenticated.');
        }

        $validated = $request->validate([
            'is_add_credit' => ['required', 'boolean'],
        ]);

        $targetUser = User::query()->findOrFail($id);
        $targetUser->forceFill([
            'is_add_credit' => (bool) $validated['is_add_credit'],
        ]);
        $targetUser->save();

        return redirect()->route('system-admin.users.index')
            ->with('status', 'Permissao de adicionar credito atualizada com sucesso.');
    }

    public function activate(Request $request, int $id): RedirectResponse
    {
        $actor = $request->user();

        if ($actor === null) {
            abort(401, 'Unauthenticated.');
        }

        $targetUser = User::query()->findOrFail($id);
        $targetUser->forceFill(['is_active' => true]);
        $targetUser->save();

        return redirect()->route('system-admin.users.index')
            ->with('status', 'Usuario ativado com sucesso.');
    }

    public function inactivate(Request $request, int $id): RedirectResponse
    {
        $actor = $request->user();

        if ($actor === null) {
            abort(401, 'Unauthenticated.');
        }

        if ((int) $actor->id === $id) {
            return redirect()->route('system-admin.users.index')
                ->withErrors('Voce nao pode inativar seu proprio usuario.');
        }

        $targetUser = User::query()->findOrFail($id);

        if ($targetUser->isSystemAdmin()) {
            return redirect()->route('system-admin.users.index')
                ->withErrors('Nao e permitido inativar um usuario system admin.');
        }

        $targetUser->forceFill(['is_active' => false]);
        $targetUser->save();

        return redirect()->route('system-admin.users.index')
            ->with('status', 'Usuario inativado com sucesso.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $actor = $request->user();

        if ($actor === null) {
            abort(401, 'Unauthenticated.');
        }

        if ((int) $actor->id === $id) {
            return redirect()->route('system-admin.users.index')
                ->withErrors('Voce nao pode remover seu proprio usuario.');
        }

        $targetUser = User::query()->findOrFail($id);

        if ($targetUser->isSystemAdmin()) {
            return redirect()->route('system-admin.users.index')
                ->withErrors('Nao e permitido remover um usuario system admin.');
        }

        $targetUser->delete();

        return redirect()->route('system-admin.users.index')
            ->with('status', 'Usuario removido com sucesso.');
    }
}
