@extends('layouts.panel')

@section('pageTitle', 'System Admin - Settings WorkoutX')
@section('headerTitle', 'Settings WorkoutX')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.settings.workoutx.audit') }}">Auditar catalogo</a>
    <a class="btn btn-soft" href="{{ route('system-admin.dashboard') }}">Voltar ao dashboard</a>
@endsection

@section('content')
    @php
        $syncStatus = $syncStatus ?? [];
        $syncProgress = $syncStatus['progress'] ?? [];
        $syncState = $syncStatus['state'] ?? 'idle';
        $syncLocked = in_array($syncState, ['queued', 'running'], true);
        $activeVectorStore = $activeVectorStore ?? null;
    @endphp

    <div class="card" style="max-width: 860px;">
        <h3>Integracao WorkoutX API</h3>
        <p>Configure a chave da API e os parametros basicos para buscar GIFs e dados dos exercicios direto da WorkoutX.</p>

        <div class="card" style="margin-top: 16px; background: #fafafa; border-style: dashed;">
            <h3 style="margin-top: 0;">Catalogo local de exercicios</h3>
            <p style="margin-bottom: 10px;">A sincronizacao consulta todo o endpoint /exercises da WorkoutX, faz upsert pelo id remoto e atualiza name, gifUrl e payload completo na sua base.</p>

            <div class="form-grid" style="margin-bottom: 14px;">
                <div class="field">
                    <label>Total salvo</label>
                    <strong>{{ number_format((int) ($catalogStats['total'] ?? 0), 0, ',', '.') }}</strong>
                </div>

                <div class="field">
                    <label>Com id remoto</label>
                    <strong>{{ number_format((int) ($catalogStats['with_remote_id'] ?? 0), 0, ',', '.') }}</strong>
                </div>

                <div class="field" style="grid-column: 1 / -1;">
                    <label>Ultima atualizacao local</label>
                    <strong>
                        @if (! empty($catalogStats['last_synced_at']))
                            {{ \Illuminate\Support\Carbon::parse($catalogStats['last_synced_at'])->format('d/m/Y H:i') }}
                        @else
                            Nao sincronizado ainda
                        @endif
                    </strong>
                </div>
            </div>

            <div class="card" style="margin-bottom: 14px; background: #fff; border-style: dashed;">
                <h3 style="margin-top: 0;">Status da fila de sincronizacao</h3>
                <p style="margin-bottom: 8px;">
                    @if ($syncState === 'queued')
                        <span class="badge warning">Na fila</span>
                    @elseif ($syncState === 'running')
                        <span class="badge warning">Em andamento</span>
                    @elseif ($syncState === 'completed')
                        <span class="badge success">Concluida</span>
                    @elseif ($syncState === 'failed')
                        <span class="badge warning">Falhou</span>
                    @else
                        <span class="badge">Parada</span>
                    @endif
                </p>
                <p style="margin-bottom: 8px; color: #475569;">{{ $syncStatus['message'] ?? 'Nenhuma sincronizacao em fila no momento.' }}</p>
                <div class="form-grid">
                    <div class="field">
                        <label>Processados</label>
                        <strong>{{ number_format((int) ($syncProgress['synced'] ?? 0), 0, ',', '.') }}</strong>
                    </div>
                    <div class="field">
                        <label>Novos</label>
                        <strong>{{ number_format((int) ($syncProgress['created'] ?? 0), 0, ',', '.') }}</strong>
                    </div>
                    <div class="field">
                        <label>Atualizados</label>
                        <strong>{{ number_format((int) ($syncProgress['updated'] ?? 0), 0, ',', '.') }}</strong>
                    </div>
                    <div class="field">
                        <label>Sem alteracao</label>
                        <strong>{{ number_format((int) ($syncProgress['unchanged'] ?? 0), 0, ',', '.') }}</strong>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('system-admin.settings.workoutx.sync') }}">
                @csrf
                <div class="actions" style="justify-content: flex-start;">
                    <button type="submit" class="btn btn-primary" @disabled($syncLocked)>
                        @if ($syncLocked)
                            Sincronizacao em andamento
                        @else
                            Sincronizar catalogo completo
                        @endif
                    </button>
                    <a class="btn btn-soft" href="{{ route('system-admin.settings.workoutx.audit') }}">Abrir auditoria</a>
                </div>
                @if ($syncLocked)
                    <small style="display: block; margin-top: 10px; color: #64748b;">O botao fica bloqueado enquanto a fila estiver aguardando ou processando paginas.</small>
                @endif
            </form>
        </div>

        <form method="POST" action="{{ route('system-admin.settings.workoutx.update') }}" class="content-stack" style="margin-top: 12px;">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field" style="grid-column: 1 / -1;">
                    <label for="workoutx_enabled">Ativar integracao</label>
                    <label style="display: flex; align-items: center; gap: 8px; margin-top: 8px;">
                        <input id="workoutx_enabled" name="workoutx_enabled" type="checkbox" value="1" @checked(old('workoutx_enabled', $settings->get('workoutx.enabled', '0')) === '1')>
                        <span>Usar WorkoutX como fonte externa de exercicios e GIFs</span>
                    </label>
                </div>

                <div class="field">
                    <label for="workoutx_api_base_url">API Base URL</label>
                    <input id="workoutx_api_base_url" name="workoutx_api_base_url" type="url" maxlength="2000" value="{{ old('workoutx_api_base_url', $settings->get('workoutx.api_base_url', 'https://api.workoutxapp.com/v1')) }}" inputmode="url" spellcheck="false">
                    <small>Padrao da documentacao: https://api.workoutxapp.com/v1</small>
                </div>

                <div class="field">
                    <label for="workoutx_auth_mode">Modo de autenticacao</label>
                    <select id="workoutx_auth_mode" name="workoutx_auth_mode">
                        @php
                            $authMode = old('workoutx_auth_mode', $settings->get('workoutx.auth_mode', 'header'));
                        @endphp
                        <option value="header" @selected($authMode === 'header')>Header X-WorkoutX-Key</option>
                        <option value="query" @selected($authMode === 'query')>Query param api-key</option>
                    </select>
                </div>

                <div class="field" style="grid-column: 1 / -1;">
                    <label for="workoutx_api_key">API Key (segredo)</label>
                    <input id="workoutx_api_key" name="workoutx_api_key" type="password" maxlength="255" placeholder="Preencha para atualizar a chave da WorkoutX">
                    <small>
                        Status atual:
                        @if (filled($settings->get('workoutx.api_key')))
                            <span class="badge success">Configurado</span>
                        @else
                            <span class="badge warning">Nao configurado</span>
                        @endif
                    </small>
                </div>

                <div class="field">
                    <label for="workoutx_request_timeout">Timeout da requisicao (segundos)</label>
                    <input id="workoutx_request_timeout" name="workoutx_request_timeout" type="number" min="3" max="120" value="{{ old('workoutx_request_timeout', $settings->get('workoutx.request_timeout', '20')) }}">
                </div>

                <div class="field">
                    <label for="workoutx_default_limit">Limite padrao por consulta</label>
                    <input id="workoutx_default_limit" name="workoutx_default_limit" type="number" min="1" max="100" value="{{ old('workoutx_default_limit', $settings->get('workoutx.default_limit', '10')) }}">
                    <small>Na free tier a documentacao informa maximo de 10 resultados por request.</small>
                </div>

                <div class="field">
                    <label for="workoutx_sync_page_delay_seconds">Intervalo entre paginas da sincronizacao (segundos)</label>
                    <input id="workoutx_sync_page_delay_seconds" name="workoutx_sync_page_delay_seconds" type="number" min="10" max="3600" value="{{ old('workoutx_sync_page_delay_seconds', $settings->get('workoutx.sync_page_delay_seconds', '120')) }}">
                    <small>Controla o tempo de espera entre uma pagina sincronizada e a proxima na fila.</small>
                </div>

                <div class="field" style="grid-column: 1 / -1;">
                    <label for="workoutx_allow_fallback">Fallback opcional</label>
                    <label style="display: flex; align-items: center; gap: 8px; margin-top: 8px;">
                        <input id="workoutx_allow_fallback" name="workoutx_allow_fallback" type="checkbox" value="1" @checked(old('workoutx_allow_fallback', $settings->get('workoutx.allow_fallback', '0')) === '1')>
                        <span>Permitir SVG local apenas quando a WorkoutX nao retornar GIF</span>
                    </label>
                </div>
            </div>

            <div class="card" style="background: #fafafa; border-style: dashed;">
                <h3 style="margin-top: 0;">Vector Store da OpenAI</h3>
                <p style="margin-bottom: 10px;">Essas variaveis deixam de ficar apenas no ambiente e passam a ser controladas pelo System Admin, junto com a configuracao do catalogo de treino.</p>

                <div class="card" style="margin-bottom: 14px; background: #fff; border-style: dashed;">
                    <h3 style="margin-top: 0;">Vector Store ativa</h3>
                    @if ($activeVectorStore)
                        <div class="form-grid">
                            <div class="field">
                                <label>ID ativo</label>
                                <strong>{{ $activeVectorStore->vector_store_id }}</strong>
                            </div>
                            <div class="field">
                                <label>Nome salvo</label>
                                <strong>{{ $activeVectorStore->vector_store_name ?: 'Sem nome salvo' }}</strong>
                            </div>
                            <div class="field">
                                <label>Status</label>
                                <strong>{{ $activeVectorStore->status ?: 'ready' }}</strong>
                            </div>
                            <div class="field">
                                <label>Arquivo atual</label>
                                <strong>{{ $activeVectorStore->storage_path }}</strong>
                            </div>
                            <div class="field">
                                <label>Ultima sincronizacao</label>
                                <strong>{{ $activeVectorStore->last_synced_at?->format('d/m/Y H:i') ?? 'Nao sincronizada ainda' }}</strong>
                            </div>
                            <div class="field">
                                <label>Ultimo uso</label>
                                <strong>{{ $activeVectorStore->last_used_at?->format('d/m/Y H:i') ?? 'Ainda nao utilizada' }}</strong>
                            </div>
                        </div>
                    @else
                        <p style="margin: 0; color: #64748b;">Nenhuma vector store foi sincronizada localmente ainda. Se voce preencher um ID existente abaixo, ele sera reutilizado no proximo sync/uso.</p>
                    @endif
                </div>

                <div class="form-grid">
                    <div class="field" style="grid-column: 1 / -1;">
                        <label for="vector_store_enabled">Ativar retrieval por Vector Store</label>
                        <label style="display: flex; align-items: center; gap: 8px; margin-top: 8px;">
                            <input id="vector_store_enabled" name="vector_store_enabled" type="checkbox" value="1" @checked(old('vector_store_enabled', $settings->get('openai.vector_store.enabled', '1')) === '1')>
                            <span>Usar a Vector Store da OpenAI antes do fallback local</span>
                        </label>
                    </div>

                    <div class="field">
                        <label for="vector_store_scope">Escopo</label>
                        @php
                            $vectorStoreScope = old('vector_store_scope', $settings->get('openai.vector_store.scope', 'global'));
                        @endphp
                        <select id="vector_store_scope" name="vector_store_scope">
                            <option value="global" @selected($vectorStoreScope === 'global')>Global</option>
                            <option value="tenant" @selected($vectorStoreScope === 'tenant')>Por tenant</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="vector_store_catalog_type">Catalog type</label>
                        <input id="vector_store_catalog_type" name="vector_store_catalog_type" type="text" maxlength="80" value="{{ old('vector_store_catalog_type', $settings->get('openai.vector_store.catalog_type', 'workout_exercises')) }}" spellcheck="false">
                    </div>

                    <div class="field">
                        <label for="vector_store_name_prefix">Prefixo do nome</label>
                        <input id="vector_store_name_prefix" name="vector_store_name_prefix" type="text" maxlength="120" value="{{ old('vector_store_name_prefix', $settings->get('openai.vector_store.name_prefix', 'akdemia-workouts')) }}" spellcheck="false">
                    </div>

                    <div class="field">
                        <label for="vector_store_existing_id">Vector Store ID existente</label>
                        <input id="vector_store_existing_id" name="vector_store_existing_id" type="text" maxlength="120" value="{{ old('vector_store_existing_id', $settings->get('openai.vector_store.existing_id', '')) }}" spellcheck="false" placeholder="vs_...">
                        <small>Cole aqui o ID mostrado no painel Storage da OpenAI para reaproveitar uma Vector Store ja criada.</small>
                    </div>

                    <div class="field">
                        <label for="vector_store_existing_name">Nome da Vector Store existente</label>
                        <input id="vector_store_existing_name" name="vector_store_existing_name" type="text" maxlength="160" value="{{ old('vector_store_existing_name', $settings->get('openai.vector_store.existing_name', '')) }}" spellcheck="false" placeholder="akdemia-workouts-global">
                        <small>Opcional. Serve para manter o nome salvo localmente igual ao nome exibido no painel da OpenAI.</small>
                    </div>

                    <div class="field">
                        <label for="vector_store_file_purpose">File purpose</label>
                        <input id="vector_store_file_purpose" name="vector_store_file_purpose" type="text" maxlength="80" value="{{ old('vector_store_file_purpose', $settings->get('openai.vector_store.file_purpose', 'assistants')) }}" spellcheck="false">
                    </div>

                    <div class="field">
                        <label for="vector_store_max_search_results">Maximo de resultados por busca</label>
                        <input id="vector_store_max_search_results" name="vector_store_max_search_results" type="number" min="1" max="100" value="{{ old('vector_store_max_search_results', $settings->get('openai.vector_store.max_search_results', '24')) }}">
                    </div>

                    <div class="field">
                        <label for="vector_store_minimum_candidates">Minimo de candidatos</label>
                        <input id="vector_store_minimum_candidates" name="vector_store_minimum_candidates" type="number" min="1" max="100" value="{{ old('vector_store_minimum_candidates', $settings->get('openai.vector_store.minimum_candidates', '12')) }}">
                    </div>

                    <div class="field" style="grid-column: 1 / -1;">
                        <label for="vector_store_storage_path">Arquivo JSONL do catalogo vetorial</label>
                        <input id="vector_store_storage_path" name="vector_store_storage_path" type="text" maxlength="255" value="{{ old('vector_store_storage_path', $settings->get('internal_catalog.vector_store_storage_path', 'ai/openai-workout-catalog.jsonl')) }}" spellcheck="false">
                        <small>Caminho relativo dentro de storage/app usado no export para upload na Vector Store.</small>
                    </div>
                </div>
            </div>

            <div class="card" style="background: #fafafa; border-style: dashed;">
                <h3 style="margin-top: 0;">Referencia da API</h3>
                <p style="margin-bottom: 8px;">Campos mapeados com base na documentacao oficial:</p>
                <ul style="margin: 0; padding-left: 18px; display: grid; gap: 4px;">
                    <li>Base URL: https://api.workoutxapp.com/v1</li>
                    <li>Auth recomendada: header X-WorkoutX-Key</li>
                    <li>Auth alternativa: query param api-key</li>
                    <li>Endpoint base de exercicios: /exercises</li>
                    <li>Detalhe por exercicio: /exercises/exercise/:id</li>
                    <li>Sincronizacao local: salva id, name, gifUrl, storage_path e payload JSON</li>
                    <li>Fila paginada: processa uma pagina por vez com intervalo configuravel entre requests</li>
                    <li>Vector Store: enable, scope, catalog_type, name_prefix, file_purpose, limites e JSONL agora ficam no System Admin</li>
                </ul>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Salvar configuracoes</button>
            </div>
        </form>
    </div>
@endsection
