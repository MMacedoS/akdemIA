@extends('layouts.panel')

@section('pageTitle', 'System Admin - Usuarios')
@section('headerTitle', 'Usuarios do Sistema')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.dashboard') }}">Voltar ao dashboard</a>
@endsection

@section('content')
    <div class="card">
        <h3>Gestao de usuarios</h3>
        <div class="cards-grid" style="margin-top: 12px;">
            @forelse ($users as $user)
                <article class="user-card">
                    <h4>{{ $user->name }}</h4>
                    <p>{{ $user->email }}</p>
                    <p>Perfil: {{ $user->profile_type ?? 'nao definido' }}</p>
                    <p>Saldo de creditos: {{ (int) $user->credits_balance }}</p>
                    <p>Adicionar credito: {{ (bool) $user->is_add_credit ? 'Sim' : 'Nao' }}</p>
                    <p>
                        Status:
                        @if ((bool) $user->is_active)
                            <span class="badge success">Ativo</span>
                        @else
                            <span class="badge warning">Inativo</span>
                        @endif
                    </p>

                    @if ((bool) $user->is_system_admin)
                        <p><span class="badge primary">System Admin</span></p>
                    @endif

                    <form method="POST" action="{{ route('system-admin.users.add-credit.update', $user->id) }}" style="margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                        @csrf
                        <label for="is_add_credit_{{ $user->id }}">Adicionar credito</label>
                        <select id="is_add_credit_{{ $user->id }}" name="is_add_credit">
                            <option value="1" @selected((bool) $user->is_add_credit)>Sim</option>
                            <option value="0" @selected(! (bool) $user->is_add_credit)>Nao</option>
                        </select>
                        <button type="submit" class="btn btn-soft">Salvar</button>
                    </form>

                    <div class="actions" style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
                        @if (! (bool) $user->is_system_admin)
                            @if ((bool) $user->is_active)
                                <form method="POST" action="{{ route('system-admin.users.inactivate', $user->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-soft">Inativar</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('system-admin.users.activate', $user->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary">Ativar</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('system-admin.users.destroy', $user->id) }}" onsubmit="return confirm('Deseja realmente remover este usuario? Esta acao e permanente.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn" style="background: #fff0f2; color: #b93c4c; border: 1px solid #ffd6dc;">Remover</button>
                            </form>
                        @else
                            <span class="badge primary">Protegido</span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="card">
                    <p>Nenhum usuario encontrado.</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection
