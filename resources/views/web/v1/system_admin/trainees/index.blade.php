@extends('layouts.panel')

@section('pageTitle', 'System Admin - Trainers')
@section('headerTitle', 'Trainers')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.dashboard') }}">Voltar ao dashboard</a>
@endsection

@section('content')
    <div class="content-stack grid grid-cols-1 lg:grid-cols-2">
        <div class="card">
            <h3>Criar trainer</h3>
            <form method="POST" action="{{ route('system-admin.trainees.store') }}" class="content-stack" style="margin-top: 12px;">
                @csrf

                <div class="form-grid">
                    <div class="field">
                        <label for="trainee_name">Nome</label>
                        <input id="trainee_name" name="name" type="text" required maxlength="120" value="{{ old('name') }}">
                    </div>

                    <div class="field">
                        <label for="trainee_email">Email</label>
                        <input id="trainee_email" name="email" type="email" required maxlength="190" value="{{ old('email') }}" data-normalize="email" spellcheck="false" autocomplete="email">
                    </div>

                    <div class="field">
                        <label for="trainee_password">Senha</label>
                        <input id="trainee_password" name="password" type="password" required minlength="8">
                    </div>

                    <div class="field">
                        <label for="trainee_password_confirmation">Confirmacao da senha</label>
                        <input id="trainee_password_confirmation" name="password_confirmation" type="password" required minlength="8">
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Criar trainer</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Vincular trainer a tenant</h3>
            <form method="POST" action="{{ route('system-admin.trainees.links.store') }}" class="content-stack" style="margin-top: 12px;">
                @csrf

                <div class="form-grid">
                    <div class="field">
                        <label for="trainee_user_id">Trainer</label>
                        <select id="trainee_user_id" name="trainee_user_id" required>
                            <option value="">Selecione</option>
                            @foreach ($trainees as $trainee)
                                <option value="{{ $trainee->id }}" @selected((int) old('trainee_user_id') === (int) $trainee->id)>
                                    {{ $trainee->name }} ({{ $trainee->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="tenant_id">Tenant</label>
                        <select id="tenant_id" name="tenant_id" required>
                            <option value="">Selecione</option>
                            @foreach ($tenants as $tenant)
                                <option value="{{ $tenant->id }}" @selected((int) old('tenant_id') === (int) $tenant->id)>
                                    {{ $tenant->name }} ({{ $tenant->slug }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field" style="grid-column: 1 / -1;">
                        <label for="link_note">Observacao</label>
                        <input id="link_note" name="note" type="text" maxlength="500" value="{{ old('note') }}">
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Salvar vinculo</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Trainers recentes</h3>
            <div class="cards-grid" style="margin-top: 12px;">
                @forelse ($trainees as $trainee)
                    <article class="user-card">
                        <h4>{{ $trainee->name }}</h4>
                        <p>{{ $trainee->email }}</p>
                        <p>Criado em: {{ format_date_br($trainee->created_at, 'nao informado', 'd/m/Y H:i') }}</p>
                        <p>
                            Status:
                            @if ((bool) $trainee->is_active)
                                <span class="badge success">Ativo</span>
                            @else
                                <span class="badge warning">Inativo</span>
                            @endif
                        </p>
                    </article>
                @empty
                    <div class="card">
                        <p>Nenhum trainer cadastrado.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <h3>Vinculos recentes</h3>
            <div class="cards-grid" style="margin-top: 12px;">
                @forelse ($links as $link)
                    <article class="user-card">
                        <h4>{{ $link->trainee_name }}</h4>
                        <p>{{ $link->trainee_email }}</p>
                        <p>Tenant: {{ $link->tenant_name }} ({{ $link->tenant_slug }})</p>
                        <p>Vinculado por: {{ $link->linked_by_email ?? 'sistema' }}</p>
                        <p>Quando: {{ format_date_br($link->created_at, 'nao informado', 'd/m/Y H:i') }}</p>
                        <p>Obs: {{ $link->note ?? 'sem observacao' }}</p>
                    </article>
                @empty
                    <div class="card">
                        <p>Nenhum vinculo registrado.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
