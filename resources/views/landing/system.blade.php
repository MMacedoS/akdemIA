<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AkdemIA | Sistema SaaS para gestao de treinos</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --bg: #f4efe7;
            --surface: rgba(255, 255, 255, 0.76);
            --text: #1f2430;
            --muted: #5b6270;
            --brand: #0f6eaf;
            --brand-deep: #0f3559;
            --accent: #e58e3a;
            --success: #2c8c68;
            --shadow: 0 24px 60px rgba(23, 31, 46, 0.12);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(15, 110, 175, 0.18), transparent 26%),
                radial-gradient(circle at top right, rgba(229, 142, 58, 0.14), transparent 22%),
                linear-gradient(180deg, #faf6ef 0%, var(--bg) 100%);
        }
        a { color: inherit; }
        img { max-width: 100%; display: block; }
        .container { width: min(1180px, calc(100% - 32px)); margin: 0 auto; }
        .topbar { padding: 22px 0 10px; }
        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: rgba(255, 255, 255, 0.58);
            backdrop-filter: blur(18px);
            border-radius: 22px;
            padding: 14px 18px;
            box-shadow: 0 10px 35px rgba(35, 41, 55, 0.08);
        }
        .brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-deep) 100%);
        }
        .brand-copy strong {
            display: block;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 18px;
            line-height: 1;
        }
        .brand-copy span { font-size: 13px; color: var(--muted); }
        .nav { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
        .nav a { text-decoration: none; color: #3f4654; font-size: 14px; font-weight: 700; }
        .hero { padding: 22px 0 48px; }
        .hero-panel {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(320px, 0.95fr);
            gap: 26px;
            padding: 28px;
            border-radius: 34px;
            background: linear-gradient(130deg, rgba(15, 53, 89, 0.98) 0%, rgba(15, 110, 175, 0.9) 55%, rgba(229, 142, 58, 0.82) 100%);
            color: #fff;
            box-shadow: 0 30px 70px rgba(16, 33, 56, 0.22);
        }
        .eyebrow {
            display: inline-flex;
            width: fit-content;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 18px;
        }
        .hero h1 {
            margin: 0;
            max-width: 12ch;
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(40px, 7vw, 68px);
            line-height: 0.98;
            letter-spacing: -0.04em;
        }
        .hero p { margin: 18px 0 0; max-width: 620px; font-size: 18px; line-height: 1.7; color: rgba(255, 255, 255, 0.9); }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 26px; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 50px;
            padding: 0 20px;
            border-radius: 16px;
            border: 1px solid transparent;
            text-decoration: none;
            font-weight: 800;
        }
        .button-primary { color: var(--brand-deep); background: linear-gradient(120deg, #ffffff 0%, #f7efe1 100%); }
        .button-secondary { color: #fff; background: rgba(255, 255, 255, 0.08); border-color: rgba(255, 255, 255, 0.28); }
        .hero-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 24px; }
        .metric { padding: 16px; border-radius: 18px; background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.16); }
        .metric strong { display: block; font-size: 26px; line-height: 1; margin-bottom: 8px; }
        .metric span { display: block; font-size: 13px; line-height: 1.5; color: rgba(255, 255, 255, 0.84); }
        .hero-image-card {
            min-height: 420px;
            border-radius: 28px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .hero-image-card img { width: 100%; height: 100%; object-fit: cover; }
        .hero-placeholder {
            height: 100%;
            min-height: 420px;
            display: grid;
            align-content: end;
            gap: 16px;
            padding: 26px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.02) 0%, rgba(7, 15, 27, 0.56) 100%), linear-gradient(145deg, rgba(244, 245, 255, 0.22) 0%, rgba(255, 255, 255, 0.08) 100%);
        }
        .hero-placeholder-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .hero-placeholder-card, .hero-placeholder-note {
            padding: 16px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .hero-placeholder-card strong, .hero-placeholder-note strong { display: block; margin-bottom: 8px; font-size: 15px; }
        .hero-placeholder-card span, .hero-placeholder-note span { display: block; font-size: 13px; line-height: 1.5; color: rgba(255, 255, 255, 0.84); }
        .section { padding: 34px 0; }
        .section-head { max-width: 720px; margin-bottom: 22px; }
        .section-kicker {
            display: inline-block;
            margin-bottom: 10px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--brand);
        }
        .section-head h2 { margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: clamp(30px, 5vw, 48px); line-height: 1; letter-spacing: -0.04em; }
        .section-head p { margin: 14px 0 0; font-size: 17px; line-height: 1.75; color: var(--muted); }
        .about-grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.9fr); gap: 18px; }
        .glass-card, .feature-card, .spotlight-card, .contact-card, .contact-form-card {
            background: var(--surface);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 28px;
            box-shadow: var(--shadow);
        }
        .glass-card, .feature-card, .spotlight-card, .contact-card, .contact-form-card { padding: 26px; }
        .benefit-list, .services-grid, .differentials-grid, .showcase-grid { display: grid; gap: 18px; }
        .benefit-list { grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 20px; }
        .benefit-pill { padding: 16px; border-radius: 20px; background: rgba(15, 110, 175, 0.08); border: 1px solid rgba(15, 110, 175, 0.12); }
        .benefit-pill strong { display: block; margin-bottom: 6px; font-size: 15px; }
        .benefit-pill span { display: block; font-size: 14px; line-height: 1.6; color: var(--muted); }
        .about-stack { display: grid; gap: 14px; }
        .about-stat { padding: 22px; border-radius: 24px; color: #fff; background: linear-gradient(135deg, var(--brand-deep) 0%, var(--brand) 100%); }
        .about-stat strong { display: block; font-size: 38px; line-height: 1; margin-bottom: 8px; }
        .about-stat p { margin: 0; color: rgba(255, 255, 255, 0.86); line-height: 1.6; }
        .services-grid, .differentials-grid, .showcase-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .feature-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            height: 48px;
            border-radius: 16px;
            font-size: 24px;
            margin-bottom: 18px;
            background: rgba(15, 110, 175, 0.1);
        }
        .feature-card h3, .spotlight-card h3 { margin: 0 0 10px; font-size: 22px; }
        .feature-card p, .spotlight-card p { margin: 0 0 16px; color: var(--muted); line-height: 1.7; }
        .feature-list, .contact-list { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
        .feature-list li, .contact-list li { display: flex; gap: 10px; align-items: flex-start; font-size: 14px; line-height: 1.65; color: #334055; }
        .feature-list li::before, .contact-list li::before {
            content: '';
            width: 9px;
            height: 9px;
            margin-top: 8px;
            border-radius: 999px;
            flex: 0 0 auto;
            background: linear-gradient(135deg, var(--brand) 0%, var(--accent) 100%);
        }
        .differential-card {
            padding: 22px;
            border-radius: 26px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.9) 0%, rgba(247, 243, 236, 0.88) 100%);
            border: 1px solid rgba(31, 36, 48, 0.08);
            box-shadow: 0 18px 44px rgba(17, 26, 40, 0.08);
        }
        .differential-card strong { display: block; font-size: 18px; margin-bottom: 8px; }
        .differential-card p { margin: 0; color: var(--muted); line-height: 1.7; }
        .showcase-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin: 30px 0 16px; }
        .showcase-heading h3 { margin: 0; font-size: 24px; }
        .spotlight-card small { display: inline-block; font-size: 12px; color: var(--brand); font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 12px; }
        .slug { margin-top: 14px; font-size: 13px; color: var(--brand-deep); font-weight: 800; word-break: break-word; }
        .contact-grid { display: grid; grid-template-columns: minmax(0, 0.88fr) minmax(0, 1.12fr); gap: 18px; }
        .contact-links { display: grid; gap: 12px; margin-top: 18px; }
        .contact-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            border-radius: 18px;
            background: rgba(15, 110, 175, 0.06);
            border: 1px solid rgba(15, 110, 175, 0.1);
            text-decoration: none;
        }
        .contact-link strong { display: block; margin-bottom: 4px; font-size: 14px; }
        .contact-link span { color: var(--muted); font-size: 14px; word-break: break-word; }
        .contact-form { display: grid; gap: 14px; }
        .field { display: grid; gap: 8px; }
        .field label { font-size: 14px; font-weight: 700; color: #374050; }
        .field input, .field textarea {
            width: 100%;
            border: 1px solid rgba(31, 36, 48, 0.12);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.85);
            padding: 14px 16px;
            font: inherit;
            color: var(--text);
        }
        .field textarea { min-height: 156px; resize: vertical; }
        .contact-note { margin: 4px 0 0; font-size: 13px; line-height: 1.6; color: var(--muted); }
        .footer { padding: 0 0 44px; }
        .footer-panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 22px 24px;
            border-radius: 24px;
            background: rgba(15, 53, 89, 0.96);
            color: rgba(255, 255, 255, 0.88);
        }
        .footer-panel strong { display: block; color: #fff; margin-bottom: 4px; }
        @media (max-width: 980px) {
            .hero-panel, .about-grid, .contact-grid, .services-grid, .differentials-grid, .showcase-grid { grid-template-columns: 1fr; }
            .hero-metrics, .benefit-list { grid-template-columns: 1fr; }
        }
        @media (max-width: 720px) {
            .topbar-inner, .showcase-heading, .footer-panel { flex-direction: column; align-items: flex-start; }
            .hero-placeholder-grid { grid-template-columns: 1fr; }
            .button { width: 100%; }
            .contact-link { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
    @php
        $heroTitle = $setting?->hero_title ?: 'Sistema SaaS para academias, trainers e alunos evoluirem juntos.';
        $heroDescription = $setting?->hero_description ?: 'Centralize operacao, prescricao de treinos, comunicacao e acompanhamento de resultados em uma experiencia moderna, multi-tenant e pronta para escalar.';
        $heroImageUrl = $setting?->hero_image_url;
        $primaryCtaText = $setting?->primary_cta_text ?: 'Comecar agora';
        $primaryCtaUrl = $setting?->primary_cta_url ?: route('register');
        $secondaryCtaText = $setting?->secondary_cta_text ?: 'Ver como funciona';
        $secondaryCtaUrl = $setting?->secondary_cta_url ?: '#sobre';
        $aboutTitle = $setting?->about_title ?: 'Uma plataforma unica para simplificar a gestao de treinos e acelerar a operacao da academia.';
        $aboutContent = $setting?->about_content ?: 'A AkdemIA organiza a jornada completa entre academia, treinadores e alunos. Em um unico ambiente, a equipe acompanha matriculas, cria fichas, atualiza treinos, entrega instrucoes e monitora a evolucao dos alunos com mais clareza, agilidade e padrao operacional.';
        $tenantsSectionTitle = $setting?->tenants_section_title ?: 'Contratantes em destaque';
        $professionalsSectionTitle = $setting?->professionals_section_title ?: 'Profissionais e trainees';
        $differentialsSectionTitle = $setting?->differentials_section_title ?: 'Por que a AkdemIA cresce como produto SaaS';
        $contactSectionTitle = $setting?->contact_section_title ?: 'Vamos conversar sobre sua operacao';
        $contactDescription = $setting?->contact_description ?: 'Envie sua mensagem para conhecer a plataforma, entender o modelo SaaS e avaliar como a AkdemIA pode apoiar a sua academia ou equipe tecnica.';
        $contactEmail = $setting?->contact_email ?: config('mail.from.address');
        $contactWhatsapp = $setting?->contact_whatsapp;
        $contactWhatsappDigits = $contactWhatsapp ? preg_replace('/\D+/', '', $contactWhatsapp) : null;
        $rootDomain = env('APP_LANDING_ROOT_DOMAIN', parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'academai.com.br');
    @endphp

    <header class="topbar">
        <div class="container">
            <div class="topbar-inner">
                <a class="brand" href="#inicio">
                    <span class="brand-mark">A</span>
                    <span class="brand-copy">
                        <strong>AkdemIA</strong>
                        <span>Gestao de treinos em modelo SaaS</span>
                    </span>
                </a>
                <nav class="nav" aria-label="Navegacao principal">
                    <a href="#sobre">Sobre</a>
                    <a href="#servicos">Funcionalidades</a>
                    <a href="#diferenciais">Diferenciais</a>
                    <a href="#contato">Contato</a>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="hero" id="inicio">
            <div class="container">
                <div class="hero-panel">
                    <div>
                        <span class="eyebrow">Plataforma para performance, recorrencia e escala</span>
                        <h1>{{ $heroTitle }}</h1>
                        <p>{{ $heroDescription }}</p>

                        <div class="hero-actions">
                            <a href="{{ $primaryCtaUrl }}" class="button button-primary">{{ $primaryCtaText }}</a>
                            <a href="{{ $secondaryCtaUrl }}" class="button button-secondary">{{ $secondaryCtaText }}</a>
                        </div>

                        <div class="hero-metrics">
                            <div class="metric">
                                <strong>{{ $featuredTenants->count() }}</strong>
                                <span>contratantes ativos destacados na vitrine publica</span>
                            </div>
                            <div class="metric">
                                <strong>{{ $featuredProfessionals->count() }}</strong>
                                <span>profissionais com perfil publicado para captacao</span>
                            </div>
                            <div class="metric">
                                <strong>1 hub</strong>
                                <span>para centralizar treinos, relatorios e comunicacao</span>
                            </div>
                        </div>
                    </div>

                    <div class="hero-image-card">
                        @if($heroImageUrl)
                            <img src="{{ $heroImageUrl }}" alt="Painel da plataforma AkdemIA em destaque">
                        @else
                            <div class="hero-placeholder">
                                <div class="hero-placeholder-grid">
                                    <div class="hero-placeholder-card">
                                        <strong>Academia</strong>
                                        <span>Controle alunos, equipe tecnica, indicadores e operacao em uma camada unica.</span>
                                    </div>
                                    <div class="hero-placeholder-card">
                                        <strong>Trainer</strong>
                                        <span>Prescreva, acompanhe e adapte treinos com mais contexto e menos atrito.</span>
                                    </div>
                                </div>
                                <div class="hero-placeholder-note">
                                    <strong>Aluno no centro da experiencia</strong>
                                    <span>Receba treinos atualizados, veja execucao de exercicios e acompanhe evolucao com mais clareza.</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="sobre">
            <div class="container">
                <div class="section-head">
                    <span class="section-kicker">Sobre a plataforma</span>
                    <h2>{{ $aboutTitle }}</h2>
                    <p>{!! nl2br(e($aboutContent)) !!}</p>
                </div>

                <div class="about-grid">
                    <div class="glass-card">
                        <div class="benefit-list">
                            <div class="benefit-pill">
                                <strong>Simplicidade operacional</strong>
                                <span>Menos planilhas soltas e mais padrao para equipe, processos e entregas.</span>
                            </div>
                            <div class="benefit-pill">
                                <strong>Centralizacao real</strong>
                                <span>Treinos, acompanhamento e comunicacao reunidos em um unico fluxo.</span>
                            </div>
                            <div class="benefit-pill">
                                <strong>Beneficio para todo o ecossistema</strong>
                                <span>Academias, trainers e alunos trabalham com visibilidade compartilhada.</span>
                            </div>
                        </div>
                    </div>

                    <div class="about-stack">
                        <div class="about-stat">
                            <strong>SaaS multi-tenant</strong>
                            <p>Cada operacao pode crescer com identidade propria, sem perder padrao tecnico, seguranca e previsibilidade.</p>
                        </div>
                        <div class="glass-card">
                            <strong style="display:block; margin-bottom:8px; font-size:18px;">Pensado para conversao e retencao</strong>
                            <p style="margin:0; color:var(--muted); line-height:1.7;">A landing publica, os perfis e o ambiente operacional trabalham juntos para transformar interesse em relacionamento continuo.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="servicos">
            <div class="container">
                <div class="section-head">
                    <span class="section-kicker">Servicos e funcionalidades</span>
                    <h2>Uma experiencia completa para quem gere, prescreve e executa o treino.</h2>
                    <p>O produto foi desenhado para atender toda a cadeia da academia com fluxos dedicados por perfil e uma base unica de informacoes.</p>
                </div>

                <div class="services-grid">
                    <article class="feature-card">
                        <span class="feature-icon">🏢</span>
                        <h3>Contratante</h3>
                        <p>Controle a academia com mais previsibilidade operacional e melhor visao do negocio.</p>
                        <ul class="feature-list">
                            <li>Gerenciamento de alunos</li>
                            <li>Gerenciamento de treinadores</li>
                            <li>Relatorios e metricas</li>
                            <li>Controle de planos futuramente</li>
                            <li>Gestao completa da academia futuramente</li>
                        </ul>
                    </article>

                    <article class="feature-card">
                        <span class="feature-icon">🧑‍🏫</span>
                        <h3>Trainer</h3>
                        <p>Prescreva com mais agilidade e acompanhe a evolucao do aluno com base em dados e historico.</p>
                        <ul class="feature-list">
                            <li>Criar treinos personalizados</li>
                            <li>Atualizar fichas de treino</li>
                            <li>Acompanhar evolucao dos alunos</li>
                            <li>Comunicacao com alunos</li>
                            <li>Integracao com biblioteca de exercicios como WorkoutX</li>
                        </ul>
                    </article>

                    <article class="feature-card">
                        <span class="feature-icon">🧑‍🎓</span>
                        <h3>Aluno</h3>
                        <p>Tenha clareza sobre o que fazer, como evoluir e onde consultar suas orientacoes.</p>
                        <ul class="feature-list">
                            <li>Visualizar treinos</li>
                            <li>Acompanhar progresso</li>
                            <li>Ver execucao de exercicios com imagem ou GIF</li>
                            <li>Receber instrucoes do treinador</li>
                        </ul>
                    </article>
                </div>

                <div class="showcase-heading">
                    <h3>{{ $tenantsSectionTitle }}</h3>
                    <span style="color: var(--muted); font-size: 14px;">Vitrines publicas ativas dentro do ecossistema</span>
                </div>

                <div class="showcase-grid">
                    @forelse($featuredTenants as $tenant)
                        <article class="spotlight-card">
                            <small>Academia</small>
                            <h3>{{ $tenant->name }}</h3>
                            <p>Landing institucional pronta para apresentar servicos, captar interesse e ampliar presenca digital.</p>
                            <div class="slug">{{ $tenant->slug }}.{{ $rootDomain }}</div>
                        </article>
                    @empty
                        <article class="spotlight-card">
                            <small>Academia</small>
                            <h3>Sem destaques no momento</h3>
                            <p>Assim que novos contratantes forem publicados, eles aparecerao nesta vitrine.</p>
                        </article>
                    @endforelse
                </div>

                <div class="showcase-heading">
                    <h3>{{ $professionalsSectionTitle }}</h3>
                    <span style="color: var(--muted); font-size: 14px;">Perfis publicos para autoridade e captacao</span>
                </div>

                <div class="showcase-grid">
                    @forelse($featuredProfessionals as $profile)
                        <article class="spotlight-card">
                            <small>Profissional</small>
                            <h3>{{ $profile->user->name }}</h3>
                            <p>{{ $profile->headline ?: 'Perfil publico ativo com foco em credibilidade e apresentacao profissional.' }}</p>
                            <div class="slug">{{ $profile->slug }}.{{ $rootDomain }}</div>
                        </article>
                    @empty
                        <article class="spotlight-card">
                            <small>Profissional</small>
                            <h3>Nenhum perfil em destaque</h3>
                            <p>Quando novos perfis publicados estiverem disponiveis, eles aparecerao aqui.</p>
                        </article>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="section" id="diferenciais">
            <div class="container">
                <div class="section-head">
                    <span class="section-kicker">Diferenciais</span>
                    <h2>{{ $differentialsSectionTitle }}</h2>
                    <p>O produto combina base tecnica escalavel com uma experiencia pensada para adocao rapida e expansao comercial.</p>
                </div>

                <div class="differentials-grid">
                    <div class="differential-card">
                        <strong>Sistema SaaS multi-tenant</strong>
                        <p>Cada operacao roda com identidade propria, isolamento de contexto e potencial de crescimento por tenant.</p>
                    </div>
                    <div class="differential-card">
                        <strong>Interface moderna</strong>
                        <p>Fluxos desenhados para reduzir friccao, acelerar acao e facilitar a leitura das informacoes chave.</p>
                    </div>
                    <div class="differential-card">
                        <strong>Acesso web e mobile</strong>
                        <p>Experiencia fluida para consulta, acompanhamento e operacao em diferentes momentos da rotina.</p>
                    </div>
                    <div class="differential-card">
                        <strong>Escalabilidade operacional</strong>
                        <p>Estrutura pronta para crescer com novas academias, times tecnicos, alunos e modulos comerciais.</p>
                    </div>
                    <div class="differential-card">
                        <strong>Integracao com APIs de exercicios</strong>
                        <p>Capacidade de enriquecer a experiencia com biblioteca de exercicios, imagem, GIF e contexto tecnico.</p>
                    </div>
                    <div class="differential-card">
                        <strong>Base para evolucao continua</strong>
                        <p>O produto ja nasce preparado para expandir planos, metricas, automacoes e novos canais de relacionamento.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="contato">
            <div class="container">
                <div class="section-head">
                    <span class="section-kicker">Contato</span>
                    <h2>{{ $contactSectionTitle }}</h2>
                    <p>{!! nl2br(e($contactDescription)) !!}</p>
                </div>

                <div class="contact-grid">
                    <aside class="contact-card">
                        <h3 style="margin:0 0 12px; font-size:24px;">Canais diretos</h3>
                        <p style="margin:0; color:var(--muted); line-height:1.7;">Use o formulario ao lado para enviar uma mensagem estruturada ou escolha um dos canais abaixo.</p>

                        <div class="contact-links">
                            @if($contactEmail)
                                <a class="contact-link" href="mailto:{{ $contactEmail }}">
                                    <div>
                                        <strong>Email</strong>
                                        <span>{{ $contactEmail }}</span>
                                    </div>
                                    <span style="font-weight:800; color:var(--brand-deep);">Abrir</span>
                                </a>
                            @endif

                            @if($contactWhatsapp)
                                <a class="contact-link" href="https://wa.me/{{ $contactWhatsappDigits }}" target="_blank" rel="noopener">
                                    <div>
                                        <strong>WhatsApp</strong>
                                        <span>{{ $contactWhatsapp }}</span>
                                    </div>
                                    <span style="font-weight:800; color:var(--success);">Conversar</span>
                                </a>
                            @endif
                        </div>

                        <ul class="contact-list" style="margin-top:18px;">
                            <li>Resposta comercial orientada para implantacao, operacao e aderencia ao modelo SaaS.</li>
                            <li>Conversa inicial voltada a academias, equipes tecnicas e parcerias de crescimento.</li>
                        </ul>
                    </aside>

                    <div class="contact-form-card">
                        <form class="contact-form" id="landing-contact-form" data-contact-email="{{ $contactEmail }}" data-contact-whatsapp="{{ $contactWhatsappDigits }}">
                            <div class="field">
                                <label for="contact_name">Nome</label>
                                <input id="contact_name" name="name" type="text" maxlength="120" placeholder="Seu nome">
                            </div>

                            <div class="field">
                                <label for="contact_email_input">Email</label>
                                <input id="contact_email_input" name="email" type="email" maxlength="190" placeholder="voce@empresa.com">
                            </div>

                            <div class="field">
                                <label for="contact_message">Mensagem</label>
                                <textarea id="contact_message" name="message" placeholder="Conte um pouco sobre sua operacao e o que voce busca resolver."></textarea>
                            </div>

                            <button type="submit" class="button button-primary">Entrar em contato</button>
                            <p class="contact-note" id="contact-form-note">Ao enviar, a mensagem sera preparada no canal de contato configurado para esta landing.</p>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-panel">
                <div>
                    <strong>AkdemIA</strong>
                    <span>Plataforma para academias, trainers e alunos operarem em sintonia.</span>
                </div>
                <a href="{{ $primaryCtaUrl }}" class="button button-primary">{{ $primaryCtaText }}</a>
            </div>
        </div>
    </footer>

    <script>
        (function () {
            const form = document.getElementById('landing-contact-form');
            const note = document.getElementById('contact-form-note');

            if (!form || !note) {
                return;
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();

                const formData = new FormData(form);
                const name = String(formData.get('name') || '').trim();
                const email = String(formData.get('email') || '').trim();
                const message = String(formData.get('message') || '').trim();
                const whatsapp = form.dataset.contactWhatsapp || '';
                const contactEmail = form.dataset.contactEmail || '';

                if (!name || !email || !message) {
                    note.textContent = 'Preencha nome, email e mensagem para continuar.';
                    return;
                }

                const composedMessage = [
                    'Novo contato via landing AkdemIA',
                    '',
                    'Nome: ' + name,
                    'Email: ' + email,
                    '',
                    'Mensagem:',
                    message,
                ].join('\n');

                if (whatsapp) {
                    window.open('https://wa.me/' + whatsapp + '?text=' + encodeURIComponent(composedMessage), '_blank', 'noopener');
                    note.textContent = 'Mensagem preparada no WhatsApp.';
                    return;
                }

                if (contactEmail) {
                    window.location.href = 'mailto:' + contactEmail + '?subject=' + encodeURIComponent('Contato via landing AkdemIA') + '&body=' + encodeURIComponent(composedMessage);
                    note.textContent = 'Mensagem preparada no seu aplicativo de email.';
                    return;
                }

                note.textContent = 'Nenhum canal de contato esta configurado no momento.';
            });
        })();
    </script>
</body>
</html>
