@extends('layouts.panel')

@section('pageTitle', 'System Admin - Regras de treino')
@section('headerTitle', 'Regras de treino')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('system-admin.dashboard') }}">Voltar ao dashboard</a>
@endsection

@section('content')
    <div class="card" style="max-width: 860px;">
        <h3>Politica global de treinos</h3>
        <p>Defina quanto a plataforma consome por geracao, reaproveitamento e reativacao, alem do periodo maximo em que um treino permanece ativo.</p>

        <form method="POST" action="{{ route('system-admin.settings.workouts.update') }}" class="content-stack" style="margin-top: 12px;">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field">
                    <label for="workout_generate_cost">Creditos para gerar treino</label>
                    <input id="workout_generate_cost" name="workout_generate_cost" type="number" min="1" max="1000" value="{{ old('workout_generate_cost', $settings->get('workout.generate_cost', config('workouts.credits.generate'))) }}">
                    <small>Aplicado na geracao inicial de treino.</small>
                </div>

                <div class="field">
                    <label for="workout_reuse_cost">Creditos para reaproveitar treino</label>
                    <input id="workout_reuse_cost" name="workout_reuse_cost" type="number" min="1" max="1000" value="{{ old('workout_reuse_cost', $settings->get('workout.reuse_cost', config('workouts.credits.reuse'))) }}">
                    <small>Aplicado tanto no refazer com IA quanto no reaproveitamento sem IA.</small>
                </div>

                <div class="field">
                    <label for="workout_reactivate_cost">Creditos para reativar treino</label>
                    <input id="workout_reactivate_cost" name="workout_reactivate_cost" type="number" min="1" max="1000" value="{{ old('workout_reactivate_cost', $settings->get('workout.reactivate_cost', config('workouts.credits.reactivate'))) }}">
                    <small>Consumido quando um treino inativo volta para o estado ativo.</small>
                </div>

                <div class="field">
                    <label for="workout_active_days">Dias com treino ativo</label>
                    <input id="workout_active_days" name="workout_active_days" type="number" min="1" max="365" value="{{ old('workout_active_days', $settings->get('workout.active_days', config('workouts.active_days'))) }}">
                    <small>Ao atingir esse prazo, o treino e inativado automaticamente na proxima consulta ou acao.</small>
                </div>
            </div>

            <div class="card" style="background: #fafafa; border-style: dashed;">
                <h3 style="margin-top: 0;">Regras ativas</h3>
                <ul style="margin: 0; padding-left: 18px; display: grid; gap: 4px;">
                    <li>Gerar treino: {{ $settings->get('workout.generate_cost', config('workouts.credits.generate')) }} creditos</li>
                    <li>Reaproveitar treino com ou sem IA: {{ $settings->get('workout.reuse_cost', config('workouts.credits.reuse')) }} creditos</li>
                    <li>Reativar treino inativo: {{ $settings->get('workout.reactivate_cost', config('workouts.credits.reactivate')) }} creditos</li>
                    <li>Treino ativo por ate {{ $settings->get('workout.active_days', config('workouts.active_days')) }} dias</li>
                </ul>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Salvar regras</button>
            </div>
        </form>
    </div>
@endsection
