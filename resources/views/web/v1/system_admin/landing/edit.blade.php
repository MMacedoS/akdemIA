@extends('layouts.panel')

@section('pageTitle', 'Landing Geral')
@section('headerTitle', 'Landing Geral da Aplicacao')

@section('content')
    <style>
        .system-landing-shell { display: grid; gap: 16px; }
        .system-landing-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(300px, 0.85fr); gap: 18px; align-items: start; }
        .system-preview {
            border: 1px solid #e5e7eb;
            border-radius: 24px;
            overflow: hidden;
            background: linear-gradient(180deg, #fffdfa 0%, #f4f7fb 100%);
            position: sticky;
            top: 20px;
            box-shadow: 0 20px 40px rgba(15, 35, 60, 0.08);
        }
        .system-preview-hero {
            background: linear-gradient(130deg, #0f3559, #0f6eaf 58%, #e58e3a);
            color: #fff;
            padding: 24px;
            display: grid;
            gap: 14px;
        }
        .system-preview-hero h3 { margin: 0; font-size: 28px; line-height: 1.08; }
        .system-preview-hero p { margin: 0; color: rgba(255, 255, 255, 0.92); line-height: 1.6; }
        .system-preview-badge {
            display: inline-flex;
            width: fit-content;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .system-preview-ctas { display: flex; gap: 8px; flex-wrap: wrap; }
        .system-preview-btn { border-radius: 10px; padding: 8px 12px; font-size: 12px; font-weight: 700; border: 1px solid transparent; }
        .system-preview-btn-primary { background: #fff; color: #0f3559; }
        .system-preview-btn-secondary { background: transparent; color: #fff; border-color: rgba(255, 255, 255, 0.5); }
        .system-preview-hero-layout { display: grid; grid-template-columns: minmax(0, 1fr) 180px; gap: 14px; align-items: stretch; }
        .system-preview-image {
            min-height: 210px;
            border-radius: 18px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.16);
        }
        .system-preview-image img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .system-preview-image-fallback {
            height: 100%;
            padding: 16px;
            display: grid;
            align-content: end;
            gap: 10px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.1), rgba(11, 19, 31, 0.3));
        }
        .system-preview-image-fallback div {
            padding: 10px 12px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.12);
            font-size: 12px;
            font-weight: 700;
        }
        .system-preview-sections { display: grid; gap: 12px; padding: 18px; }
        .system-preview-section {
            border: 1px solid #edf0f4;
            border-radius: 18px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.92);
            color: #1f2c3a;
        }
        .system-preview-section h5 { margin: 0 0 8px; font-size: 15px; }
        .system-preview-section p { margin: 0; color: #516070; line-height: 1.6; font-size: 14px; }
        .system-preview-list { display: grid; gap: 7px; margin: 10px 0 0; padding: 0; list-style: none; }
        .system-preview-list li { display: flex; gap: 8px; color: #334155; font-size: 13px; line-height: 1.5; }
        .system-preview-list li::before {
            content: '';
            width: 8px;
            height: 8px;
            margin-top: 6px;
            border-radius: 999px;
            background: linear-gradient(135deg, #0f6eaf, #e58e3a);
            flex: 0 0 auto;
        }
        .system-preview-contact { display: grid; gap: 8px; }
        .system-preview-contact-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 12px;
            background: #f5f8fc;
            color: #24415b;
            font-size: 13px;
            font-weight: 700;
        }
        @media (max-width: 1080px) {
            .system-landing-grid { grid-template-columns: 1fr; }
            .system-preview { position: static; }
        }
        @media (max-width: 720px) {
            .system-preview-hero-layout { grid-template-columns: 1fr; }
        }
    </style>

    <div class="content-stack">
        <div class="card" style="max-width: 1240px;">
            <h3>Configurar landing do site</h3>
            <p>Personalize a landing publica com foco em conversao, autoridade e contato comercial.</p>

            <form method="POST" action="{{ route('system-admin.landing.update') }}" class="content-stack system-landing-shell" style="margin-top: 12px;">
                @csrf
                @method('PUT')

                <div class="system-landing-grid">
                    <div class="form-shell">
                        <section class="form-section">
                            <h4>Hero</h4>
                            <div class="form-grid">
                                <div class="field" style="grid-column: 1 / -1;">
                                    <label for="hero_title">Titulo principal</label>
                                    <input id="hero_title" name="hero_title" type="text" maxlength="255" value="{{ old('hero_title', $setting?->hero_title) }}">
                                </div>
                                <div class="field" style="grid-column: 1 / -1;">
                                    <label for="hero_description">Subtitulo</label>
                                    <textarea id="hero_description" name="hero_description" rows="4">{{ old('hero_description', $setting?->hero_description) }}</textarea>
                                </div>
                                <div class="field" style="grid-column: 1 / -1;">
                                    <label for="hero_image_url">Imagem de destaque</label>
                                    <input id="hero_image_url" name="hero_image_url" type="url" maxlength="2000" value="{{ old('hero_image_url', $setting?->hero_image_url) }}" placeholder="https://...">
                                </div>
                            </div>
                        </section>

                        <section class="form-section">
                            <h4>Chamadas de acao</h4>
                            <div class="form-grid">
                                <div class="field">
                                    <label for="primary_cta_text">Texto CTA primaria</label>
                                    <input id="primary_cta_text" name="primary_cta_text" type="text" maxlength="80" value="{{ old('primary_cta_text', $setting?->primary_cta_text) }}">
                                </div>
                                <div class="field">
                                    <label for="primary_cta_url">URL CTA primaria</label>
                                    <input id="primary_cta_url" name="primary_cta_url" type="url" maxlength="2000" value="{{ old('primary_cta_url', $setting?->primary_cta_url) }}">
                                </div>
                                <div class="field">
                                    <label for="secondary_cta_text">Texto CTA secundaria</label>
                                    <input id="secondary_cta_text" name="secondary_cta_text" type="text" maxlength="80" value="{{ old('secondary_cta_text', $setting?->secondary_cta_text) }}">
                                </div>
                                <div class="field">
                                    <label for="secondary_cta_url">URL CTA secundaria</label>
                                    <input id="secondary_cta_url" name="secondary_cta_url" type="url" maxlength="2000" value="{{ old('secondary_cta_url', $setting?->secondary_cta_url) }}">
                                </div>
                            </div>
                        </section>

                        <section class="form-section">
                            <h4>Sobre</h4>
                            <div class="form-grid">
                                <div class="field" style="grid-column: 1 / -1;">
                                    <label for="about_title">Titulo institucional</label>
                                    <input id="about_title" name="about_title" type="text" maxlength="160" value="{{ old('about_title', $setting?->about_title) }}">
                                </div>
                                <div class="field" style="grid-column: 1 / -1;">
                                    <label for="about_content">Texto institucional</label>
                                    <textarea id="about_content" name="about_content" rows="5">{{ old('about_content', $setting?->about_content) }}</textarea>
                                </div>
                            </div>
                        </section>

                        <section class="form-section">
                            <h4>Titulos de secoes</h4>
                            <div class="form-grid">
                                <div class="field">
                                    <label for="tenants_section_title">Titulo da secao de contratantes</label>
                                    <input id="tenants_section_title" name="tenants_section_title" type="text" maxlength="120" value="{{ old('tenants_section_title', $setting?->tenants_section_title) }}">
                                </div>
                                <div class="field">
                                    <label for="professionals_section_title">Titulo da secao de profissionais</label>
                                    <input id="professionals_section_title" name="professionals_section_title" type="text" maxlength="120" value="{{ old('professionals_section_title', $setting?->professionals_section_title) }}">
                                </div>
                                <div class="field">
                                    <label for="differentials_section_title">Titulo dos diferenciais</label>
                                    <input id="differentials_section_title" name="differentials_section_title" type="text" maxlength="120" value="{{ old('differentials_section_title', $setting?->differentials_section_title) }}">
                                </div>
                                <div class="field">
                                    <label for="contact_section_title">Titulo do contato</label>
                                    <input id="contact_section_title" name="contact_section_title" type="text" maxlength="120" value="{{ old('contact_section_title', $setting?->contact_section_title) }}">
                                </div>
                            </div>
                        </section>

                        <section class="form-section">
                            <h4>Contato</h4>
                            <div class="form-grid">
                                <div class="field" style="grid-column: 1 / -1;">
                                    <label for="contact_description">Texto de apoio</label>
                                    <textarea id="contact_description" name="contact_description" rows="4">{{ old('contact_description', $setting?->contact_description) }}</textarea>
                                </div>
                                <div class="field">
                                    <label for="contact_email">Email de contato</label>
                                    <input id="contact_email" name="contact_email" type="email" maxlength="190" value="{{ old('contact_email', $setting?->contact_email) }}">
                                </div>
                                <div class="field">
                                    <label for="contact_whatsapp">WhatsApp</label>
                                    <input id="contact_whatsapp" name="contact_whatsapp" type="text" maxlength="40" value="{{ old('contact_whatsapp', $setting?->contact_whatsapp) }}" placeholder="5511999999999 ou (11) 99999-9999">
                                </div>
                            </div>
                        </section>
                    </div>

                    <section class="form-section">
                        <h4>Preview ao vivo</h4>
                        <div class="system-preview" aria-live="polite">
                            <div class="system-preview-hero">
                                <span class="system-preview-badge">Landing SaaS para gestao de treinos</span>
                                <div class="system-preview-hero-layout">
                                    <div>
                                        <h3 id="preview-hero-title">{{ old('hero_title', $setting?->hero_title ?: 'Sistema SaaS para academias, trainers e alunos evoluirem juntos.') }}</h3>
                                        <p id="preview-hero-description">{{ old('hero_description', $setting?->hero_description ?: 'Centralize operacao, prescricao de treinos, comunicacao e acompanhamento de resultados em uma experiencia moderna, multi-tenant e pronta para escalar.') }}</p>
                                        <div class="system-preview-ctas">
                                            <span class="system-preview-btn system-preview-btn-primary" id="preview-primary-cta">{{ old('primary_cta_text', $setting?->primary_cta_text ?: 'Comecar agora') }}</span>
                                            <span class="system-preview-btn system-preview-btn-secondary" id="preview-secondary-cta">{{ old('secondary_cta_text', $setting?->secondary_cta_text ?: 'Ver como funciona') }}</span>
                                        </div>
                                    </div>

                                    <div class="system-preview-image">
                                        @if(old('hero_image_url', $setting?->hero_image_url))
                                            <img id="preview-hero-image" src="{{ old('hero_image_url', $setting?->hero_image_url) }}" alt="Preview da imagem de destaque">
                                            <div class="system-preview-image-fallback" id="preview-hero-image-fallback" hidden>
                                                <div>Academia</div>
                                                <div>Trainer</div>
                                                <div>Aluno</div>
                                            </div>
                                        @else
                                            <img id="preview-hero-image" src="" alt="Preview da imagem de destaque" hidden>
                                            <div class="system-preview-image-fallback" id="preview-hero-image-fallback">
                                                <div>Academia</div>
                                                <div>Trainer</div>
                                                <div>Aluno</div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="system-preview-sections">
                                <div class="system-preview-section">
                                    <h5 id="preview-about-title">{{ old('about_title', $setting?->about_title ?: 'Uma plataforma unica para simplificar a gestao de treinos e acelerar a operacao da academia.') }}</h5>
                                    <p id="preview-about-content">{{ old('about_content', $setting?->about_content ?: 'A AkdemIA organiza a jornada completa entre academia, treinadores e alunos em um unico ambiente.') }}</p>
                                </div>

                                <div class="system-preview-section">
                                    <h5 id="preview-tenants-title">{{ old('tenants_section_title', $setting?->tenants_section_title ?: 'Contratantes em destaque') }}</h5>
                                    <h5 id="preview-professionals-title" style="margin-top: 14px;">{{ old('professionals_section_title', $setting?->professionals_section_title ?: 'Profissionais e trainees') }}</h5>
                                    <p id="preview-differentials-title" style="margin-top: 14px; font-weight: 700; color: #1f2c3a;">{{ old('differentials_section_title', $setting?->differentials_section_title ?: 'Por que a AkdemIA cresce como produto SaaS') }}</p>
                                    <ul class="system-preview-list">
                                        <li>Sistema SaaS multi-tenant</li>
                                        <li>Interface moderna</li>
                                        <li>Acesso web e mobile</li>
                                        <li>Integracao com APIs de exercicios</li>
                                    </ul>
                                </div>

                                <div class="system-preview-section">
                                    <h5 id="preview-contact-title">{{ old('contact_section_title', $setting?->contact_section_title ?: 'Vamos conversar sobre sua operacao') }}</h5>
                                    <p id="preview-contact-description">{{ old('contact_description', $setting?->contact_description ?: 'Envie sua mensagem para conhecer a plataforma, entender o modelo SaaS e avaliar como a AkdemIA pode apoiar a sua academia ou equipe tecnica.') }}</p>
                                    <div class="system-preview-contact" style="margin-top: 12px;">
                                        <div class="system-preview-contact-row">
                                            <span>Email</span>
                                            <span id="preview-contact-email">{{ old('contact_email', $setting?->contact_email ?: config('mail.from.address') ?: 'comercial@akdemia.com') }}</span>
                                        </div>
                                        <div class="system-preview-contact-row">
                                            <span>WhatsApp</span>
                                            <span id="preview-contact-whatsapp">{{ old('contact_whatsapp', $setting?->contact_whatsapp ?: 'Nao informado') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Salvar landing geral</button>
                    <a href="{{ route('home') }}" class="btn btn-soft" target="_blank" rel="noopener">Visualizar pagina inicial</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const bindTextPreview = (inputId, targetId, fallbackText) => {
                const input = document.getElementById(inputId);
                const target = document.getElementById(targetId);

                if (!input || !target) {
                    return;
                }

                const sync = () => {
                    const value = (input.value || '').trim();
                    target.textContent = value || fallbackText;
                };

                input.addEventListener('input', sync);
                sync();
            };

            const bindImagePreview = (inputId, imageId, fallbackId) => {
                const input = document.getElementById(inputId);
                const image = document.getElementById(imageId);
                const fallback = document.getElementById(fallbackId);

                if (!input || !image || !fallback) {
                    return;
                }

                const sync = () => {
                    const value = (input.value || '').trim();

                    if (value) {
                        image.src = value;
                        image.hidden = false;
                        fallback.hidden = true;
                        return;
                    }

                    image.removeAttribute('src');
                    image.hidden = true;
                    fallback.hidden = false;
                };

                input.addEventListener('input', sync);
                sync();
            };

            bindTextPreview('hero_title', 'preview-hero-title', 'Sistema SaaS para academias, trainers e alunos evoluirem juntos.');
            bindTextPreview('hero_description', 'preview-hero-description', 'Centralize operacao, prescricao de treinos, comunicacao e acompanhamento de resultados em uma experiencia moderna, multi-tenant e pronta para escalar.');
            bindTextPreview('primary_cta_text', 'preview-primary-cta', 'Comecar agora');
            bindTextPreview('secondary_cta_text', 'preview-secondary-cta', 'Ver como funciona');
            bindTextPreview('about_title', 'preview-about-title', 'Uma plataforma unica para simplificar a gestao de treinos e acelerar a operacao da academia.');
            bindTextPreview('about_content', 'preview-about-content', 'A AkdemIA organiza a jornada completa entre academia, treinadores e alunos em um unico ambiente.');
            bindTextPreview('tenants_section_title', 'preview-tenants-title', 'Contratantes em destaque');
            bindTextPreview('professionals_section_title', 'preview-professionals-title', 'Profissionais e trainees');
            bindTextPreview('differentials_section_title', 'preview-differentials-title', 'Por que a AkdemIA cresce como produto SaaS');
            bindTextPreview('contact_section_title', 'preview-contact-title', 'Vamos conversar sobre sua operacao');
            bindTextPreview('contact_description', 'preview-contact-description', 'Envie sua mensagem para conhecer a plataforma, entender o modelo SaaS e avaliar como a AkdemIA pode apoiar a sua academia ou equipe tecnica.');
            bindTextPreview('contact_email', 'preview-contact-email', '{{ config('mail.from.address') ?: 'comercial@akdemia.com' }}');
            bindTextPreview('contact_whatsapp', 'preview-contact-whatsapp', 'Nao informado');
            bindImagePreview('hero_image_url', 'preview-hero-image', 'preview-hero-image-fallback');
        })();
    </script>
@endsection
