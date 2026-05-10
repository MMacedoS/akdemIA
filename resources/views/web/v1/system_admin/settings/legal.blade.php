@extends('layouts.panel')

@section('pageTitle', 'System Admin - Documentos Legais')
@section('headerTitle', 'Documentos Legais')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.dashboard') }}">Voltar ao dashboard</a>
@endsection

@section('content')
    <div class="card" style="max-width: 980px;">
        <h3>Termos de uso e politica de privacidade</h3>
        <p>Cadastre o conteudo oficial publicado para acesso web e API. O aceite dos usuarios continuara vinculado ao campo de versao informado abaixo.</p>

        <form method="POST" action="{{ route('system-admin.settings.legal.update') }}" class="content-stack" style="margin-top: 12px;">
            @csrf
            @method('PUT')

            @php
                $terms = $documents->get('terms');
                $privacy = $documents->get('privacy_policy');
            @endphp

            <div class="content-stack" style="gap: 24px;">
                <section class="card" style="background: rgba(255,255,255,0.02); border-color: rgba(255,255,255,0.08);">
                    <h4>Termos de Uso</h4>
                    <div class="form-grid">
                        <div class="field">
                            <label for="terms_title">Titulo</label>
                            <input id="terms_title" name="terms_title" type="text" maxlength="150" value="{{ old('terms_title', $terms['title']) }}" required>
                        </div>

                        <div class="field">
                            <label for="terms_version">Versao</label>
                            <input id="terms_version" name="terms_version" type="text" maxlength="50" value="{{ old('terms_version', $terms['version']) }}" required>
                        </div>

                        <div class="field">
                            <label for="terms_effective_date">Data de vigencia</label>
                            <input id="terms_effective_date" name="terms_effective_date" type="date" value="{{ old('terms_effective_date', $terms['effective_date']) }}" required>
                        </div>

                        <div class="field" style="grid-column: 1 / -1;">
                            <label for="terms_content_html">Conteudo HTML</label>
                            <textarea id="terms_content_html" name="terms_content_html" rows="14" spellcheck="false" required>{{ old('terms_content_html', $terms['content_html']) }}</textarea>
                            <small>URL publica: {{ route('legal.terms') }} | API: {{ route('api.legal.terms.show') }}</small>
                        </div>
                    </div>
                </section>

                <section class="card" style="background: rgba(255,255,255,0.02); border-color: rgba(255,255,255,0.08);">
                    <h4>Politica de Privacidade</h4>
                    <div class="form-grid">
                        <div class="field">
                            <label for="privacy_title">Titulo</label>
                            <input id="privacy_title" name="privacy_title" type="text" maxlength="150" value="{{ old('privacy_title', $privacy['title']) }}" required>
                        </div>

                        <div class="field">
                            <label for="privacy_version">Versao</label>
                            <input id="privacy_version" name="privacy_version" type="text" maxlength="50" value="{{ old('privacy_version', $privacy['version']) }}" required>
                        </div>

                        <div class="field">
                            <label for="privacy_effective_date">Data de vigencia</label>
                            <input id="privacy_effective_date" name="privacy_effective_date" type="date" value="{{ old('privacy_effective_date', $privacy['effective_date']) }}" required>
                        </div>

                        <div class="field" style="grid-column: 1 / -1;">
                            <label for="privacy_content_html">Conteudo HTML</label>
                            <textarea id="privacy_content_html" name="privacy_content_html" rows="14" spellcheck="false" required>{{ old('privacy_content_html', $privacy['content_html']) }}</textarea>
                            <small>URL publica: {{ route('legal.privacy') }} | API: {{ route('api.legal.privacy.show') }}</small>
                        </div>
                    </div>
                </section>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Salvar documentos</button>
            </div>
        </form>
    </div>
@endsection
