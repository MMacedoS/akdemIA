@extends('layouts.panel')

@section('pageTitle', 'System Admin - Editar Tenant')
@section('headerTitle', 'Editar Tenant')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.tenants.index') }}">Voltar para tenants</a>
@endsection

@section('content')
    <div class="card" style="max-width: 980px;">
        <h3>Dados do tenant</h3>

        <form method="POST" action="{{ route('system-admin.tenants.update', $tenant->id) }}" class="content-stack" style="margin-top: 12px;">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field">
                    <label for="tenant_name">Nome</label>
                    <input id="tenant_name" name="name" type="text" required maxlength="120" value="{{ old('name', $tenant->name) }}">
                </div>

                <div class="field">
                    <label for="tenant_slug">Slug</label>
                    <input id="tenant_slug" name="slug" type="text" required maxlength="120" value="{{ old('slug', $tenant->slug) }}" spellcheck="false">
                </div>

                <div class="field">
                    <label for="contact_email">Email de contato</label>
                    <input id="contact_email" name="contact_email" type="email" maxlength="190" value="{{ old('contact_email', $tenant->contact_email) }}" data-normalize="email" spellcheck="false" autocomplete="email">
                </div>

                <div class="field">
                    <label for="contact_phone">Telefone</label>
                    <input id="contact_phone" name="contact_phone" type="text" maxlength="40" value="{{ old('contact_phone', format_phone_br($tenant->contact_phone, '')) }}" data-mask="phone">
                </div>

                <div class="field">
                    <label for="document_number">Documento</label>
                    <input id="document_number" name="document_number" type="text" maxlength="40" value="{{ old('document_number', format_document_br($tenant->document_number, '')) }}" data-mask="document">
                </div>

                <div class="field">
                    <label for="is_active">Status</label>
                    <select id="is_active" name="is_active">
                        <option value="1" @selected((string) old('is_active', $tenant->is_active ? '1' : '0') === '1')>Ativo</option>
                        <option value="0" @selected((string) old('is_active', $tenant->is_active ? '1' : '0') === '0')>Inativo</option>
                    </select>
                </div>

                <div class="field" style="grid-column: 1 / -1;">
                    <label for="notes">Observacoes</label>
                    <textarea id="notes" name="notes">{{ old('notes', $tenant->notes) }}</textarea>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Salvar tenant</button>
            </div>
        </form>
    </div>
@endsection
