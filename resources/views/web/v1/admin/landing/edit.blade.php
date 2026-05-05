@extends('layouts.panel')

@section('pageTitle', 'Landing do Contratante')
@section('headerTitle', 'Landing do Contratante')

@section('content')
    <style>
        .landing-simple-shell { display: grid; gap: 18px; }
        .landing-simple-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, 0.88fr);
            gap: 18px;
            align-items: start;
        }
        .landing-simple-section {
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            background: #fff;
            padding: 18px;
            display: grid;
            gap: 14px;
        }
        .landing-simple-section h4 {
            margin: 0;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
        }
        .landing-simple-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .landing-simple-field { display: grid; gap: 6px; }
        .landing-simple-field-full { grid-column: 1 / -1; }
        .landing-simple-field label {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
        }
        .landing-simple-field input,
        .landing-simple-field textarea,
        .landing-simple-field select {
            width: 100%;
            border: 1px solid #dbe2ea;
            border-radius: 14px;
            background: #f8fafc;
            padding: 12px 13px;
            font: inherit;
            color: #0f172a;
        }
        .landing-simple-field textarea { min-height: 112px; resize: vertical; }
        .landing-simple-note { margin: 0; color: #64748b; font-size: 13px; line-height: 1.6; }
        .landing-simple-publish {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 10px 14px;
            border-radius: 999px;
            background: #eef6ff;
            border: 1px solid #cfe2ff;
            color: #1d4f91;
            font-size: 13px;
            font-weight: 700;
        }
        .landing-simple-preview {
            position: sticky;
            top: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 24px;
            overflow: hidden;
            background: linear-gradient(180deg, #ffffff 0%, #f4f8fb 100%);
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
        }
        .landing-simple-preview-hero {
            padding: 22px;
            display: grid;
            gap: 12px;
            background: linear-gradient(135deg, #0f6eaf 0%, #13a9bf 100%);
            color: #fff;
        }
        .landing-simple-preview-hero h3 { margin: 0; font-size: 28px; line-height: 1.08; }
        .landing-simple-preview-hero p { margin: 0; color: rgba(255, 255, 255, 0.92); line-height: 1.6; }
        .landing-simple-preview-media {
            min-height: 180px;
            border-radius: 18px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .landing-simple-preview-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .landing-simple-preview-fallback {
            height: 100%;
            min-height: 180px;
            display: grid;
            align-content: end;
            gap: 10px;
            padding: 16px;
            background: linear-gradient(180deg, rgba(255,255,255,0.1), rgba(8,15,27,0.25));
        }
        .landing-simple-preview-fallback div {
            padding: 10px 12px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.14);
            font-size: 12px;
            font-weight: 700;
        }
        .landing-simple-preview-body { padding: 18px; display: grid; gap: 12px; }
        .landing-simple-preview-card {
            border: 1px solid #e6edf5;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.92);
            padding: 16px;
        }
        .landing-simple-preview-card strong { display: block; margin-bottom: 8px; font-size: 15px; }
        .landing-simple-preview-card p { margin: 0; color: #64748b; font-size: 14px; line-height: 1.6; }
        .landing-simple-preview-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 16px;
            border-radius: 14px;
            width: fit-content;
            background: #0f6eaf;
            color: #fff;
            font-weight: 800;
        }
        @media (max-width: 1080px) {
            .landing-simple-grid { grid-template-columns: 1fr; }
            .landing-simple-preview { position: static; }
        }
        @media (max-width: 720px) {
            .landing-simple-fields { grid-template-columns: 1fr; }
        }
    </style>

    <div class="content-stack landing-simple-shell">
        @if (session('status'))
            <div class="flash-success">{{ session('status') }}</div>
        @endif

        @if (session('compression_status'))
            <div class="flash-success">{{ session('compression_status') }}</div>
        @endif

        <div class="card" style="max-width: 1240px;">
            <h3>Landing da academia</h3>
            <p class="landing-simple-note">Versao mais simples da landing do contratante, com foco em apresentacao, CTA e integracao opcional com Instagram.</p>

            <form method="POST" action="{{ route('admin.landing.update') }}" class="landing-simple-shell js-upload-form" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <input type="hidden" name="theme_preset" value="{{ old('theme_preset', $landing?->theme_preset ?: 'ocean_mist') }}">
                <input type="hidden" name="primary_color" value="{{ old('primary_color', $landing?->primary_color ?: '#0f6eaf') }}">
                <input type="hidden" name="secondary_color" value="{{ old('secondary_color', $landing?->secondary_color ?: '#13a9bf') }}">
                <input type="hidden" name="hero_video_url" value="{{ old('hero_video_url', $landing?->hero_video_url) }}">

                <div class="landing-simple-grid">
                    <div class="landing-simple-shell">
                        <section class="landing-simple-section">
                            <h4>Conteudo principal</h4>
                            <div class="landing-simple-fields">
                                <div class="landing-simple-field">
                                    <label for="title">Titulo da secao sobre</label>
                                    <input id="title" name="title" type="text" maxlength="255" value="{{ old('title', $landing?->title) }}">
                                </div>
                                <div class="landing-simple-field">
                                    <label for="headline">Headline</label>
                                    <input id="headline" name="headline" type="text" maxlength="255" value="{{ old('headline', $landing?->headline) }}">
                                </div>
                                <div class="landing-simple-field landing-simple-field-full">
                                    <label for="description">Descricao</label>
                                    <textarea id="description" name="description">{{ old('description', $landing?->description) }}</textarea>
                                </div>
                            </div>
                        </section>

                        <section class="landing-simple-section">
                            <h4>Hero e CTA</h4>
                            <div class="landing-simple-fields">
                                <div class="landing-simple-field landing-simple-field-full">
                                    <label for="hero_image_url">Imagem principal URL</label>
                                    <input id="hero_image_url" name="hero_image_url" type="url" value="{{ old('hero_image_url', $landing?->hero_image_url) }}" placeholder="https://...">
                                </div>
                                <div class="landing-simple-field landing-simple-field-full">
                                    <label for="hero_image_file">Upload de imagem</label>
                                    <input id="hero_image_file" class="js-file-preview" data-preview-target="tenant-hero-image-preview" data-max-bytes="3145728" data-kind="image" name="hero_image_file" type="file" accept="image/*">
                                </div>
                                <div class="landing-simple-field">
                                    <label for="cta_text">Texto do CTA</label>
                                    <input id="cta_text" name="cta_text" type="text" maxlength="255" value="{{ old('cta_text', $landing?->cta_text) }}">
                                </div>
                                <div class="landing-simple-field">
                                    <label for="cta_url">Link do CTA</label>
                                    <input id="cta_url" name="cta_url" type="url" value="{{ old('cta_url', $landing?->cta_url) }}">
                                </div>
                            </div>

                            <div id="tenant-hero-image-preview" style="display:none;"></div>
                        </section>

                        <section class="landing-simple-section">
                            <h4>Instagram</h4>
                            <p class="landing-simple-note">Informe o usuario e o token da API para a landing puxar os posts recentes automaticamente.</p>
                            <div class="landing-simple-fields">
                                <div class="landing-simple-field">
                                    <label for="instagram_username">Usuario do Instagram</label>
                                    <input id="instagram_username" name="instagram_username" type="text" maxlength="100" value="{{ old('instagram_username', $landing?->instagram_username) }}" placeholder="academia.exemplo">
                                </div>
                                <div class="landing-simple-field">
                                    <label for="instagram_access_token">Token da API do Instagram</label>
                                    <input id="instagram_access_token" name="instagram_access_token" type="password" maxlength="5000" value="" placeholder="Cole aqui o token atual ou um novo token">
                                </div>
                            </div>
                        </section>

                        <label class="landing-simple-publish"><input type="checkbox" name="is_published" value="1" {{ old('is_published', $landing?->is_published) ? 'checked' : '' }}> Publicar landing</label>

                        <div class="js-upload-progress" style="display:none;margin:4px 0 0;">
                            <div style="height:10px;border-radius:999px;background:#e5e7eb;overflow:hidden;">
                                <div class="js-upload-progress-bar" style="height:10px;width:0;background:#2563eb;transition:width .2s ease;"></div>
                            </div>
                            <small class="js-upload-progress-text" style="color:#4b5563;">Preparando upload...</small>
                        </div>

                        <div class="actions">
                            <button class="btn btn-primary" type="submit">Salvar landing</button>
                            @if(request()->attributes->get('tenant')?->slug)
                                <a href="{{ route('landing.subdomain', ['slug' => request()->attributes->get('tenant')->slug]) }}" class="btn btn-soft" target="_blank" rel="noopener">Visualizar landing</a>
                            @endif
                        </div>
                    </div>

                    <aside class="landing-simple-preview" aria-live="polite">
                        <div class="landing-simple-preview-hero">
                            <h3 id="preview-headline">{{ old('headline', $landing?->headline ?: 'Sua academia com uma apresentacao mais clara e objetiva.') }}</h3>
                            <p id="preview-description">{{ old('description', $landing?->description ?: 'Landing simples para destacar estrutura, equipe e canais de atendimento com foco em conversao.') }}</p>
                            <span class="landing-simple-preview-cta" id="preview-cta-text">{{ old('cta_text', $landing?->cta_text ?: 'Falar com a academia') }}</span>
                            <div class="landing-simple-preview-media">
                                @if(old('hero_image_url', $landing?->hero_image_url))
                                    <img id="preview-hero-image" src="{{ old('hero_image_url', $landing?->hero_image_url) }}" alt="Preview da imagem principal">
                                    <div class="landing-simple-preview-fallback" id="preview-hero-image-fallback" hidden>
                                        <div>Headline forte</div>
                                        <div>CTA direto</div>
                                        <div>Instagram integrado</div>
                                    </div>
                                @else
                                    <img id="preview-hero-image" src="" alt="Preview da imagem principal" hidden>
                                    <div class="landing-simple-preview-fallback" id="preview-hero-image-fallback">
                                        <div>Headline forte</div>
                                        <div>CTA direto</div>
                                        <div>Instagram integrado</div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="landing-simple-preview-body">
                            <div class="landing-simple-preview-card">
                                <strong id="preview-title">{{ old('title', $landing?->title ?: 'Uma pagina simples, direta e pronta para conversao.') }}</strong>
                                <p>Secao sobre da academia com comunicacao mais limpa e menos ruido visual.</p>
                            </div>
                            <div class="landing-simple-preview-card">
                                <strong>Instagram</strong>
                                <p id="preview-instagram">{{ old('instagram_username', $landing?->instagram_username ?: 'Conecte o Instagram da academia para puxar os posts mais recentes.') }}</p>
                            </div>
                        </div>
                    </aside>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Midias dos profissionais</h3>
            <p class="landing-simple-note">Esta parte continua disponivel para complementar a landing com conteudos da equipe.</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                <span style="display:inline-flex;align-items:center;gap:6px;background:#eef6ff;color:#1e3a8a;border:1px solid #cfe2ff;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:600;">Foto: maximo 15 por profissional</span>
                <span style="display:inline-flex;align-items:center;gap:6px;background:#eef6ff;color:#1e3a8a;border:1px solid #cfe2ff;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:600;">Video: maximo 4 por profissional</span>
            </div>
            <form method="POST" action="{{ route('admin.landing.professional-media.store') }}" class="form-shell js-upload-form landing-form-shell" enctype="multipart/form-data">
                @csrf
                <div class="landing-field">
                    <label class="landing-label" data-icon="PRO">Profissional</label>
                    <select class="field-control" name="professional_user_id" required>
                        <option value="">Selecione</option>
                        @foreach($professionals as $professional)
                            <option value="{{ $professional->id }}">{{ $professional->name }} ({{ $professional->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="landing-field">
                    <label class="landing-label" data-icon="TYPE">Tipo</label>
                    <select class="field-control" name="media_type" required>
                        <option value="image">Imagem</option>
                        <option value="video">Video</option>
                    </select>
                </div>
                <div class="landing-field"><label class="landing-label" data-icon="URL">URL (opcional)</label><input class="field-control" name="media_url"></div>
                <div class="landing-field"><label class="landing-label" data-icon="FILE">Arquivo (opcional)</label><input class="field-control js-file-preview" data-preview-target="tenant-media-preview" data-max-bytes="31457280" data-kind="auto" name="media_file" type="file" accept="image/*,video/*"></div>
                <div id="tenant-media-preview" style="display:none;margin-bottom:12px;"></div>
                <div class="landing-field"><label class="landing-label" data-icon="T">Titulo</label><input class="field-control" name="title"></div>
                <div class="landing-field"><label class="landing-label" data-icon="TXT">Descricao</label><textarea class="field-control" name="description" rows="2"></textarea></div>
                <div class="js-upload-progress" style="display:none;margin:12px 0;">
                    <div style="height:10px;border-radius:999px;background:#e5e7eb;overflow:hidden;">
                        <div class="js-upload-progress-bar" style="height:10px;width:0;background:#2563eb;transition:width .2s ease;"></div>
                    </div>
                    <small class="js-upload-progress-text" style="color:#4b5563;">Preparando upload...</small>
                </div>
                <button class="btn btn-primary" type="submit">Adicionar midia</button>
            </form>

            <hr>
            @foreach($professionalMedia as $media)
                <div style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;">
                    <div>
                        <strong>{{ strtoupper($media->media_type) }}</strong> - {{ $media->professional?->name }} - {{ $media->title ?: $media->media_url }}
                    </div>
                    <form method="POST" action="{{ route('admin.landing.professional-media.destroy', $media->id) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-soft" type="submit">Remover</button>
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
                });
            });

            bindTextPreview('headline', 'preview-headline', 'Sua academia com uma apresentacao mais clara e objetiva.');
            bindTextPreview('description', 'preview-description', 'Landing simples para destacar estrutura, equipe e canais de atendimento com foco em conversao.');
            bindTextPreview('title', 'preview-title', 'Uma pagina simples, direta e pronta para conversao.');
            bindTextPreview('cta_text', 'preview-cta-text', 'Falar com a academia');
            bindTextPreview('instagram_username', 'preview-instagram', 'Conecte o Instagram da academia para puxar os posts mais recentes.');
            bindImagePreview('hero_image_url', 'preview-hero-image', 'preview-hero-image-fallback');
        })();
    </script>
@endsection
