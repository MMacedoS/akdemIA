<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $landing?->title ?: $tenant->name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Manrope:wght@400;500;600;700;800&display=swap');

        :root {
            --bg: #f4f6f8;
            --surface: #ffffff;
            --surface-soft: #eef4f8;
            --text: #1c2430;
            --muted: #64748b;
            --line: rgba(28, 36, 48, 0.1);
            --primary: #0f6eaf;
            --secondary: #13a9bf;
            --shadow: 0 18px 46px rgba(15, 35, 60, 0.12);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: 'Manrope', 'Segoe UI', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top right, rgba(19, 169, 191, 0.15), transparent 20%),
                radial-gradient(circle at top left, rgba(15, 110, 175, 0.14), transparent 22%),
                linear-gradient(180deg, #f8fafc 0%, var(--bg) 100%);
        }

        a { color: inherit; }
        img { max-width: 100%; display: block; }
        .container { width: min(1120px, calc(100% - 32px)); margin: 0 auto; }
        .topbar { padding: 22px 0; }
        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.78);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: 20px;
            box-shadow: 0 10px 32px rgba(23, 34, 52, 0.08);
        }

        .brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .brand-mark {
            width: 46px;
            height: 46px;
            border-radius: 15px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            font-size: 18px;
            font-weight: 800;
        }

        .brand-copy strong {
            display: block;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 18px;
        }

        .brand-copy span { display: block; font-size: 13px; color: var(--muted); }
        .nav { display: flex; gap: 18px; flex-wrap: wrap; }
        .nav a { text-decoration: none; color: #334155; font-size: 14px; font-weight: 700; }
        .hero { padding: 18px 0 42px; }
        .hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.02fr) minmax(300px, 0.98fr);
            gap: 20px;
            align-items: stretch;
        }

        .hero-card,
        .section-card,
        .instagram-card,
        .contact-card,
        .professional-card,
        .service-card {
            border: 1px solid rgba(255, 255, 255, 0.65);
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(16px);
            box-shadow: var(--shadow);
        }

        .hero-copy {
            padding: 28px;
            display: grid;
            align-content: center;
            gap: 16px;
            background: linear-gradient(135deg, rgba(15, 110, 175, 0.96) 0%, rgba(19, 169, 191, 0.88) 100%);
            color: #fff;
        }

        .eyebrow {
            display: inline-flex;
            width: fit-content;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .hero-copy h1 {
            margin: 0;
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(40px, 6vw, 64px);
            line-height: 0.98;
            letter-spacing: -0.04em;
        }

        .hero-copy p { margin: 0; font-size: 17px; line-height: 1.7; color: rgba(255, 255, 255, 0.92); }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 0 18px;
            border-radius: 14px;
            text-decoration: none;
            border: 1px solid transparent;
            font-weight: 800;
        }

        .button-primary { background: #fff; color: var(--primary); }
        .button-secondary { background: rgba(255, 255, 255, 0.08); color: #fff; border-color: rgba(255, 255, 255, 0.24); }
        .hero-media { overflow: hidden; min-height: 100%; background: linear-gradient(180deg, #dcecf3 0%, #f8fafc 100%); }
        .hero-media img, .hero-media video, .hero-media iframe { width: 100%; height: 100%; min-height: 420px; object-fit: cover; border: 0; }
        .hero-placeholder {
            min-height: 420px;
            display: grid;
            align-content: end;
            gap: 12px;
            padding: 24px;
            background: linear-gradient(145deg, rgba(15, 110, 175, 0.16), rgba(19, 169, 191, 0.08));
        }

        .hero-placeholder div {
            padding: 14px 16px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.82);
            font-weight: 700;
            color: #24415b;
        }

        .section { padding: 16px 0 20px; }
        .section-head { margin-bottom: 18px; max-width: 700px; }
        .section-head span {
            display: inline-block;
            margin-bottom: 10px;
            color: var(--primary);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .section-head h2 {
            margin: 0;
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(30px, 5vw, 44px);
            line-height: 1;
            letter-spacing: -0.04em;
        }

        .section-head p { margin: 12px 0 0; color: var(--muted); line-height: 1.75; }
        .section-card { padding: 24px; }
        .services-grid, .professionals-grid, .instagram-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .service-card, .professional-card, .instagram-card { padding: 22px; }
        .service-card strong, .professional-card strong, .instagram-card strong { display: block; margin-bottom: 10px; font-size: 20px; }
        .service-card p, .professional-card p, .instagram-card p { margin: 0; color: var(--muted); line-height: 1.7; }
        .service-meta {
            display: inline-flex;
            margin-bottom: 12px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(15, 110, 175, 0.08);
            color: var(--primary);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .instagram-card img {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            border-radius: 20px;
            margin-bottom: 14px;
            background: #e8eef4;
        }

        .instagram-card a { color: var(--primary); text-decoration: none; font-weight: 800; }
        .contact-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(280px, 0.8fr); gap: 16px; }
        .contact-card { padding: 24px; }
        .contact-points { display: grid; gap: 12px; margin-top: 16px; }
        .contact-point {
            padding: 14px 16px;
            border-radius: 18px;
            background: var(--surface-soft);
            border: 1px solid rgba(15, 110, 175, 0.08);
        }

        .contact-point strong { display: block; margin-bottom: 6px; font-size: 14px; }
        .contact-point span, .contact-point a { color: var(--muted); text-decoration: none; word-break: break-word; }
        .footer { padding: 18px 0 40px; }
        .footer-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 24px;
            border-radius: 24px;
            background: #172434;
            color: rgba(255, 255, 255, 0.88);
        }

        .footer-card strong { display: block; color: #fff; margin-bottom: 4px; }

        @media (max-width: 980px) {
            .hero-grid, .contact-grid, .services-grid, .professionals-grid, .instagram-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 720px) {
            .topbar-inner, .footer-card { flex-direction: column; align-items: flex-start; }
            .button { width: 100%; }
        }
    </style>
</head>
<body style="--primary: {{ $landing?->primary_color ?: '#0f6eaf' }}; --secondary: {{ $landing?->secondary_color ?: '#13a9bf' }};">
    @php
        $resolveMediaUrl = static function (?string $url, array $preferredDirectories = []): string {
            if (! is_string($url)) {
                return '';
            }

            $normalized = trim($url);

            if ($normalized === '') {
                return '';
            }

            if (
                str_starts_with($normalized, 'http://')
                || str_starts_with($normalized, 'https://')
                || str_starts_with($normalized, '//')
                || str_starts_with($normalized, 'data:')
            ) {
                return $normalized;
            }

            if (str_starts_with($normalized, '/')) {
                return $normalized;
            }

            if (str_starts_with($normalized, 'storage/')) {
                return asset($normalized);
            }

            $paths = [$normalized];

            foreach ($preferredDirectories as $directory) {
                $paths[] = trim((string) $directory, '/') . '/' . ltrim($normalized, '/');
            }

            foreach ($paths as $path) {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                    return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
                }
            }

            return asset('storage/' . ltrim($normalized, '/'));
        };

        $mediaCollection = collect($professionalMedia ?? []);
        $heroImageUrl = $resolveMediaUrl((string) ($landing?->hero_image_url ?: ''), ['landings/tenants/heroes', 'landings/users/heroes']);
        $heroVideoUrl = $resolveMediaUrl((string) ($landing?->hero_video_url ?: ''), ['landings/tenants/heroes']);
        $heroMediaUrl = $heroImageUrl !== '' ? $heroImageUrl : $heroVideoUrl;
        $heroMediaType = $heroImageUrl !== '' ? 'image' : ($heroVideoUrl !== '' ? 'video' : 'none');
        $heroIsEmbeddedVideo = $heroMediaType === 'video'
            && (str_contains($heroMediaUrl, 'youtube.com') || str_contains($heroMediaUrl, 'youtu.be') || str_contains($heroMediaUrl, 'vimeo.com'));
        $featuredMedia = $mediaCollection->take(3);
        $contactUrl = $landing?->cta_url ?: '#contato';
        $contactText = $landing?->cta_text ?: 'Falar com a academia';
        $instagram = $instagramFeed ?? ['enabled' => false, 'username' => null, 'profile_url' => null, 'items' => []];
    @endphp

    <header class="topbar">
        <div class="container">
            <div class="topbar-inner">
                <a href="#inicio" class="brand">
                    <span class="brand-mark">{{ strtoupper(mb_substr($tenant->name, 0, 1)) }}</span>
                    <span class="brand-copy">
                        <strong>{{ $tenant->name }}</strong>
                        <span>Academia com presenca digital e atendimento centralizado</span>
                    </span>
                </a>

                <nav class="nav" aria-label="Navegacao principal">
                    <a href="#sobre">Sobre</a>
                    <a href="#servicos">Servicos</a>
                    <a href="#equipe">Equipe</a>
                    <a href="#instagram">Instagram</a>
                    <a href="#contato">Contato</a>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="hero" id="inicio">
            <div class="container">
                <div class="hero-grid">
                    <div class="hero-card hero-copy">
                        <span class="eyebrow">Landing da academia</span>
                        <h1>{{ $landing?->headline ?: $tenant->name }}</h1>
                        <p>{{ $landing?->description ?: 'Treinos, acompanhamento e atendimento em uma experiencia mais clara para atrair novos alunos e fortalecer a presenca da academia.' }}</p>

                        <div class="hero-actions">
                            <a href="{{ $contactUrl }}" class="button button-primary">{{ $contactText }}</a>
                            <a href="#instagram" class="button button-secondary">Ver rede social</a>
                        </div>
                    </div>

                    <div class="hero-card hero-media">
                        @if($heroMediaType === 'image')
                            <img src="{{ $heroMediaUrl }}" alt="Imagem principal da academia">
                        @elseif($heroMediaType === 'video' && $heroIsEmbeddedVideo)
                            <iframe src="{{ $heroMediaUrl }}" allowfullscreen></iframe>
                        @elseif($heroMediaType === 'video')
                            <video src="{{ $heroMediaUrl }}" controls preload="metadata"></video>
                        @else
                            <div class="hero-placeholder">
                                <div>Ambiente moderno para apresentar sua academia.</div>
                                <div>CTA direto para captar novos alunos.</div>
                                <div>Instagram integrado para trazer prova social.</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="sobre">
            <div class="container">
                <div class="section-head">
                    <span>Sobre a academia</span>
                    <h2>{{ $landing?->title ?: 'Uma pagina simples, direta e pronta para conversao.' }}</h2>
                    <p>{{ $landing?->description ?: 'Use esta landing para apresentar estrutura, equipe, proposta de valor e canais de atendimento da academia com um layout mais limpo e objetivo.' }}</p>
                </div>

                <div class="section-card">
                    <div class="services-grid">
                        <article class="service-card">
                            <span class="service-meta">Estrutura</span>
                            <strong>Apresentacao clara</strong>
                            <p>Mostre rapidamente o que a academia oferece, para quem atende e qual experiencia entrega.</p>
                        </article>
                        <article class="service-card">
                            <span class="service-meta">Conversao</span>
                            <strong>CTA direto</strong>
                            <p>Conduza o visitante para contato, visita experimental ou conversa comercial sem distracoes.</p>
                        </article>
                        <article class="service-card">
                            <span class="service-meta">Autoridade</span>
                            <strong>Prova social</strong>
                            <p>Use equipe, conteudos e Instagram para sustentar confianca e ampliar alcance organicamente.</p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="servicos">
            <div class="container">
                <div class="section-head">
                    <span>Servicos</span>
                    <h2>O que sua academia pode destacar aqui</h2>
                    <p>Mesmo com uma estrutura simples, a pagina continua preparada para apresentar os principais pontos comerciais da operacao.</p>
                </div>

                <div class="services-grid">
                    <article class="service-card">
                        <span class="service-meta">Treinamento</span>
                        <strong>Acompanhamento orientado</strong>
                        <p>Apresente planos, metodologia de treino e diferenciais do acompanhamento da equipe.</p>
                    </article>
                    <article class="service-card">
                        <span class="service-meta">Equipe</span>
                        <strong>Trainers em evidencia</strong>
                        <p>Mostre profissionais da academia para gerar proximidade e reforcar especialidades.</p>
                    </article>
                    <article class="service-card">
                        <span class="service-meta">Conteudo</span>
                        <strong>Midias e rede social</strong>
                        <p>Use imagens internas e conteudo do Instagram para manter a landing viva e atualizada.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section" id="equipe">
            <div class="container">
                <div class="section-head">
                    <span>Equipe</span>
                    <h2>Profissionais em destaque</h2>
                    <p>Selecione os nomes certos para transmitir autoridade, proximidade e capacidade tecnica logo na primeira visita.</p>
                </div>

                <div class="professionals-grid">
                    @forelse($professionals->take(3) as $professional)
                        <article class="professional-card">
                            <strong>{{ $professional->name }}</strong>
                            <p>{{ $professional->goal ?: 'Profissional da equipe com foco em evolucao, constancia e atendimento proximo ao aluno.' }}</p>
                        </article>
                    @empty
                        <article class="professional-card">
                            <strong>Equipe em atualizacao</strong>
                            <p>Adicione trainers ao tenant para destacar a equipe tecnica nesta secao.</p>
                        </article>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="section" id="instagram">
            <div class="container">
                <div class="section-head">
                    <span>Instagram</span>
                    <h2>Conteudo vindo da rede social da academia</h2>
                    <p>Quando o token e o usuario estiverem configurados, esta secao usa a API do Instagram para trazer os posts mais recentes automaticamente.</p>
                </div>

                @if(!empty($instagram['items']))
                    <div class="instagram-grid">
                        @foreach($instagram['items'] as $item)
                            <article class="instagram-card">
                                <img src="{{ $item['media_url'] }}" alt="Post do Instagram da academia">
                                <strong>{{ $instagram['username'] ? '@' . ltrim((string) $instagram['username'], '@') : 'Instagram da academia' }}</strong>
                                <p>{{ \Illuminate\Support\Str::limit($item['caption'] ?: 'Conteudo importado automaticamente do Instagram.', 110) }}</p>
                                @if(!empty($item['permalink']))
                                    <p style="margin-top:12px;"><a href="{{ $item['permalink'] }}" target="_blank" rel="noopener">Abrir no Instagram</a></p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="section-card">
                        <p style="margin:0; color:var(--muted); line-height:1.8;">
                            Ainda nao ha posts importados automaticamente.
                            @if(!empty($instagram['profile_url']))
                                Voce ja pode direcionar o visitante para o perfil em <a href="{{ $instagram['profile_url'] }}" target="_blank" rel="noopener" style="color:var(--primary); font-weight:800; text-decoration:none;">{{ '@' . ltrim((string) $instagram['username'], '@') }}</a>.
                            @else
                                Configure o usuario e o token no painel da academia para ativar esta integracao.
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        </section>

        @if($featuredMedia->isNotEmpty())
            <section class="section">
                <div class="container">
                    <div class="section-head">
                        <span>Midias</span>
                        <h2>Conteudos da academia</h2>
                        <p>As midias internas continuam disponiveis para complementar a comunicacao da landing.</p>
                    </div>

                    <div class="instagram-grid">
                        @foreach($featuredMedia as $media)
                            @php
                                $mediaUrl = $resolveMediaUrl((string) $media->media_url, ['landings/tenants/professionals', 'landings/users/media']);
                            @endphp
                            <article class="instagram-card">
                                @if($media->media_type === 'image')
                                    <img src="{{ $mediaUrl }}" alt="Midia da academia">
                                @else
                                    <img src="{{ $heroImageUrl ?: 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 400%22%3E%3Crect width=%22400%22 height=%22400%22 fill=%22%23dbe8ef%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 fill=%22%2324415b%22 font-family=%22Arial%22 font-size=%2224%22%3EVideo%3C/text%3E%3C/svg%3E' }}" alt="Video da academia">
                                @endif
                                <strong>{{ $media->title ?: ($media->professional?->name ?: 'Conteudo da equipe') }}</strong>
                                <p>{{ $media->description ?: 'Midia publicada para reforcar a comunicacao da academia.' }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="section" id="contato">
            <div class="container">
                <div class="section-head">
                    <span>Contato</span>
                    <h2>Pronto para receber novos alunos</h2>
                    <p>Use o CTA principal para levar o visitante ao canal comercial mais importante da academia.</p>
                </div>

                <div class="contact-grid">
                    <div class="contact-card">
                        <strong style="display:block; margin-bottom:10px; font-size:24px;">Canal principal</strong>
                        <p style="margin:0; color:var(--muted); line-height:1.8;">Mantenha a rota de conversao simples: um CTA claro, um link direto e informacoes objetivas para o proximo passo.</p>
                        <div style="margin-top:18px;">
                            <a href="{{ $contactUrl }}" class="button" style="background:linear-gradient(135deg, var(--primary), var(--secondary)); color:#fff;">{{ $contactText }}</a>
                        </div>
                    </div>

                    <aside class="contact-card">
                        <div class="contact-points">
                            @if($tenant->contact_email)
                                <div class="contact-point">
                                    <strong>Email</strong>
                                    <a href="mailto:{{ $tenant->contact_email }}">{{ $tenant->contact_email }}</a>
                                </div>
                            @endif
                            @if($tenant->contact_phone)
                                <div class="contact-point">
                                    <strong>Telefone</strong>
                                    <span>{{ $tenant->contact_phone }}</span>
                                </div>
                            @endif
                            @if(!empty($instagram['profile_url']))
                                <div class="contact-point">
                                    <strong>Instagram</strong>
                                    <a href="{{ $instagram['profile_url'] }}" target="_blank" rel="noopener">{{ '@' . ltrim((string) $instagram['username'], '@') }}</a>
                                </div>
                            @endif
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-card">
                <div>
                    <strong>{{ $tenant->name }}</strong>
                    <span>Landing simples para apresentar a academia e conectar canais de conversao.</span>
                </div>
                <a href="{{ $contactUrl }}" class="button button-primary">{{ $contactText }}</a>
            </div>
        </div>
    </footer>
</body>
</html>
