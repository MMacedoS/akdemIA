@extends('layouts.panel')

@section('pageTitle', 'Perfil do Aluno')
@section('headerTitle', 'Visao do Trainer')

@section('headerAction')
    <div class="actions">
        <button id="open-generate-modal-btn" class="btn btn-primary" type="button">Gerar treino + dieta</button>
        <a class="btn btn-soft" href="{{ route('trainer.students.edit', $student->id) }}">Editar saude</a>
    </div>
@endsection

@section('content')
    <div class="content-stack">
        <div class="card">
            <h3>{{ $student->name }}</h3>
            <p>{{ $student->email }}</p>
            <p style="margin-top: 8px;">Objetivo: {{ $student->goal ?: 'Nao informado' }}</p>
        </div>

        <div class="stats">
            <div class="card">
                <h3>Dados fisicos</h3>
                <p>Altura: {{ $student->height ?? 'Nao informado' }}</p>
                <p>Peso: {{ $student->weight ?? 'Nao informado' }}</p>
                <p>% Gordura: {{ optional($student->physicalData)->body_fat_percentage ?? 'Nao informado' }}</p>
                <p>IMC: {{ optional($student->physicalData)->imc ?? 'Nao informado' }}</p>
            </div>

            <div class="card">
                <h3>Dados medicos</h3>
                <p>Lesoes: {{ optional($student->medicalData)->injuries ?? 'Nao informado' }}</p>
                <p>Doencas: {{ optional($student->medicalData)->diseases ?? 'Nao informado' }}</p>
                <p>Medicacoes: {{ optional($student->medicalData)->medications ?? 'Nao informado' }}</p>
                <p>Restricoes: {{ optional($student->medicalData)->restrictions ?? 'Nao informado' }}</p>
            </div>

            <div class="card">
                <h3>Preferencias</h3>
                <p>Alimentos preferidos: {{ is_array(optional($student->preference)->preferred_foods) ? implode(', ', $student->preference->preferred_foods) : 'Nao informado' }}</p>
                <p>Alimentos evitados: {{ is_array(optional($student->preference)->disliked_foods) ? implode(', ', $student->preference->disliked_foods) : 'Nao informado' }}</p>
                <p>Bebidas: {{ is_array(optional($student->preference)->drinks) ? implode(', ', $student->preference->drinks) : 'Nao informado' }}</p>
                <p>Frequencia: {{ optional($student->preference)->training_frequency ?? 'Nao informado' }}</p>
            </div>
        </div>

        <div class="card">
            <h3>Ultimos treinos</h3>
            <div class="cards-grid" style="margin-top: 12px;">
                @forelse ($workouts as $workout)
                    <article class="user-card">
                        <h4>Treino #{{ $workout->id }}</h4>
                        <p>Status: {{ strtoupper((string) $workout->status) }}</p>
                        <p>Requisicao: {{ strtoupper((string) ($workout->request_status ?? 'active')) }}</p>
                        <p>Criado em: {{ optional($workout->created_at)?->format('d/m/Y H:i') }}</p>
                        <div class="actions">
                            <a class="btn btn-soft" href="{{ route('trainer.students.workouts.show', [$student->id, $workout->id]) }}">Visualizar board</a>
                            @if ((string) $workout->status === 'error')
                                <form method="POST" action="{{ route('trainer.students.workouts.retry', [$student->id, $workout->id]) }}">
                                    @csrf
                                    <button class="btn btn-primary" type="submit">Reenviar transacao</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="card">
                        <p>Nenhum treino encontrado para este aluno.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div id="generate-workout-modal" class="plan-refazer-modal" aria-hidden="true">
        <div class="plan-refazer-modal-card">
            <div class="plan-refazer-modal-head">
                <h4>Gerar treino + dieta</h4>
                <button id="close-generate-modal-btn" type="button" class="btn btn-soft">Fechar</button>
            </div>

            <form method="POST" action="{{ route('trainer.students.workouts.generate', $student->id) }}" class="content-stack">
                @csrf
                <div class="field" style="max-width: 100%;">
                    <label for="generate_adjustment_request">Observacoes para IA (opcional)</label>
                    <textarea id="generate_adjustment_request" name="adjustment_request" rows="3" maxlength="1500" placeholder="Ex: priorizar treino com baixo impacto no joelho.">{{ old('adjustment_request') }}</textarea>
                </div>
                <div class="actions">
                    <button class="btn btn-primary" type="submit">Iniciar geracao</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .plan-refazer-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(19, 18, 32, 0.48);
            z-index: 120;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .plan-refazer-modal.open {
            display: flex;
        }

        .plan-refazer-modal-card {
            width: min(640px, 100%);
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 14px;
            display: grid;
            gap: 10px;
        }

        .plan-refazer-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .plan-refazer-modal-head h4 {
            margin: 0;
            font-size: 15px;
        }
    </style>

    <script>
        (function () {
            const openButton = document.getElementById('open-generate-modal-btn');
            const closeButton = document.getElementById('close-generate-modal-btn');
            const modal = document.getElementById('generate-workout-modal');

            function closeModal() {
                if (!modal) {
                    return;
                }

                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
            }

            if (openButton && modal) {
                openButton.addEventListener('click', function () {
                    modal.classList.add('open');
                    modal.setAttribute('aria-hidden', 'false');
                });
            }

            if (closeButton) {
                closeButton.addEventListener('click', closeModal);
            }

            if (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        closeModal();
                    }
                });
            }
        })();
    </script>
@endsection
