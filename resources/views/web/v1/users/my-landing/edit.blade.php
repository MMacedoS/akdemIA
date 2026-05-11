@extends('layouts.panel')

@section('pageTitle', 'Minha Landing')
@section('headerTitle', 'Minha Landing Page')

@section('content')
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Cormorant+Garamond:wght@600;700&display=swap');

        .content-stack {
            --sq-bg: #eef1ea;
            --sq-paper: #fbfaf5;
            --sq-sand: #e4ddcf;
            --sq-ink: #1f241f;
            --sq-muted: #5f655d;
            --sq-accent: #688b7d;
            --sq-accent-strong: #365247;
            --sq-line: #d3d8cb;
            --sq-focus: rgba(104, 139, 125, 0.2);
            font-family: 'Manrope', 'Segoe UI', sans-serif;
            display: grid;
            gap: 24px;
        }

        .content-stack > .card {
            border: 1px solid var(--sq-line);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, #f7f3ea 100%);
            border-radius: 28px;
            box-shadow: 0 18px 46px rgba(40, 36, 28, 0.08);
            padding: 28px;
        }

        .content-stack > .card > h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(38px, 3vw, 52px);
            line-height: .92;
            color: var(--sq-ink);
            margin: 0 0 8px;
            text-align: left;
            letter-spacing: -.02em;
        }

        .landing-hero-card {
            position: relative;
            overflow: hidden;
            padding: 34px;
            border-radius: 34px;
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.55), transparent 22%),
                linear-gradient(135deg, #183027 0%, #6e9182 55%, #d8dfd3 100%);
            color: #f7f7f2;
        }

        .landing-hero-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, 0.08), transparent 40%, rgba(255, 255, 255, 0.04));
            pointer-events: none;
        }

        .landing-hero-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(280px, .7fr);
            gap: 24px;
            align-items: end;
        }

        .landing-hero-copy {
            display: grid;
            gap: 16px;
        }

        .landing-eyebrow {
            margin: 0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .18em;
            color: rgba(247, 247, 242, 0.82);
        }

        .landing-hero-copy h3 {
            margin: 0;
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(48px, 5vw, 74px);
            line-height: .9;
            letter-spacing: -.03em;
        }

        .landing-hero-copy p {
            margin: 0;
            max-width: 58ch;
            color: rgba(247, 247, 242, 0.9);
            line-height: 1.8;
        }

        .landing-hero-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: 6px;
        }

        .landing-stat {
            padding: 14px 16px;
            border-radius: 22px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
        }

        .landing-stat strong {
            display: block;
            font-size: 28px;
            font-family: 'Cormorant Garamond', serif;
            line-height: .95;
        }

        .landing-stat span,
        .landing-hero-side p,
        .section-subtitle,
        .preview-meta,
        .editor-note,
        .collection-item p,
        .collection-item span {
            color: rgba(247, 247, 242, 0.84);
            line-height: 1.7;
        }

        .landing-hero-side {
            display: grid;
            gap: 14px;
        }

        .hero-note-card {
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(12, 24, 20, 0.22);
            padding: 18px;
        }

        .hero-note-card strong {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .16em;
        }

        .workspace-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(320px, .85fr);
            gap: 24px;
            align-items: start;
        }

        .landing-form-shell {
            display: grid;
            gap: 20px;
        }

        .landing-form-section {
            border: 1px solid var(--sq-line);
            border-radius: 22px;
            padding: 20px;
            background: #fff;
            display: grid;
            gap: 16px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.45);
        }

        .landing-form-section h4 {
            margin: 0;
            font-size: 30px;
            color: var(--sq-ink);
            text-align: left;
            font-family: 'Cormorant Garamond', serif;
            font-weight: 700;
            letter-spacing: -.01em;
            line-height: .96;
        }

        .landing-form-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .landing-field {
            display: grid;
            gap: 8px;
        }

        .landing-field-full {
            grid-column: 1 / -1;
        }

        .landing-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--sq-muted);
            font-weight: 700;
        }

        .landing-label::before {
            content: attr(data-icon);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 22px;
            border-radius: 999px;
            border: 1px solid #d2d8d4;
            background: #edf2ef;
            color: var(--sq-accent-strong);
            font-size: 10px;
            letter-spacing: .04em;
            padding: 0 8px;
            box-sizing: border-box;
        }

        .landing-field .field-control {
            border: 1px solid var(--sq-line);
            border-radius: 16px;
            padding: 13px 14px;
            font-size: 14px;
            background: #fffdfa;
            width: 100%;
            color: var(--sq-ink);
            font-family: 'Manrope', 'Segoe UI', sans-serif;
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        }

        .landing-field .field-control:focus {
            outline: none;
            border-color: var(--sq-accent);
            box-shadow: 0 0 0 3px var(--sq-focus);
            transform: translateY(-1px);
        }

        .landing-check {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--sq-accent-strong);
            background: #edf2ef;
            border: 1px solid #d2d8d4;
            border-radius: 999px;
            padding: 9px 12px;
            width: fit-content;
        }

        .upload-hint {
            color: var(--sq-muted);
            font-size: 12px;
        }

        .live-preview-card {
            border: 1px solid var(--sq-line);
            border-radius: 28px;
            overflow: hidden;
            background: #fffdfa;
            box-shadow: 0 18px 42px rgba(40, 36, 28, 0.08);
            position: sticky;
            top: 24px;
        }

        .live-preview-hero {
            --preview-bg: #203128;
            --preview-surface: #355144;
            --preview-text: #f8f7f2;
            --preview-muted: rgba(248, 247, 242, 0.84);
            --preview-button-bg: #f2e8d8;
            --preview-button-text: #1f241f;
            padding: 28px;
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.16), transparent 22%),
                linear-gradient(135deg, var(--preview-bg) 0%, var(--preview-surface) 100%);
            color: var(--preview-text);
            display: grid;
            gap: 12px;
            text-align: left;
        }

        .live-preview-hero h4 {
            margin: 0;
            font-size: clamp(34px, 3vw, 46px);
            line-height: .95;
            font-family: 'Cormorant Garamond', serif;
            font-weight: 700;
        }

        .live-preview-hero p {
            margin: 0;
            color: var(--preview-muted);
        }

        .preview-pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .preview-pill {
            display: inline-flex;
            align-items: center;
            min-height: 34px;
            padding: 0 12px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.08);
            color: var(--preview-text);
            font-size: 11px;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 800;
        }

        .preview-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            min-height: 42px;
            padding: 0 16px;
            border-radius: 999px;
            background: var(--preview-button-bg);
            color: var(--preview-button-text);
            font-size: 12px;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 800;
        }

        .live-preview-media {
            padding: 16px;
            display: grid;
            gap: 10px;
            border-top: 1px solid rgba(31, 36, 31, 0.08);
        }

        .live-preview-body {
            padding: 20px;
            display: grid;
            gap: 18px;
            border-top: 1px solid var(--sq-line);
            background: linear-gradient(180deg, #f8f5ed 0%, #f2eee4 100%);
        }

        .live-preview-section h5 {
            margin: 0 0 12px;
            font-size: 24px;
            color: var(--sq-ink);
            text-align: left;
            font-family: 'Cormorant Garamond', serif;
            letter-spacing: -.01em;
            line-height: .96;
        }

        .live-preview-gallery {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .live-preview-gallery-item {
            border: 1px solid var(--sq-line);
            background: #fffdfa;
            border-radius: 14px;
            padding: 8px;
            display: grid;
            gap: 6px;
        }

        .live-preview-gallery-item strong {
            font-size: 11px;
            color: #475569;
            text-transform: uppercase;
        }

        .live-preview-gallery-item span {
            font-size: 12px;
            color: #111827;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .live-preview-posts {
            display: grid;
            gap: 8px;
        }

        .live-preview-post {
            border: 1px solid var(--sq-line);
            border-radius: 16px;
            background: #fffdfa;
            padding: 14px;
            display: grid;
            gap: 4px;
            text-align: left;
        }

        .live-preview-post h6 {
            margin: 0;
            font-size: 13px;
            color: #111827;
        }

        .live-preview-post p {
            margin: 0;
            font-size: 12px;
            color: #6b7280;
        }

        .live-preview-media img,
        .live-preview-media video {
            max-width: 100%;
            width: 100%;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            background: #f3f4f6;
        }

        .media-limit-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #d2d8d4;
            border-radius: 999px;
            background: #edf2ef;
            color: var(--sq-accent-strong);
            font-size: 12px;
            font-weight: 700;
            padding: 6px 10px;
        }

        .media-limit-chip.full {
            border-color: #e3c1bf;
            background: #f8ecea;
            color: #93433d;
        }

        .btn.btn-primary {
            background: linear-gradient(120deg, var(--sq-accent-strong), var(--sq-accent));
            border-color: var(--sq-accent-strong);
            color: #f9f8f4;
            box-shadow: 0 12px 24px rgba(53, 82, 71, 0.18);
        }

        .btn.btn-soft {
            border-color: var(--sq-line);
            background: #fffdfa;
            color: var(--sq-ink);
        }

        .posts-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .posts-summary {
            color: var(--sq-muted);
            font-size: 13px;
        }

        .post-editor-card {
            border: 1px solid var(--sq-line);
            border-radius: 18px;
            background: #fff;
            padding: 18px;
            display: grid;
            gap: 16px;
        }

        .post-editor-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        .post-editor-head h4 {
            margin: 0;
            font-size: 28px;
            color: var(--sq-ink);
            font-family: 'Cormorant Garamond', serif;
            line-height: .96;
        }

        .post-editor-meta {
            color: var(--sq-muted);
            border-radius: 22px;
        }
            padding: 22px;
        .post-status-chip {
            display: inline-flex;
            align-items: center;

        .section-subtitle {
            margin: -2px 0 0;
            color: var(--sq-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .editor-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .editor-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .editor-badge {
            display: inline-flex;
            align-items: center;
            min-height: 36px;
            padding: 0 12px;
            border-radius: 999px;
            background: #edf2ef;
            border: 1px solid #d2d8d4;
            color: var(--sq-accent-strong);
            font-size: 11px;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 800;
        }

        .preview-shell {
            display: grid;
            gap: 16px;
        }

        .preview-meta-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .preview-meta-card {
            border: 1px solid var(--sq-line);
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f7f5ee 100%);
            padding: 14px;
            display: grid;
            gap: 6px;
        }

        .preview-meta-card strong {
            color: var(--sq-ink);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .12em;
        }

        .preview-meta-card span {
            color: var(--sq-muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .collection-list {
            display: grid;
            gap: 12px;
        }

        .collection-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            padding: 16px 0;
            border-top: 1px solid rgba(31, 36, 31, 0.08);
        }

        .collection-item:first-of-type {
            border-top: 0;
            padding-top: 0;
        }

        .collection-item strong {
            display: block;
            color: var(--sq-ink);
            margin-bottom: 4px;
        }

        .collection-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
            gap: 6px;
            border-radius: 999px;
            .landing-hero-grid,
            .workspace-grid {
                grid-template-columns: 1fr;
            }

            .landing-hero-stats,
            .preview-meta-grid {
                grid-template-columns: 1fr;
            }

            padding: 6px 10px;
            font-size: 12px;
            font-weight: 800;
            border: 1px solid #d2d8d4;
            background: #edf2ef;
            color: var(--sq-accent-strong);
        }

        .post-status-chip.draft {
            background: #f5efe3;
            border-color: #e3d1b5;
            color: #8b5e2d;
        }

        @media (max-width: 900px) {
            .landing-form-grid {
                grid-template-columns: 1fr;
            }

            .live-preview-gallery {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>

    <div class="content-stack">
        @if (session('status'))
            <div class="flash-success">{{ session('status') }}</div>
        @endif

        @if (session('compression_status'))
            <div class="flash-success">{{ session('compression_status') }}</div>
        @endif

        <div class="card landing-hero-card">
            <div class="landing-hero-grid">
                <div class="landing-hero-copy">
                    <p class="landing-eyebrow">Studio de marca para sua landing</p>
                    <h3>Transforme a pagina em uma vitrine mais premium, clara e confiavel.</h3>
                    <p>Este editor agora esta organizado para funcionar como uma mesa de direcao visual: mensagem, oferta, prova visual e contato em um fluxo mais profissional.</p>
                    <div class="landing-hero-stats">
                        <div class="landing-stat">
                            <strong>01</strong>
                            <span>Defina headline, bio e posicionamento.</span>
                        </div>
                        <div class="landing-stat">
                            <strong>02</strong>
                            <span>Monte a oferta com servicos e midias.</span>
                        </div>
                        <div class="landing-stat">
                            <strong>03</strong>
                            <span>Valide tudo no preview antes de publicar.</span>
                        </div>
                    </div>
                </div>
                <div class="landing-hero-side">
                    <div class="hero-note-card">
                        <strong>Direcao</strong>
                        <p>Mais hierarquia tipografica, melhor contraste, melhor uso do espaco e preview com mais fidelidade visual.</p>
                    </div>
                    <div class="hero-note-card">
                        <strong>Foco</strong>
                        <p>Uma landing que pareca produto premium, e nao apenas um formulario publicado.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="workspace-grid">
            <div class="card">
                <div class="editor-toolbar">
                    <div>
                        <h3>Configuracoes da landing</h3>
                        <p class="section-subtitle">Voce pode enviar arquivo ou informar URL externa. Limites: imagem ate 3MB, video ate 25MB.</p>
                    </div>
                    <div class="editor-badges">
                        <span class="editor-badge">Mensagem</span>
                        <span class="editor-badge">Oferta</span>
                        <span class="editor-badge">Conversao</span>
                    </div>
                </div>
                <form id="landing-config-form" method="POST" action="{{ route('my-landing.update') }}" class="form-shell js-upload-form landing-form-shell" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <section class="landing-form-section">
                        <h4>Dados principais</h4>
                        <div class="landing-form-grid">
                        <div class="landing-field">
                            <label class="landing-label" data-icon="SLUG">Slug publico</label>
                            <input class="field-control" name="slug" value="{{ old('slug', $profile?->slug) }}" required>
                        </div>
                        <div class="landing-field">
                            <label class="landing-label" data-icon="H1">Headline</label>
                            <input class="field-control" name="headline" value="{{ old('headline', $profile?->headline) }}">
                        </div>
                        <div class="landing-field">
                            <label class="landing-label" data-icon="TH">Tema visual</label>
                            <select class="field-control" name="theme_preset">
                                <option value="myhra_bordeaux" @selected(old('theme_preset', $profile?->theme_preset) === 'myhra_bordeaux')>Myhra Bordeaux (vinho elegante)</option>
                                <option value="sage_serene" @selected(old('theme_preset', $profile?->theme_preset) === 'sage_serene')>Sage Serene (verde natural)</option>
                                <option value="graphite_noir" @selected(old('theme_preset', $profile?->theme_preset) === 'graphite_noir')>Graphite Noir (escuro premium)</option>
                                <option value="ocean_mist" @selected(old('theme_preset', $profile?->theme_preset) === 'ocean_mist')>Ocean Mist (azul clean)</option>
                            </select>
                        </div>
                        <div class="landing-field landing-field-full">
                            <label class="landing-label" data-icon="BIO">Bio</label>
                            <textarea class="field-control" name="bio" rows="3">{{ old('bio', $profile?->bio) }}</textarea>
                        </div>
                        <div class="landing-field landing-field-full">
                            <label class="landing-label" data-icon="SK">Habilidades</label>
                            <textarea class="field-control" name="skills" rows="3">{{ old('skills', $profile?->skills) }}</textarea>
                        </div>
                    </div>
                </section>

                <section class="landing-form-section">
                    <h4>Cards de servico</h4>
                    <div class="landing-form-grid">
                        <div class="landing-field landing-field-full">
                            <label class="landing-label" data-icon="SEC">Titulo da secao</label>
                            <input class="field-control" name="service_section_title" value="{{ old('service_section_title', $profile?->service_section_title) }}" placeholder="Ex: Atendimento, conteudo e video">
                        </div>

                        <div class="landing-field landing-field-full">
                            <label class="landing-label" data-icon="C1">Card 1</label>
                        </div>
                        <div class="landing-field">
                            <input class="field-control" name="service_one_label" value="{{ old('service_one_label', $profile?->service_one_label) }}" placeholder="Etiqueta do card 1">
                        </div>
                        <div class="landing-field">
                            <input class="field-control" name="service_one_title" value="{{ old('service_one_title', $profile?->service_one_title) }}" placeholder="Titulo do card 1">
                        </div>
                        <div class="landing-field landing-field-full">
                            <textarea class="field-control" name="service_one_description" rows="3" placeholder="Descricao do card 1">{{ old('service_one_description', $profile?->service_one_description) }}</textarea>
                        </div>
                        <div class="landing-field">
                            <input class="field-control" name="service_one_link_label" value="{{ old('service_one_link_label', $profile?->service_one_link_label) }}" placeholder="Texto do link do card 1">
                        </div>
                        <div class="landing-field">
                            <input class="field-control" name="service_one_link_url" value="{{ old('service_one_link_url', $profile?->service_one_link_url) }}" placeholder="Link do card 1, ex: #contato">
                        </div>

                        <div class="landing-field landing-field-full">
                            <label class="landing-label" data-icon="C2">Card 2</label>
                        </div>
                        <div class="landing-field">
                            <input class="field-control" name="service_two_label" value="{{ old('service_two_label', $profile?->service_two_label) }}" placeholder="Etiqueta do card 2">
                        </div>
                        <div class="landing-field">
                            <input class="field-control" name="service_two_title" value="{{ old('service_two_title', $profile?->service_two_title) }}" placeholder="Titulo do card 2">
                        </div>
                        <div class="landing-field landing-field-full">
                            <textarea class="field-control" name="service_two_description" rows="3" placeholder="Descricao do card 2">{{ old('service_two_description', $profile?->service_two_description) }}</textarea>
                        </div>
                        <div class="landing-field">
                            <input class="field-control" name="service_two_link_label" value="{{ old('service_two_link_label', $profile?->service_two_link_label) }}" placeholder="Texto do link do card 2">
                        </div>
                        <div class="landing-field">
                            <input class="field-control" name="service_two_link_url" value="{{ old('service_two_link_url', $profile?->service_two_link_url) }}" placeholder="Link do card 2, ex: #conteudos">
                        </div>

                        <div class="landing-field landing-field-full">
                            <label class="landing-label" data-icon="C3">Card 3</label>
                        </div>
                        <div class="landing-field">
                            <input class="field-control" name="service_three_label" value="{{ old('service_three_label', $profile?->service_three_label) }}" placeholder="Etiqueta do card 3">
                        </div>
                        <div class="landing-field">
                            <input class="field-control" name="service_three_title" value="{{ old('service_three_title', $profile?->service_three_title) }}" placeholder="Titulo do card 3">
                        </div>
                        <div class="landing-field landing-field-full">
                            <textarea class="field-control" name="service_three_description" rows="3" placeholder="Descricao do card 3">{{ old('service_three_description', $profile?->service_three_description) }}</textarea>
                        </div>
                        <div class="landing-field">
                            <input class="field-control" name="service_three_link_label" value="{{ old('service_three_link_label', $profile?->service_three_link_label) }}" placeholder="Texto do link do card 3">
                        </div>
                        <div class="landing-field">
                            <input class="field-control" name="service_three_link_url" value="{{ old('service_three_link_url', $profile?->service_three_link_url) }}" placeholder="Link do card 3, ex: #contato">
                        </div>
                    </div>
                </section>

                <section class="landing-form-section">
                    <h4>Contato</h4>
                    <div class="landing-form-grid">
                        <div class="landing-field">
                            <label class="landing-label" data-icon="WA">WhatsApp</label>
                            <input class="field-control" name="contact_whatsapp" value="{{ old('contact_whatsapp', $profile?->contact_whatsapp) }}" placeholder="Ex: 5511999998888 ou https://wa.me/5511999998888">
                        </div>
                        <div class="landing-field">
                            <label class="landing-label" data-icon="IG">Instagram</label>
                            <input class="field-control" name="contact_instagram" value="{{ old('contact_instagram', $profile?->contact_instagram) }}" placeholder="Ex: @seuinstagram ou https://instagram.com/seuinstagram">
                        </div>
                    </div>
                </section>

                <section class="landing-form-section">
                    <h4>Midias de destaque</h4>
                    <div class="landing-form-grid">
                        <div class="landing-field">
                            <label class="landing-label" data-icon="IMG">Imagem principal URL</label>
                            <input class="field-control" name="hero_image_url" value="{{ old('hero_image_url', $profile?->hero_image_url) }}">
                        </div>
                        <div class="landing-field">
                            <label class="landing-label" data-icon="UP">Upload de imagem</label>
                            <input class="field-control js-file-preview" data-preview-target="hero-image-preview" data-max-bytes="3145728" data-kind="image" name="hero_image_file" type="file" accept="image/*">
                            <small class="upload-hint">JPG, PNG, WEBP ou GIF ate 3MB. Imagem otimizada automaticamente.</small>
                        </div>
                    </div>
                </section>
                <div id="hero-image-preview" style="display:none;margin-bottom:12px;"></div>

                <section class="landing-form-section">
                    <div class="landing-form-grid">
                        <div class="landing-field">
                            <label class="landing-label" data-icon="VID">Video principal URL</label>
                            <input class="field-control" name="hero_video_url" value="{{ old('hero_video_url', $profile?->hero_video_url) }}">
                        </div>
                        <div class="landing-field">
                            <label class="landing-label" data-icon="UP">Upload de video</label>
                            <input class="field-control js-file-preview" data-preview-target="hero-video-preview" data-max-bytes="26214400" data-kind="video" name="hero_video_file" type="file" accept="video/*">
                            <small class="upload-hint">MP4, WEBM ou MOV ate 25MB.</small>
                        </div>
                    </div>
                </section>
                <div id="hero-video-preview" style="display:none;margin-bottom:12px;"></div>
                    <div class="form-actions">
                        <label class="landing-check"><input type="checkbox" name="is_published" value="1" {{ old('is_published', $profile?->is_published) ? 'checked' : '' }}> Publicar landing</label>
                        <span class="editor-note">Revise headline, imagem principal e contatos antes de publicar.</span>
                    </div>
                    <div class="js-upload-progress" style="display:none;margin:12px 0;">
                        <div style="height:10px;border-radius:999px;background:#e5e7eb;overflow:hidden;">
                            <div class="js-upload-progress-bar" style="height:10px;width:0;background:#2563eb;transition:width .2s ease;"></div>
                        </div>
                        <small class="js-upload-progress-text" style="color:#4b5563;">Preparando upload...</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Salvar landing</button>
                </form>
            </div>

            <div class="preview-shell">
                <div class="preview-meta-grid">
                    <article class="preview-meta-card">
                        <strong>Preview</strong>
                        <span>Atualiza em tempo real conforme voce edita.</span>
                    </article>
                    <article class="preview-meta-card">
                        <strong>Tema</strong>
                        <span>O card respeita a paleta escolhida para a landing.</span>
                    </article>
                    <article class="preview-meta-card">
                        <strong>Objetivo</strong>
                        <span>Checar impacto visual antes de publicar.</span>
                    </article>
                </div>

                <div class="card">
                    <h3>Preview da landing</h3>
                    <p class="section-subtitle">Pre-visualizacao ao vivo de como a sua landing vai aparecer para visitantes.</p>
                    @php
                        $previewMedia = collect($mediaAssets)->take(6);
                        $previewPosts = collect($posts)->take(3);
                    @endphp
                    <div class="live-preview-card" id="landing-live-preview">
                        <div class="live-preview-hero">
                            <p id="preview-slug">/{{ old('slug', $profile?->slug) ?: 'seu-slug' }}</p>
                            <h4 id="preview-headline">{{ old('headline', $profile?->headline) ?: 'Sua headline aparecera aqui' }}</h4>
                            <p id="preview-bio">{{ old('bio', $profile?->bio) ?: 'Sua bio sera exibida neste bloco.' }}</p>
                            <p id="preview-skills">{{ old('skills', $profile?->skills) ?: 'Suas habilidades serao mostradas aqui.' }}</p>
                            <div class="preview-pill-row">
                                <span class="preview-pill">Autoridade</span>
                                <span class="preview-pill">Conteudo</span>
                                <span class="preview-pill">Contato</span>
                            </div>
                            <span class="preview-cta">Agendar atendimento</span>
                        </div>
                        <div class="live-preview-media">
                            <div id="preview-image-wrap" style="display:none;"></div>
                            <div id="preview-video-wrap" style="display:none;"></div>
                        </div>
                        <div class="live-preview-body">
                            <section class="live-preview-section">
                                <h5 id="preview-service-section-title">{{ old('service_section_title', $profile?->service_section_title) ?: 'Atendimento, conteudo e video' }}</h5>
                                <div class="live-preview-posts">
                                    <article class="live-preview-post">
                                        <h6 id="preview-service-one-title">{{ old('service_one_title', $profile?->service_one_title) ?: 'Mentoria Individual' }}</h6>
                                        <p id="preview-service-one-description">{{ old('service_one_description', $profile?->service_one_description) ?: 'Acompanhamento direto para acelerar sua evolucao com um plano claro, pratico e adaptado a sua rotina.' }}</p>
                                    </article>
                                    <article class="live-preview-post">
                                        <h6 id="preview-service-two-title">{{ old('service_two_title', $profile?->service_two_title) ?: 'Posts Semanais' }}</h6>
                                        <p id="preview-service-two-description">{{ old('service_two_description', $profile?->service_two_description) ?: 'Publicacoes praticas com orientacoes, estudos de caso e direcionamentos para aplicar no dia a dia.' }}</p>
                                    </article>
                                    <article class="live-preview-post">
                                        <h6 id="preview-service-three-title">{{ old('service_three_title', $profile?->service_three_title) ?: 'Conteudos em Video' }}</h6>
                                        <p id="preview-service-three-description">{{ old('service_three_description', $profile?->service_three_description) ?: 'Videos objetivos com explicacoes diretas para transformar teoria em acao com consistencia.' }}</p>
                                    </article>
                                </div>
                            </section>

                            <section class="live-preview-section">
                                <h5>Galeria</h5>
                                <div class="live-preview-gallery">
                                    @forelse ($previewMedia as $previewItem)
                                        <article class="live-preview-gallery-item">
                                            <strong>{{ strtoupper((string) $previewItem->media_type) }}</strong>
                                            <span>{{ $previewItem->title ?: 'Midia sem titulo' }}</span>
                                        </article>
                                    @empty
                                        <article class="live-preview-gallery-item" style="grid-column: 1 / -1;">
                                            <span>Sem itens de galeria. Adicione fotos ou videos para aparecer aqui.</span>
                                        </article>
                                    @endforelse
                                </div>
                            </section>

                            <section class="live-preview-section">
                                <h5>Posts</h5>
                                <div class="live-preview-posts">
                                    @forelse ($previewPosts as $previewPost)
                                        <article class="live-preview-post">
                                            <h6>{{ $previewPost->title }}</h6>
                                            <p>{{ $previewPost->excerpt ?: 'Post sem resumo.' }}</p>
                                        </article>
                                    @empty
                                        <article class="live-preview-post">
                                            <p>Sem posts publicados ainda.</p>
                                        </article>
                                    @endforelse
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Adicionar midia</h3>
            <p class="section-subtitle">Envie um arquivo ou informe uma URL. Limites: imagem ate 3MB, video ate 30MB.</p>
            @if (!empty($isProfessional))
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 14px;">
                    <span class="media-limit-chip {{ (int) $mediaImageCount >= (int) $professionalImageLimit ? 'full' : '' }}">Fotos: {{ (int) $mediaImageCount }}/{{ (int) $professionalImageLimit }}</span>
                    <span class="media-limit-chip {{ (int) $mediaVideoCount >= (int) $professionalVideoLimit ? 'full' : '' }}">Videos: {{ (int) $mediaVideoCount }}/{{ (int) $professionalVideoLimit }}</span>
                </div>
            @endif
            <form method="POST" action="{{ route('my-landing.media.store') }}" class="form-shell js-upload-form landing-form-shell" enctype="multipart/form-data">
                @csrf
                <div class="landing-field">
                    <label class="landing-label" data-icon="TYPE">Tipo</label>
                    <select class="field-control" name="media_type" required>
                        <option value="image">Imagem</option>
                        <option value="video">Video</option>
                    </select>
                </div>
                <div class="landing-field">
                    <label class="landing-label" data-icon="URL">URL (opcional)</label>
                    <input class="field-control" name="media_url">
                </div>
                <div class="landing-field">
                    <label class="landing-label" data-icon="FILE">Arquivo (opcional)</label>
                    <input class="field-control js-file-preview" data-preview-target="media-preview" data-max-bytes="31457280" data-kind="auto" name="media_file" type="file" accept="image/*,video/*">
                </div>
                <div id="media-preview" style="display:none;margin-bottom:12px;"></div>
                <div class="landing-field"><label class="landing-label" data-icon="T">Titulo</label><input class="field-control" name="title"></div>
                <div class="landing-field"><label class="landing-label" data-icon="TXT">Descricao</label><textarea class="field-control" name="description" rows="2"></textarea></div>
                <div class="js-upload-progress" style="display:none;margin:12px 0;">
                    <div style="height:10px;border-radius:999px;background:#e5e7eb;overflow:hidden;">
                        <div class="js-upload-progress-bar" style="height:10px;width:0;background:#2563eb;transition:width .2s ease;"></div>
                    </div>
                    <small class="js-upload-progress-text" style="color:#4b5563;">Preparando upload...</small>
                </div>
                <button type="submit" class="btn btn-primary">Adicionar midia</button>
            </form>

            <hr>
            @foreach($mediaAssets as $media)
                <div class="collection-item">
                    <div>
                        <strong>{{ strtoupper($media->media_type) }} - {{ $media->title ?: 'Midia sem titulo' }}</strong>
                        <p>{{ $media->media_url ?: 'Arquivo enviado internamente' }}</p>
                    </div>
                    <div class="collection-actions">
                        <form method="POST" action="{{ route('my-landing.media.destroy', $media->id) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-soft" type="submit">Remover</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="posts-toolbar">
                <div>
                    <h3>Posts da landing</h3>
                    <p class="section-subtitle">Esses posts alimentam a secao "Posts e conteudos" da landing publica. Cada pagina mostra ate 6 posts.</p>
                </div>
                <span class="posts-summary">{{ collect($posts)->count() }} post(s) cadastrados</span>
            </div>

            <form method="POST" action="{{ route('my-landing.posts.store') }}" class="landing-form-shell">
                @csrf
                <section class="landing-form-section">
                    <h4>Novo post</h4>
                    <div class="landing-form-grid">
                        <div class="landing-field landing-field-full"><label class="landing-label" data-icon="T">Titulo do card</label><input class="field-control" name="title" required placeholder="Ex: Como organizar sua semana de treino"></div>
                        <div class="landing-field landing-field-full"><label class="landing-label" data-icon="SUM">Resumo exibido na landing</label><textarea class="field-control" name="excerpt" rows="3" placeholder="Texto curto para o card de listagem."></textarea></div>
                        <div class="landing-field landing-field-full"><label class="landing-label" data-icon="TXT">Conteudo completo</label><textarea class="field-control" name="content" rows="6" required placeholder="Escreva o conteudo completo do post."></textarea></div>
                    </div>
                    <label class="landing-check"><input type="checkbox" name="is_published" value="1"> Publicar imediatamente</label>
                    <button class="btn btn-primary" type="submit">Criar post</button>
                </section>
            </form>

            <hr>
            @foreach($posts as $post)
                <div class="post-editor-card" style="margin-bottom:18px;">
                    <div class="post-editor-head">
                        <div>
                            <h4>{{ $post->title }}</h4>
                            <div class="post-editor-meta">Slug: {{ $post->slug }}</div>
                        </div>
                        <span class="post-status-chip {{ $post->is_published ? '' : 'draft' }}">{{ $post->is_published ? 'Publicado' : 'Rascunho' }}</span>
                    </div>
                    <form method="POST" action="{{ route('my-landing.posts.update', $post->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="landing-field"><label class="landing-label" data-icon="T">Titulo do card</label><input class="field-control" name="title" value="{{ $post->title }}" required></div>
                        <div class="landing-field"><label class="landing-label" data-icon="SUM">Resumo exibido na landing</label><textarea class="field-control" name="excerpt" rows="3">{{ $post->excerpt }}</textarea></div>
                        <div class="landing-field"><label class="landing-label" data-icon="TXT">Conteudo completo</label><textarea class="field-control" name="content" rows="6" required>{{ $post->content }}</textarea></div>
                        <label class="landing-check"><input type="checkbox" name="is_published" value="1" {{ $post->is_published ? 'checked' : '' }}> Publicado</label>
                        <div style="display:flex;gap:10px;margin-top:8px;">
                            <button class="btn btn-primary" type="submit">Atualizar</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('my-landing.posts.destroy', $post->id) }}" style="margin-top:8px;">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-soft" type="submit">Excluir</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        (function () {
            const humanSize = (bytes) => {
                if (bytes < 1024) return `${bytes} B`;
                if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
                return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
            };

            const buildPreview = (target, file, kind) => {
                const url = URL.createObjectURL(file);
                const fileType = file.type || '';
                const effectiveKind = kind === 'auto'
                    ? (fileType.startsWith('video/') ? 'video' : (fileType.startsWith('image/') ? 'image' : 'unknown'))
                    : kind;

                let mediaHtml = `<p style="margin:0 0 8px;font-size:12px;color:#6b7280;">Arquivo: ${file.name} (${humanSize(file.size)})</p>`;

                if (effectiveKind === 'image') {
                    mediaHtml += `<img src="${url}" alt="Preview" style="max-width:100%;max-height:240px;border-radius:10px;border:1px solid #e5e7eb;">`;
                } else if (effectiveKind === 'video') {
                    mediaHtml += `<video src="${url}" controls style="max-width:100%;max-height:260px;border-radius:10px;border:1px solid #e5e7eb;"></video>`;
                } else {
                    mediaHtml += `<p style="margin:0;color:#b91c1c;">Formato nao suportado para preview instantaneo.</p>`;
                }

                target.style.display = 'block';
                target.innerHTML = mediaHtml;
            };

            const escapeHtml = (value) => {
                return String(value || '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            };

            const landingForm = document.getElementById('landing-config-form');
            const mediaForm = document.querySelector('form[action="{{ route('my-landing.media.store') }}"]');

            const isProfessionalUser = {{ !empty($isProfessional) ? 'true' : 'false' }};
            const mediaImageCount = {{ (int) ($mediaImageCount ?? 0) }};
            const mediaVideoCount = {{ (int) ($mediaVideoCount ?? 0) }};
            const professionalImageLimit = {{ (int) ($professionalImageLimit ?? 15) }};
            const professionalVideoLimit = {{ (int) ($professionalVideoLimit ?? 4) }};
            const previewCard = document.getElementById('landing-live-preview');

            const themePalette = {
                myhra_bordeaux: {
                    bg: '#4f0f0c',
                    surface: '#6a1912',
                    text: '#f8f3ea',
                    muted: 'rgba(248, 243, 234, 0.82)',
                    buttonBg: '#d8d0c8',
                    buttonText: '#2a2520',
                },
                sage_serene: {
                    bg: '#41554c',
                    surface: '#688b7d',
                    text: '#f2f1e9',
                    muted: 'rgba(242, 241, 233, 0.82)',
                    buttonBg: '#e2dbcd',
                    buttonText: '#253028',
                },
                graphite_noir: {
                    bg: '#17181a',
                    surface: '#313438',
                    text: '#f5f4ef',
                    muted: 'rgba(245, 244, 239, 0.82)',
                    buttonBg: '#d2cec7',
                    buttonText: '#1a1b1d',
                },
                ocean_mist: {
                    bg: '#20354a',
                    surface: '#3d607d',
                    text: '#f0f5f7',
                    muted: 'rgba(240, 245, 247, 0.84)',
                    buttonBg: '#d8e2e7',
                    buttonText: '#1f2f3d',
                },
            };

            const previewSlug = document.getElementById('preview-slug');
            const previewHeadline = document.getElementById('preview-headline');
            const previewBio = document.getElementById('preview-bio');
            const previewSkills = document.getElementById('preview-skills');
            const previewServiceSectionTitle = document.getElementById('preview-service-section-title');
            const previewServiceOneTitle = document.getElementById('preview-service-one-title');
            const previewServiceOneDescription = document.getElementById('preview-service-one-description');
            const previewServiceTwoTitle = document.getElementById('preview-service-two-title');
            const previewServiceTwoDescription = document.getElementById('preview-service-two-description');
            const previewServiceThreeTitle = document.getElementById('preview-service-three-title');
            const previewServiceThreeDescription = document.getElementById('preview-service-three-description');
            const previewImageWrap = document.getElementById('preview-image-wrap');
            const previewVideoWrap = document.getElementById('preview-video-wrap');

            const updateLandingPreview = () => {
                if (!landingForm) return;

                const slugInput = landingForm.querySelector('input[name="slug"]');
                const headlineInput = landingForm.querySelector('input[name="headline"]');
                const bioInput = landingForm.querySelector('textarea[name="bio"]');
                const skillsInput = landingForm.querySelector('textarea[name="skills"]');
                const serviceSectionTitleInput = landingForm.querySelector('input[name="service_section_title"]');
                const serviceOneTitleInput = landingForm.querySelector('input[name="service_one_title"]');
                const serviceOneDescriptionInput = landingForm.querySelector('textarea[name="service_one_description"]');
                const serviceTwoTitleInput = landingForm.querySelector('input[name="service_two_title"]');
                const serviceTwoDescriptionInput = landingForm.querySelector('textarea[name="service_two_description"]');
                const serviceThreeTitleInput = landingForm.querySelector('input[name="service_three_title"]');
                const serviceThreeDescriptionInput = landingForm.querySelector('textarea[name="service_three_description"]');
                const heroImageUrlInput = landingForm.querySelector('input[name="hero_image_url"]');
                const heroVideoUrlInput = landingForm.querySelector('input[name="hero_video_url"]');
                const heroImageFileInput = landingForm.querySelector('input[name="hero_image_file"]');
                const heroVideoFileInput = landingForm.querySelector('input[name="hero_video_file"]');
                const themeSelect = landingForm.querySelector('select[name="theme_preset"]');

                const slugValue = slugInput ? slugInput.value.trim() : '';
                const headlineValue = headlineInput ? headlineInput.value.trim() : '';
                const bioValue = bioInput ? bioInput.value.trim() : '';
                const skillsValue = skillsInput ? skillsInput.value.trim() : '';
                const serviceSectionTitleValue = serviceSectionTitleInput ? serviceSectionTitleInput.value.trim() : '';
                const serviceOneTitleValue = serviceOneTitleInput ? serviceOneTitleInput.value.trim() : '';
                const serviceOneDescriptionValue = serviceOneDescriptionInput ? serviceOneDescriptionInput.value.trim() : '';
                const serviceTwoTitleValue = serviceTwoTitleInput ? serviceTwoTitleInput.value.trim() : '';
                const serviceTwoDescriptionValue = serviceTwoDescriptionInput ? serviceTwoDescriptionInput.value.trim() : '';
                const serviceThreeTitleValue = serviceThreeTitleInput ? serviceThreeTitleInput.value.trim() : '';
                const serviceThreeDescriptionValue = serviceThreeDescriptionInput ? serviceThreeDescriptionInput.value.trim() : '';
                const themeValue = themeSelect ? themeSelect.value : 'myhra_bordeaux';
                const activeTheme = themePalette[themeValue] || themePalette.myhra_bordeaux;

                if (previewSlug) previewSlug.textContent = '/' + (slugValue || 'seu-slug');
                if (previewHeadline) previewHeadline.textContent = headlineValue || 'Sua headline aparecera aqui';
                if (previewBio) previewBio.textContent = bioValue || 'Sua bio sera exibida neste bloco.';
                if (previewSkills) previewSkills.textContent = skillsValue || 'Suas habilidades serao mostradas aqui.';
                if (previewServiceSectionTitle) previewServiceSectionTitle.textContent = serviceSectionTitleValue || 'Atendimento, conteudo e video';
                if (previewServiceOneTitle) previewServiceOneTitle.textContent = serviceOneTitleValue || 'Mentoria Individual';
                if (previewServiceOneDescription) previewServiceOneDescription.textContent = serviceOneDescriptionValue || 'Acompanhamento direto para acelerar sua evolucao com um plano claro, pratico e adaptado a sua rotina.';
                if (previewServiceTwoTitle) previewServiceTwoTitle.textContent = serviceTwoTitleValue || 'Posts Semanais';
                if (previewServiceTwoDescription) previewServiceTwoDescription.textContent = serviceTwoDescriptionValue || 'Publicacoes praticas com orientacoes, estudos de caso e direcionamentos para aplicar no dia a dia.';
                if (previewServiceThreeTitle) previewServiceThreeTitle.textContent = serviceThreeTitleValue || 'Conteudos em Video';
                if (previewServiceThreeDescription) previewServiceThreeDescription.textContent = serviceThreeDescriptionValue || 'Videos objetivos com explicacoes diretas para transformar teoria em acao com consistencia.';

                if (previewCard) {
                    previewCard.style.setProperty('--preview-bg', activeTheme.bg);
                    previewCard.style.setProperty('--preview-surface', activeTheme.surface);
                    previewCard.style.setProperty('--preview-text', activeTheme.text);
                    previewCard.style.setProperty('--preview-muted', activeTheme.muted);
                    previewCard.style.setProperty('--preview-button-bg', activeTheme.buttonBg);
                    previewCard.style.setProperty('--preview-button-text', activeTheme.buttonText);
                }

                const heroImageFile = heroImageFileInput && heroImageFileInput.files ? heroImageFileInput.files[0] : null;
                const heroVideoFile = heroVideoFileInput && heroVideoFileInput.files ? heroVideoFileInput.files[0] : null;

                const heroImageUrl = heroImageFile ? URL.createObjectURL(heroImageFile) : (heroImageUrlInput ? heroImageUrlInput.value.trim() : '');
                const heroVideoUrl = heroVideoFile ? URL.createObjectURL(heroVideoFile) : (heroVideoUrlInput ? heroVideoUrlInput.value.trim() : '');

                if (previewImageWrap) {
                    if (heroImageUrl !== '') {
                        previewImageWrap.style.display = 'block';
                        previewImageWrap.innerHTML = `<img src="${escapeHtml(heroImageUrl)}" alt="Preview imagem principal">`;
                    } else {
                        previewImageWrap.style.display = 'none';
                        previewImageWrap.innerHTML = '';
                    }
                }

                if (previewVideoWrap) {
                    if (heroVideoUrl !== '') {
                        previewVideoWrap.style.display = 'block';
                        previewVideoWrap.innerHTML = `<video src="${escapeHtml(heroVideoUrl)}" controls preload="metadata"></video>`;
                    } else {
                        previewVideoWrap.style.display = 'none';
                        previewVideoWrap.innerHTML = '';
                    }
                }
            };

            const refreshMediaSubmitState = () => {
                if (!mediaForm || !isProfessionalUser) return;

                const typeInput = mediaForm.querySelector('select[name="media_type"]');
                const submitButton = mediaForm.querySelector('button[type="submit"]');
                if (!typeInput || !submitButton) return;

                let isLimitReached = false;

                if (typeInput.value === 'image') {
                    isLimitReached = mediaImageCount >= professionalImageLimit;
                }

                if (typeInput.value === 'video') {
                    isLimitReached = mediaVideoCount >= professionalVideoLimit;
                }

                submitButton.disabled = isLimitReached;
                submitButton.title = isLimitReached ? 'Limite dessa midia atingido para profissional.' : '';
            };

            const submitWithProgress = (form) => {
                const progressWrap = form.querySelector('.js-upload-progress');
                const progressBar = form.querySelector('.js-upload-progress-bar');
                const progressText = form.querySelector('.js-upload-progress-text');

                const hasFile = Array.from(form.querySelectorAll('input[type="file"]')).some((input) => input.files && input.files.length > 0);

                if (!hasFile) {
                    form.submit();
                    return;
                }

                const formData = new FormData(form);
                const xhr = new XMLHttpRequest();
                xhr.open((form.method || 'POST').toUpperCase(), form.action, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                if (progressWrap && progressBar && progressText) {
                    progressWrap.style.display = 'block';
                }

                xhr.upload.onprogress = (event) => {
                    if (!event.lengthComputable || !progressBar || !progressText) return;
                    const percent = Math.min(100, Math.round((event.loaded / event.total) * 100));
                    progressBar.style.width = `${percent}%`;
                    progressText.textContent = `Upload em andamento... ${percent}%`;
                };

                xhr.onload = () => {
                    if (progressBar && progressText) {
                        progressBar.style.width = '100%';
                        progressText.textContent = 'Processando resposta...';
                    }

                    if (xhr.status >= 200 && xhr.status < 500) {
                        window.location.href = xhr.responseURL || window.location.href;
                        return;
                    }

                    alert('Falha no upload. Tente novamente.');
                };

                xhr.onerror = () => alert('Erro de rede durante o upload.');
                xhr.send(formData);
            };

            document.querySelectorAll('.js-upload-form').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    submitWithProgress(form);
                });
            });

            document.querySelectorAll('.js-file-preview').forEach((input) => {
                input.addEventListener('change', (event) => {
                    const fileInput = event.currentTarget;
                    const file = fileInput.files && fileInput.files[0];
                    const maxBytes = Number(fileInput.dataset.maxBytes || 0);
                    const targetId = fileInput.dataset.previewTarget;
                    const kind = fileInput.dataset.kind || 'auto';
                    const target = targetId ? document.getElementById(targetId) : null;

                    if (!target) return;

                    if (!file) {
                        target.style.display = 'none';
                        target.innerHTML = '';
                        return;
                    }

                    if (maxBytes > 0 && file.size > maxBytes) {
                        alert(`Arquivo excede o limite permitido (${humanSize(maxBytes)}).`);
                        fileInput.value = '';
                        target.style.display = 'none';
                        target.innerHTML = '';
                        return;
                    }

                    buildPreview(target, file, kind);
                    updateLandingPreview();
                });
            });

            if (landingForm) {
                landingForm.querySelectorAll('input, textarea').forEach((field) => {
                    field.addEventListener('input', updateLandingPreview);
                    field.addEventListener('change', updateLandingPreview);
                });
            }

            if (mediaForm) {
                const typeInput = mediaForm.querySelector('select[name="media_type"]');
                if (typeInput) {
                    typeInput.addEventListener('change', refreshMediaSubmitState);
                }
            }

            updateLandingPreview();
            refreshMediaSubmitState();
        })();
    </script>
@endsection
