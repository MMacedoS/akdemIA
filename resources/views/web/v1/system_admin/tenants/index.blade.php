@extends('layouts.panel')

@section('pageTitle', 'System Admin - Tenants')
@section('headerTitle', 'Tenants')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.dashboard') }}">Voltar ao dashboard</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="card" style="max-width: 860px;">
            <h3>Criar tenant</h3>
            <form method="POST" action="{{ route('system-admin.tenants.store') }}" class="content-stack" style="margin-top: 12px;">
                @csrf

                <div class="form-grid">
                    <div class="field">
                        <label for="tenant_name">Nome</label>
                        <input id="tenant_name" name="name" type="text" required maxlength="120" value="{{ old('name') }}">
                    </div>

                    <div class="field">
                        <label for="tenant_slug">Slug (opcional)</label>
                        <input id="tenant_slug" name="slug" type="text" maxlength="120" placeholder="ex: academia-centro" value="{{ old('slug') }}" spellcheck="false">
                    </div>

                    <div class="field" style="grid-column: 1 / -1;">
                        <label for="tenant_email">Email de acesso do tenant</label>
                        <input id="tenant_email" name="email" type="email" required value="{{ old('email') }}" data-normalize="email" spellcheck="false" autocomplete="email">
                        <small>A senha inicial sera @academai123 e o usuario recebera um e-mail com a URL de acesso e a orientacao para trocar a senha.</small>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Criar tenant</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Tenants recentes</h3>
            <div class="cards-grid" style="margin-top: 12px;">
                @forelse ($tenants as $tenant)
                    <article class="user-card">
                        <h4>{{ $tenant->name }}</h4>
                        <p>Slug: {{ $tenant->slug }}</p>
                        <p>Email: {{ $tenant->contact_email ?? 'nao informado' }}</p>
                        <p>Criado em: {{ format_date_br($tenant->created_at, 'nao informado', 'd/m/Y H:i') }}</p>
                        <p>
                            Status:
                            @if ((bool) $tenant->is_active)
                                <span class="badge success">Ativo</span>
                            @else
                                <span class="badge warning">Inativo</span>
                            @endif
                        </p>
                        <div class="actions">
                            <a class="btn btn-soft" href="{{ route('system-admin.tenants.edit', $tenant->id) }}">Editar</a>
                            <form method="POST" action="{{ route('system-admin.tenants.destroy', $tenant->id) }}" onsubmit="return confirm('Deseja excluir este tenant e remover em cascata usuarios de acesso, trainees, alunos, landings, conteudos e arquivos associados?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn" style="background: #fff0f2; color: #b93c4c; border: 1px solid #ffd6dc;">Excluir</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="card">
                        <p>Nenhum tenant cadastrado.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
