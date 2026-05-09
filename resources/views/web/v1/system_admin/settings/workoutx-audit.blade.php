@extends('layouts.panel')

@section('pageTitle', 'System Admin - Auditoria Catalogo WorkoutX')
@section('headerTitle', 'Auditoria Catalogo WorkoutX')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.settings.workoutx.edit') }}">Voltar para WorkoutX</a>
@endsection

@section('content')
    @php
        $filters = $audit['filters'] ?? [];
        $summary = $audit['summary'] ?? [];
        $rows = $audit['rows'] ?? [];
        $availableFocuses = $audit['available_focuses'] ?? [];
        $pagination = $audit['pagination'] ?? [];
        $query = request()->query();
    @endphp

    <div class="card">
        <h3>Auditoria do catalogo local</h3>
        <p>Use esta tela para revisar os exercicios sincronizados, filtrar por foco muscular, localizar nomes e acompanhar a cobertura de traducao pt-BR.</p>

        <div class="form-grid" style="margin-top: 16px;">
            <div class="field">
                <label>Total catalogado</label>
                <strong>{{ number_format((int) ($summary['total'] ?? 0), 0, ',', '.') }}</strong>
            </div>
            <div class="field">
                <label>Traduzidos pt-BR</label>
                <strong>{{ number_format((int) ($summary['translated'] ?? 0), 0, ',', '.') }}</strong>
            </div>
            <div class="field">
                <label>Pendentes de traducao</label>
                <strong>{{ number_format((int) ($summary['pending_translation'] ?? 0), 0, ',', '.') }}</strong>
            </div>
        </div>

        <form method="GET" action="{{ route('system-admin.settings.workoutx.audit') }}" class="content-stack" style="margin-top: 16px;">
            <div class="form-grid">
                <div class="field">
                    <label for="search">Busca</label>
                    <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}" placeholder="Nome, target, equipment, workoutx_name">
                </div>

                <div class="field">
                    <label for="focus">Foco muscular</label>
                    <select id="focus" name="focus">
                        <option value="">Todos</option>
                        @foreach ($availableFocuses as $focus)
                            <option value="{{ $focus }}" @selected(($filters['focus'] ?? '') === $focus)>{{ ucfirst($focus) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="translation_status">Status traducao pt-BR</label>
                    <select id="translation_status" name="translation_status">
                        <option value="">Todos</option>
                        <option value="translated" @selected(($filters['translation_status'] ?? '') === 'translated')>Traduzido</option>
                        <option value="pending" @selected(($filters['translation_status'] ?? '') === 'pending')>Pendente</option>
                    </select>
                </div>

                <div class="field">
                    <label for="limit">Itens por pagina</label>
                    <select id="limit" name="limit">
                        @foreach ([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected((int) ($filters['limit'] ?? 25) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="justify-content: flex-start;">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a class="btn btn-soft" href="{{ route('system-admin.settings.workoutx.audit') }}">Limpar</a>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top: 18px; overflow-x: auto;">
        <h3 style="margin-top: 0;">Exercicios</h3>

        @if ($rows === [])
            <p>Nenhum exercicio encontrado para os filtros atuais.</p>
        @else
            <table style="width: 100%; border-collapse: collapse; min-width: 960px;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid #e5e7eb;">
                        <th style="padding: 10px;">ID</th>
                        <th style="padding: 10px;">Nome pt-BR</th>
                        <th style="padding: 10px;">Nome WorkoutX</th>
                        <th style="padding: 10px;">Foco</th>
                        <th style="padding: 10px;">Body Part</th>
                        <th style="padding: 10px;">Target</th>
                        <th style="padding: 10px;">Equipment</th>
                        <th style="padding: 10px;">Traducao</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr style="border-bottom: 1px solid #f1f5f9; vertical-align: top;">
                            <td style="padding: 10px; font-family: monospace;">{{ $row['id'] }}</td>
                            <td style="padding: 10px;">
                                @if (filled($row['localized_name_pt_br'] ?? null))
                                    {{ $row['localized_name_pt_br'] }}
                                @else
                                    <span style="color: #b45309;">Pendente</span>
                                @endif
                            </td>
                            <td style="padding: 10px;">
                                <div>{{ $row['name'] }}</div>
                                <small style="color: #64748b;">{{ $row['workoutx_name'] }}</small>
                            </td>
                            <td style="padding: 10px; text-transform: capitalize;">{{ $row['focus'] }}</td>
                            <td style="padding: 10px; text-transform: capitalize;">{{ $row['body_part'] }}</td>
                            <td style="padding: 10px;">{{ $row['target'] !== '' ? $row['target'] : '-' }}</td>
                            <td style="padding: 10px;">{{ $row['equipment'] !== '' ? $row['equipment'] : '-' }}</td>
                            <td style="padding: 10px;">
                                @if (($row['translation_status'] ?? '') === 'translated')
                                    <span class="badge success">Traduzido</span>
                                @else
                                    <span class="badge warning">Pendente</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="actions" style="justify-content: space-between; margin-top: 16px;">
                <span>Pagina {{ $pagination['page'] ?? 1 }} de {{ $pagination['last_page'] ?? 1 }}</span>
                <div style="display: flex; gap: 8px;">
                    @if ($pagination['has_previous'] ?? false)
                        <a class="btn btn-soft" href="{{ route('system-admin.settings.workoutx.audit', array_merge($query, ['page' => max(($pagination['page'] ?? 1) - 1, 1)])) }}">Anterior</a>
                    @endif
                    @if ($pagination['has_next'] ?? false)
                        <a class="btn btn-soft" href="{{ route('system-admin.settings.workoutx.audit', array_merge($query, ['page' => ($pagination['page'] ?? 1) + 1])) }}">Proxima</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
