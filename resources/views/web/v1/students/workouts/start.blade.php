@extends('layouts.panel')

@section('pageTitle', 'Iniciar Treino')
@section('headerTitle', 'Execucao do Treino')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('students.workout.show') }}">Voltar ao plano</a>
@endsection

@section('content')
    @php
        $exerciseAssetBuilder = app(\App\Support\Workout\ExerciseAssetBuilder::class);
        $normalizeExerciseMediaUrl = static function (array $exercise): string {
            $mediaUrl = trim((string) data_get($exercise, 'exercise_media_url', ''));
            $workoutxName = trim((string) data_get($exercise, 'workoutx_name', ''));

            if ($workoutxName === '') {
                return $mediaUrl;
            }

            if ($mediaUrl === ''
                || $mediaUrl === $workoutxName
                || str_contains($mediaUrl, '/storage/exercises/')
                || (! str_contains($mediaUrl, '://')
                    && ! str_starts_with($mediaUrl, '/')
                    && preg_match('/^[a-z0-9-]+$/', $mediaUrl) === 1)
            ) {
                return route('api.workouts.exercises.media.show', ['workoutxName' => $workoutxName]);
            }

            return $mediaUrl;
        };
        $weeklyPlan = [];

        if ($workout) {
            $weeklyPlan = data_get($workout, 'workout_plan.weekly_plan', []);
            if (! is_array($weeklyPlan)) {
                $weeklyPlan = [];
            }

            $weeklyPlan = collect($weeklyPlan)
                ->map(function ($dayPlan) use ($exerciseAssetBuilder, $normalizeExerciseMediaUrl) {
                    $focus = trim((string) data_get($dayPlan, 'focus', 'Treino geral'));
                    $exercises = data_get($dayPlan, 'exercises', []);

                    if (! is_array($exercises)) {
                        $exercises = [];
                    }

                    data_set($dayPlan, 'exercises', collect($exercises)
                        ->map(function ($exercise) use ($exerciseAssetBuilder, $focus, $normalizeExerciseMediaUrl) {
                            $name = trim((string) data_get($exercise, 'name', 'Exercicio'));
                            $notes = trim((string) data_get($exercise, 'notes', ''));
                            $steps = $exerciseAssetBuilder->normalizeSteps(data_get($exercise, 'steps'), $name, $notes);

                            data_set($exercise, 'steps', $steps);
                            data_set($exercise, 'exercise_media_url', $normalizeExerciseMediaUrl(is_array($exercise) ? $exercise : []));

                            return $exercise;
                        })
                        ->all());

                    return $dayPlan;
                })
                ->all();
        }
    @endphp

    <style>
        .shell {
            grid-template-columns: 1fr;
        }

        .sidebar,
        .mobile-header,
        .sidebar-backdrop,
        .topbar,
        .platform-footer {
            display: none !important;
        }

        .panel {
            padding: 0 !important;
        }

        .workout-splash {
            min-height: 100vh;
            display: grid;
            gap: 14px;
            border-radius: 0;
            border: 0;
            padding: 16px 14px 110px;
            max-width: 560px;
            margin: 0 auto;
            background:
                radial-gradient(circle at 12% 0%, rgba(115, 103, 240, 0.22), transparent 44%),
                radial-gradient(circle at 100% 100%, rgba(40, 199, 111, 0.14), transparent 40%),
                linear-gradient(160deg, #ffffff 0%, #f8f7ff 58%, #f4fff8 100%);
        }

        .splash-day-list {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .day-chip {
            border: 1px solid #ddd9ff;
            background: #fff;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            color: #4d4788;
            cursor: pointer;
        }

        .day-chip.active {
            background: #7367f0;
            border-color: #7367f0;
            color: #fff;
        }

        .splash-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        .splash-header h3 {
            margin: 0;
            font-size: 12px;
            color: #5c5890;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .splash-exercise {
            border: 1px solid #d9d5fb;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.84);
            backdrop-filter: blur(4px);
            padding: 22px;
            display: grid;
            gap: 10px;
            box-shadow: 0 10px 24px rgba(53, 39, 140, 0.08);
        }

        .splash-exercise h2 {
            margin: 0;
            font-size: clamp(28px, 7.5vw, 52px);
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .splash-subtitle {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .splash-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .exercise-illustration-shell {
            border: 1px solid #ddd9ff;
            border-radius: 18px;
            background: #fff;
            padding: 10px;
        }

        .exercise-illustration-shell img,
        .exercise-illustration-shell svg {
            width: 100%;
            height: auto;
            display: block;
            object-fit: contain;
        }

        .exercise-steps-card {
            border: 1px solid #d9d5fb;
            border-radius: 18px;
            padding: 14px;
            background: #fff;
            display: grid;
            gap: 8px;
        }

        .exercise-steps-list {
            margin: 0;
            padding-left: 18px;
            display: grid;
            gap: 6px;
            color: #4c486f;
        }

        .exercise-visual-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .exercise-image-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 40;
            background: rgba(15, 23, 42, 0.58);
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .exercise-image-modal.open {
            display: flex;
        }

        .exercise-image-modal-card {
            width: min(760px, 100%);
            max-height: calc(100vh - 32px);
            overflow: auto;
            background: #fff;
            border: 1px solid #ddd9ff;
            border-radius: 18px;
            padding: 16px;
            display: grid;
            gap: 12px;
            box-shadow: 0 18px 48px rgba(15, 23, 42, 0.18);
        }

        .exercise-image-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .exercise-image-modal-head h4 {
            margin: 0;
            font-size: 18px;
        }

        .exercise-image-modal-figure {
            border: 1px solid #ddd9ff;
            border-radius: 18px;
            background: #f8f7ff;
            padding: 12px;
        }

        .exercise-image-modal-figure img,
        .exercise-image-modal-figure svg {
            width: 100%;
            height: auto;
            max-height: 70vh;
            display: block;
            object-fit: contain;
        }

        .metric-card {
            border: 1px solid #dbd7ff;
            border-radius: 16px;
            padding: 12px;
            background: #fff;
            display: grid;
            gap: 4px;
        }

        .metric-card span {
            color: #7a76a0;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .metric-card strong {
            font-size: clamp(28px, 4.5vw, 44px);
            line-height: 1;
            letter-spacing: -0.02em;
        }

        .splash-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .splash-controls {
            border: 1px solid #d9d5fb;
            border-radius: 18px;
            padding: 12px;
            background: #fff;
            display: grid;
            gap: 10px;
        }

        .app-lite-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 20;
            border-top: 1px solid #d9d5fb;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(6px);
            padding: 10px 12px;
        }

        .app-lite-footer-inner {
            max-width: 560px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 8px;
            align-items: center;
        }

        .app-lite-status {
            font-size: 12px;
            color: #6f6a96;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .session-time-chip {
            display: none;
            border: 1px solid #cfc8ff;
            background: #f3f1ff;
            color: #4d4788;
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 13px;
            font-weight: 700;
            min-width: 72px;
            text-align: center;
            font-variant-numeric: tabular-nums;
        }

        .splash-controls-main,
        .splash-controls-secondary {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn-ghost {
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            color: var(--text);
            font-size: 13px;
            font-weight: 600;
            padding: 10px 14px;
            cursor: pointer;
        }

        .water-banner {
            border-radius: 12px;
            border: 1px solid #bfead1;
            background: #eaf9f1;
            color: #197b46;
            font-weight: 700;
            padding: 10px 12px;
            display: none;
        }

        @media (max-width: 900px) {
            .splash-metrics {
                grid-template-columns: 1fr;
            }

            .workout-splash {
                min-height: auto;
                padding: 14px 12px 110px;
            }
        }

        @media (max-width: 480px) {
            .app-lite-footer-inner {
                grid-template-columns: 1fr 1fr;
            }

            .app-lite-status {
                grid-column: 1 / -1;
            }
        }
    </style>

    <div class="content-stack">
        @if (! $workout)
            <div class="card">
                <h3>Sem treino disponivel</h3>
                <p>Seu treino ainda nao foi gerado.</p>
            </div>
        @elseif ((string) $workout->status !== 'done' || $weeklyPlan === [])
            <div class="card">
                <h3>Treino ainda nao pronto</h3>
                <p>Seu plano esta em processamento. Aguarde a finalizacao para iniciar.</p>
            </div>
        @else
            <section class="workout-splash">
                <div class="splash-header">
                    <div>
                        <h3>Treino #{{ $workout->id }}</h3>
                        <p class="splash-subtitle" id="session-status">Aguardando inicio.</p>
                    </div>
                    <button class="btn btn-soft" type="button" id="next-exercise-btn">Proximo</button>
                </div>

                <div>
                    <p class="splash-subtitle" style="margin-bottom: 6px;">Dia</p>
                    <div class="splash-day-list" id="day-selector"></div>
                </div>

                <div class="splash-exercise">
                    <p class="splash-subtitle" id="current-day">Dia</p>
                    <h2 id="exercise-name">Exercicio</h2>
                    <p class="splash-subtitle" id="exercise-meta">Series, reps e descanso.</p>
                    <div class="exercise-visual-actions">
                        <button class="btn btn-soft" type="button" id="view-exercise-image-btn">Ver imagem</button>
                    </div>
                    <div class="exercise-illustration-shell" id="exercise-illustration"></div>
                    <div class="exercise-steps-card">
                        <p class="splash-subtitle" style="margin: 0;">Passo a passo</p>
                        <ol class="exercise-steps-list" id="exercise-steps"></ol>
                    </div>
                </div>

                <div class="splash-metrics">
                    <article class="metric-card">
                        <span>Series</span>
                        <strong><span id="series-done">0</span>/<span id="series-target">0</span></strong>
                    </article>
                    <article class="metric-card">
                        <span>Repeticoes</span>
                        <strong><span id="reps-done">0</span></strong>
                        <span>Meta: <span id="reps-target">-</span></span>
                    </article>
                    <article class="metric-card">
                        <span>Descanso</span>
                        <strong id="rest-timer">00:00</strong>
                        <span id="rest-message">Sem intervalo no momento.</span>
                    </article>
                </div>

                <div class="splash-controls">
                    <div class="splash-controls-main">
                        <button class="btn btn-soft" type="button" id="prev-exercise-btn">Anterior</button>
                        <button class="btn btn-primary" type="button" id="complete-set-btn">Concluir serie</button>
                    </div>
                    <div class="splash-controls-secondary">
                        <button class="btn-ghost" type="button" id="remove-rep-btn">- Repeticao</button>
                        <button class="btn btn-primary" type="button" id="add-rep-btn">+ Repeticao</button>
                    </div>
                </div>

                <div class="water-banner" id="water-reminder">
                    Intervalo iniciado. Beba agua antes da proxima serie.
                </div>
            </section>

            <div class="app-lite-footer">
                <div class="app-lite-footer-inner">
                    <span class="app-lite-status" id="footer-status">Pronto para iniciar.</span>
                    <button class="btn btn-primary" type="button" id="start-workout-btn">Iniciar</button>
                    <span class="session-time-chip" id="session-time-chip">00:00</span>
                    <button class="btn btn-soft" type="button" id="finish-workout-btn">Finalizar</button>
                </div>
            </div>

            <div id="exercise-image-modal" class="exercise-image-modal" aria-hidden="true">
                <div class="exercise-image-modal-card">
                    <div class="exercise-image-modal-head">
                        <div>
                            <h4 id="exercise-image-modal-title">Imagem do exercicio</h4>
                            <p id="exercise-image-modal-subtitle" class="splash-subtitle" style="margin: 4px 0 0;">Visualizacao ampliada da midia atual.</p>
                        </div>
                        <button class="btn btn-soft" type="button" id="close-exercise-image-modal-btn">Fechar</button>
                    </div>
                    <div class="exercise-image-modal-figure" id="exercise-image-modal-figure"></div>
                </div>
            </div>
        @endif
    </div>

    @if ($workout && (string) $workout->status === 'done' && $weeklyPlan !== [])
        <script>
            (function () {
                const weeklyPlan = @json($weeklyPlan, JSON_UNESCAPED_UNICODE);

                const startWorkoutButton = document.getElementById('start-workout-btn');
                const sessionTimeChipEl = document.getElementById('session-time-chip');
                const finishWorkoutButton = document.getElementById('finish-workout-btn');
                const restTimerEl = document.getElementById('rest-timer');
                const sessionStatusEl = document.getElementById('session-status');
                const footerStatusEl = document.getElementById('footer-status');
                const restMessageEl = document.getElementById('rest-message');
                const waterReminderEl = document.getElementById('water-reminder');
                const seriesDoneEl = document.getElementById('series-done');
                const seriesTargetEl = document.getElementById('series-target');
                const repsDoneEl = document.getElementById('reps-done');
                const repsTargetEl = document.getElementById('reps-target');
                const daySelectorEl = document.getElementById('day-selector');
                const currentDayEl = document.getElementById('current-day');
                const exerciseNameEl = document.getElementById('exercise-name');
                const exerciseMetaEl = document.getElementById('exercise-meta');
                const exerciseIllustrationEl = document.getElementById('exercise-illustration');
                const exerciseStepsEl = document.getElementById('exercise-steps');
                const viewExerciseImageButton = document.getElementById('view-exercise-image-btn');
                const exerciseImageModal = document.getElementById('exercise-image-modal');
                const exerciseImageModalFigure = document.getElementById('exercise-image-modal-figure');
                const exerciseImageModalTitle = document.getElementById('exercise-image-modal-title');
                const exerciseImageModalSubtitle = document.getElementById('exercise-image-modal-subtitle');
                const closeExerciseImageModalButton = document.getElementById('close-exercise-image-modal-btn');
                const addRepButton = document.getElementById('add-rep-btn');
                const removeRepButton = document.getElementById('remove-rep-btn');
                const completeSetButton = document.getElementById('complete-set-btn');
                const prevExerciseButton = document.getElementById('prev-exercise-btn');
                const nextExerciseButton = document.getElementById('next-exercise-btn');

                function renderExerciseMedia(exercise) {
                    const mediaUrl = String(exercise && exercise.exercise_media_url ? exercise.exercise_media_url : '').trim();

                    if (mediaUrl !== '') {
                        return '<img src="' + mediaUrl.replace(/"/g, '&quot;') + '" alt="Midia do exercicio" loading="lazy">';
                    }

                    return typeof exercise.illustration_svg === 'string' ? exercise.illustration_svg : '';
                }

                const state = {
                    started: false,
                    totalSeconds: 0,
                    restSeconds: 0,
                    dayIndex: 0,
                    exerciseIndex: 0,
                    setsDone: 0,
                    repsDone: 0,
                    restTimerId: null,
                    totalTimerId: null,
                };

                function closeExerciseImageModal() {
                    if (!exerciseImageModal) {
                        return;
                    }

                    exerciseImageModal.classList.remove('open');
                    exerciseImageModal.setAttribute('aria-hidden', 'true');
                    if (exerciseImageModalFigure) {
                        exerciseImageModalFigure.innerHTML = '';
                    }
                }

                function openExerciseImageModal() {
                    const exercise = currentExercise();
                    const day = currentDay();

                    if (!exercise || !exerciseImageModal || !exerciseImageModalFigure) {
                        return;
                    }

                    exerciseImageModalTitle.textContent = String(exercise.name || 'Imagem do exercicio');
                    exerciseImageModalSubtitle.textContent = 'Foco: ' + String(day && day.focus ? day.focus : 'Treino geral');
                    exerciseImageModalFigure.innerHTML = renderExerciseMedia(exercise);
                    exerciseImageModal.classList.add('open');
                    exerciseImageModal.setAttribute('aria-hidden', 'false');
                }

                function formatTime(totalSeconds) {
                    const safeSeconds = Math.max(0, Number(totalSeconds) || 0);
                    const minutes = Math.floor(safeSeconds / 60);
                    const seconds = safeSeconds % 60;

                    return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                }

                function parseRestSeconds(restValue) {
                    const text = String(restValue || '').trim().toLowerCase();
                    if (text === '') {
                        return 60;
                    }

                    let total = 0;
                    const regex = /(\d+)\s*(h|hora|horas|min|mins|m|s|sec|secs|seg|segs)/g;
                    let found = false;
                    let match;

                    while ((match = regex.exec(text)) !== null) {
                        found = true;
                        const amount = Number(match[1]) || 0;
                        const unit = match[2];

                        if (unit === 'h' || unit === 'hora' || unit === 'horas') {
                            total += amount * 3600;
                        } else if (unit === 'min' || unit === 'mins' || unit === 'm') {
                            total += amount * 60;
                        } else {
                            total += amount;
                        }
                    }

                    if (found && total > 0) {
                        return total;
                    }

                    const onlyNumber = Number(text.replace(/\D/g, ''));
                    if (Number.isFinite(onlyNumber) && onlyNumber > 0) {
                        return onlyNumber;
                    }

                    return 60;
                }

                function currentDay() {
                    return weeklyPlan[state.dayIndex] || null;
                }

                function currentExercises() {
                    const day = currentDay();
                    if (!day || !Array.isArray(day.exercises)) {
                        return [];
                    }

                    return day.exercises;
                }

                function currentExercise() {
                    const exercises = currentExercises();
                    return exercises[state.exerciseIndex] || null;
                }

                function stopRestTimer() {
                    if (state.restTimerId) {
                        clearInterval(state.restTimerId);
                        state.restTimerId = null;
                    }
                }

                function startRestTimer(seconds) {
                    stopRestTimer();

                    state.restSeconds = Math.max(0, Number(seconds) || 0);
                    restTimerEl.textContent = formatTime(state.restSeconds);

                    if (state.restSeconds <= 0) {
                        restMessageEl.textContent = 'Sem intervalo configurado.';
                        waterReminderEl.style.display = 'none';
                        return;
                    }

                    restMessageEl.textContent = 'Intervalo ativo. Beba agua.';
                    waterReminderEl.style.display = 'block';

                    state.restTimerId = setInterval(function () {
                        state.restSeconds -= 1;
                        restTimerEl.textContent = formatTime(state.restSeconds);

                        if (state.restSeconds <= 0) {
                            stopRestTimer();
                            restTimerEl.textContent = '00:00';
                            restMessageEl.textContent = 'Intervalo concluido. Volte para a proxima serie.';
                            waterReminderEl.style.display = 'none';
                        }
                    }, 1000);
                }

                function resetExerciseProgress() {
                    state.setsDone = 0;
                    state.repsDone = 0;
                    stopRestTimer();
                    state.restSeconds = 0;
                    restTimerEl.textContent = '00:00';
                    restMessageEl.textContent = 'Sem intervalo no momento.';
                    waterReminderEl.style.display = 'none';
                }

                function renderDayButtons() {
                    daySelectorEl.innerHTML = '';

                    weeklyPlan.forEach(function (day, index) {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = index === state.dayIndex ? 'day-chip active' : 'day-chip';
                        button.textContent = String(day.day || ('Dia ' + (index + 1)));

                        button.addEventListener('click', function () {
                            state.dayIndex = index;
                            state.exerciseIndex = 0;
                            resetExerciseProgress();
                            render();
                        });

                        daySelectorEl.appendChild(button);
                    });
                }

                function render() {
                    renderDayButtons();

                    const day = currentDay();
                    const exercise = currentExercise();

                    currentDayEl.textContent = String(day && day.day ? day.day : 'Dia');

                    if (!exercise) {
                        exerciseNameEl.textContent = 'Sem exercicio neste dia';
                        exerciseMetaEl.textContent = 'Selecione outro dia para continuar.';
                        exerciseIllustrationEl.innerHTML = '';
                        exerciseStepsEl.innerHTML = '';
                        if (viewExerciseImageButton) {
                            viewExerciseImageButton.disabled = true;
                        }
                        seriesDoneEl.textContent = '0';
                        seriesTargetEl.textContent = '0';
                        repsDoneEl.textContent = '0';
                        repsTargetEl.textContent = '-';
                        return;
                    }

                    const targetSets = Math.max(1, Number(exercise.sets) || 1);

                    exerciseNameEl.textContent = String(exercise.name || 'Exercicio');
                    exerciseMetaEl.textContent = 'Series: ' + targetSets + ' | Repeticoes: ' + String(exercise.reps || '-') + ' | Descanso: ' + String(exercise.rest || '60s') + ' | Foco: ' + String(day && day.focus ? day.focus : 'Treino geral');
                    exerciseIllustrationEl.innerHTML = renderExerciseMedia(exercise);
                    exerciseStepsEl.innerHTML = '';
                    if (viewExerciseImageButton) {
                        viewExerciseImageButton.disabled = String(exercise.exercise_media_url || '').trim() === '' && String(exercise.illustration_svg || '').trim() === '';
                    }

                    const steps = Array.isArray(exercise.steps) ? exercise.steps : [];
                    steps.forEach(function (step) {
                        const item = document.createElement('li');
                        item.textContent = String(step || '');
                        exerciseStepsEl.appendChild(item);
                    });

                    seriesDoneEl.textContent = String(state.setsDone);
                    seriesTargetEl.textContent = String(targetSets);
                    repsDoneEl.textContent = String(state.repsDone);
                    repsTargetEl.textContent = String(exercise.reps || '-');
                }

                function startWorkout() {
                    if (state.started) {
                        return;
                    }

                    state.started = true;
                    sessionStatusEl.textContent = 'Treino em andamento.';
                    footerStatusEl.textContent = 'Treino em andamento.';

                    if (startWorkoutButton) {
                        startWorkoutButton.style.display = 'none';
                    }

                    if (sessionTimeChipEl) {
                        sessionTimeChipEl.style.display = 'inline-flex';
                        sessionTimeChipEl.textContent = formatTime(state.totalSeconds);
                    }

                    if (! state.totalTimerId) {
                        state.totalTimerId = setInterval(function () {
                            state.totalSeconds += 1;
                            if (sessionTimeChipEl) {
                                sessionTimeChipEl.textContent = formatTime(state.totalSeconds);
                            }
                        }, 1000);
                    }
                }

                function finishWorkout() {
                    state.started = false;
                    sessionStatusEl.textContent = 'Treino finalizado. Bom trabalho!';
                    footerStatusEl.textContent = 'Finalizando sessao...';

                    stopRestTimer();
                    waterReminderEl.style.display = 'none';

                    if (state.totalTimerId) {
                        clearInterval(state.totalTimerId);
                        state.totalTimerId = null;
                    }

                    setTimeout(function () {
                        window.location.href = "{{ route('students.workout.show') }}";
                    }, 500);
                }

                function addRep() {
                    if (!state.started) {
                        sessionStatusEl.textContent = 'Clique em Iniciar treino para comecar.';
                        footerStatusEl.textContent = 'Toque em Iniciar para comecar.';
                        return;
                    }

                    state.repsDone += 1;
                    render();
                }

                function removeRep() {
                    state.repsDone = Math.max(0, state.repsDone - 1);
                    render();
                }

                function completeSet() {
                    const exercise = currentExercise();
                    if (!exercise) {
                        return;
                    }

                    if (!state.started) {
                        sessionStatusEl.textContent = 'Clique em Iniciar treino para comecar.';
                        footerStatusEl.textContent = 'Toque em Iniciar para comecar.';
                        return;
                    }

                    const targetSets = Math.max(1, Number(exercise.sets) || 1);
                    state.setsDone = Math.min(targetSets, state.setsDone + 1);
                    state.repsDone = 0;
                    render();

                    if (state.setsDone < targetSets) {
                        startRestTimer(parseRestSeconds(exercise.rest));
                        footerStatusEl.textContent = 'Intervalo ativo. Beba agua.';
                        return;
                    }

                    restMessageEl.textContent = 'Exercicio concluido. Avance para o proximo.';
                    waterReminderEl.style.display = 'none';
                    stopRestTimer();
                    footerStatusEl.textContent = 'Serie concluida. Avance para o proximo exercicio.';
                }

                function nextExercise() {
                    const exercises = currentExercises();
                    if (exercises.length === 0) {
                        return;
                    }

                    if (state.exerciseIndex < exercises.length - 1) {
                        state.exerciseIndex += 1;
                    } else if (state.dayIndex < weeklyPlan.length - 1) {
                        state.dayIndex += 1;
                        state.exerciseIndex = 0;
                    }

                    resetExerciseProgress();
                    render();
                }

                function prevExercise() {
                    if (state.exerciseIndex > 0) {
                        state.exerciseIndex -= 1;
                    } else if (state.dayIndex > 0) {
                        state.dayIndex -= 1;
                        const previousDay = currentExercises();
                        state.exerciseIndex = Math.max(0, previousDay.length - 1);
                    }

                    resetExerciseProgress();
                    render();
                }

                startWorkoutButton.addEventListener('click', startWorkout);
                finishWorkoutButton.addEventListener('click', finishWorkout);
                if (viewExerciseImageButton) {
                    viewExerciseImageButton.addEventListener('click', openExerciseImageModal);
                }
                if (closeExerciseImageModalButton) {
                    closeExerciseImageModalButton.addEventListener('click', closeExerciseImageModal);
                }
                if (exerciseImageModal) {
                    exerciseImageModal.addEventListener('click', function (event) {
                        if (event.target === exerciseImageModal) {
                            closeExerciseImageModal();
                        }
                    });
                }
                addRepButton.addEventListener('click', addRep);
                removeRepButton.addEventListener('click', removeRep);
                completeSetButton.addEventListener('click', completeSet);
                nextExerciseButton.addEventListener('click', nextExercise);
                prevExerciseButton.addEventListener('click', prevExercise);

                restTimerEl.textContent = formatTime(state.restSeconds);
                if (sessionTimeChipEl) {
                    sessionTimeChipEl.textContent = formatTime(state.totalSeconds);
                }
                render();
            })();
        </script>
    @endif
@endsection
