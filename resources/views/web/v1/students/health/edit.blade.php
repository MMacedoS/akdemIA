@extends('layouts.panel')

@section('pageTitle', 'Minha Saude')
@section('headerTitle', 'Dados de Saude e Preferencias')

@section('content')
    @php
        $availableHourOptions = ['06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00'];
        $availableDayOptions = [
            'seg' => 'Segunda',
            'ter' => 'Terca',
            'qua' => 'Quarta',
            'qui' => 'Quinta',
            'sex' => 'Sexta',
            'sab' => 'Sabado',
            'dom' => 'Domingo',
        ];
        $availableHoursValue = old('available_hours', is_array(optional($user->preference)->available_hours) ? implode(', ', $user->preference->available_hours) : '');
        $calculatedImc = null;
        $heightValue = old('height', $user->height);
        $weightValue = old('weight', $user->weight);
        if (is_numeric($heightValue) && is_numeric($weightValue) && (float) $heightValue > 0) {
            $calculatedImc = round(((float) $weightValue) / (((float) $heightValue) * ((float) $heightValue)), 2);
        }
        $imcValue = old('imc', optional($user->physicalData)->imc ?? $calculatedImc);
    @endphp
    <div class="content-stack">
        <div class="card">
            <h3>Atualizar dados de saude</h3>
            <p>Preencha os campos para manter suas recomendacoes sempre atualizadas.</p>

            <form method="POST" action="{{ route('students.health.update') }}" class="content-stack" style="margin-top: 16px;">
                @csrf
                @method('PUT')

                <div class="form-shell">
                    <section class="form-section">
                        <h4>Dados de cadastro</h4>
                        <p>Esses dados alimentam o calculo automatico do IMC.</p>
                        <div class="form-grid">
                            <div class="field">
                                <label for="birth_date">Data de nascimento</label>
                                <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', $user->birth_date?->format('Y-m-d')) }}">
                            </div>

                            <div class="field">
                                <label for="height">Altura</label>
                                <input id="height" name="height" type="number" step="0.01" min="0.5" max="3" value="{{ $heightValue }}" placeholder="Ex: 1.75" inputmode="decimal">
                            </div>

                            <div class="field">
                                <label for="weight">Peso</label>
                                <input id="weight" name="weight" type="number" step="0.01" min="20" max="500" value="{{ $weightValue }}" placeholder="Ex: 72.40" inputmode="decimal">
                            </div>
                        </div>
                    </section>

                    <section class="form-section">
                        <h4>Dados fisicos</h4>
                        <p>Informe medidas atuais para melhorar a personalizacao.</p>
                        <div class="form-grid">
                            <div class="field">
                                <label for="body_fat_percentage">Percentual de gordura</label>
                                <input id="body_fat_percentage" name="body_fat_percentage" type="number" step="0.01" value="{{ old('body_fat_percentage', optional($user->physicalData)->body_fat_percentage) }}" placeholder="Ex: 18.50">
                            </div>

                            <div class="field">
                                <label for="imc">IMC</label>
                                <input id="imc" name="imc" type="number" step="0.01" value="{{ $imcValue }}" placeholder="Ex: 24.20" readonly>
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="activity_level">Nivel de atividade</label>
                                <input id="activity_level" name="activity_level" type="text" value="{{ old('activity_level', optional($user->physicalData)->activity_level) }}" placeholder="Ex: Leve, moderado ou intenso">
                            </div>
                        </div>
                    </section>

                    <section class="form-section">
                        <h4>Dados medicos</h4>
                        <p>Escreva com clareza para garantir recomendacoes seguras.</p>
                        <div class="form-grid">
                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="injuries">Lesoes</label>
                                <textarea id="injuries" name="injuries" placeholder="Informe por texto livre">{{ old('injuries', optional($user->medicalData)->injuries) }}</textarea>
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="diseases">Doencas</label>
                                <textarea id="diseases" name="diseases">{{ old('diseases', optional($user->medicalData)->diseases) }}</textarea>
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="medications">Medicacoes</label>
                                <textarea id="medications" name="medications">{{ old('medications', optional($user->medicalData)->medications) }}</textarea>
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="restrictions">Restricoes</label>
                                <textarea id="restrictions" name="restrictions">{{ old('restrictions', optional($user->medicalData)->restrictions) }}</textarea>
                            </div>
                        </div>
                    </section>

                    <section class="form-section">
                        <h4>Preferencias</h4>
                        <p>Para listas, separe os itens por virgula.</p>
                        <div class="form-grid">
                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="preferred_foods">Alimentos preferidos</label>
                                <input id="preferred_foods" name="preferred_foods" type="text" value="{{ old('preferred_foods', is_array(optional($user->preference)->preferred_foods) ? implode(', ', $user->preference->preferred_foods) : '') }}" placeholder="Ex: arroz, frango, iogurte">
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="disliked_foods">Alimentos que evita</label>
                                <input id="disliked_foods" name="disliked_foods" type="text" value="{{ old('disliked_foods', is_array(optional($user->preference)->disliked_foods) ? implode(', ', $user->preference->disliked_foods) : '') }}" placeholder="Ex: pimenta, frituras">
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="drinks">Bebidas</label>
                                <input id="drinks" name="drinks" type="text" value="{{ old('drinks', is_array(optional($user->preference)->drinks) ? implode(', ', $user->preference->drinks) : '') }}" placeholder="Ex: agua, cafe, suco">
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="available_hours">Horarios disponiveis</label>
                                <input id="available_hours" name="available_hours" type="text" value="{{ $availableHoursValue }}" placeholder="Ex: seg 19:00, qui 07:00" readonly>
                                <small>Selecione o dia e marque os horarios desejados para montar o campo automaticamente.</small>
                            </div>

                            <div class="field">
                                <label for="available_hours_day_selector">Dia da semana</label>
                                <select id="available_hours_day_selector">
                                    @foreach ($availableDayOptions as $dayValue => $dayLabel)
                                        <option value="{{ $dayValue }}">{{ $dayLabel }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label>Horarios</label>
                                <div id="available_hours_checkbox_group" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(92px, 1fr)); gap:10px;">
                                    @foreach ($availableHourOptions as $hourOption)
                                        <label style="display:flex; align-items:center; gap:8px; padding:10px 12px; border:1px solid #d9deea; border-radius:12px; background:#fff;">
                                            <input type="checkbox" value="{{ $hourOption }}" data-available-hour>
                                            <span>{{ $hourOption }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="training_frequency">Frequencia de treino</label>
                                <select id="training_frequency" name="training_frequency">
                                    <option value="">Selecione</option>
                                    @for ($frequency = 1; $frequency <= 6; $frequency++)
                                        <option value="{{ $frequency }}x por semana" @selected(old('training_frequency', optional($user->preference)->training_frequency) === $frequency . 'x por semana')>{{ $frequency }}x por semana</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Salvar dados de saude</button>
                    <a class="btn btn-soft" href="{{ route('students.dashboard') }}">Voltar</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const heightInput = document.getElementById('height');
            const weightInput = document.getElementById('weight');
            const imcInput = document.getElementById('imc');
            const availableHoursInput = document.getElementById('available_hours');
            const daySelector = document.getElementById('available_hours_day_selector');
            const hourCheckboxes = Array.from(document.querySelectorAll('[data-available-hour]'));

            const selectedSlots = new Set(
                (availableHoursInput?.value || '')
                    .split(',')
                    .map((value) => value.trim())
                    .filter((value) => value !== '')
            );

            const syncImc = () => {
                const height = parseFloat((heightInput?.value || '').replace(',', '.'));
                const weight = parseFloat((weightInput?.value || '').replace(',', '.'));

                if (!Number.isFinite(height) || !Number.isFinite(weight) || height <= 0 || weight <= 0) {
                    if (imcInput) {
                        imcInput.value = '';
                    }
                    return;
                }

                if (imcInput) {
                    imcInput.value = (weight / (height * height)).toFixed(2);
                }
            };

            const syncAvailableHoursInput = () => {
                if (!availableHoursInput) {
                    return;
                }

                availableHoursInput.value = Array.from(selectedSlots).join(', ');
            };

            const syncCheckboxesForDay = () => {
                const selectedDay = daySelector?.value;

                hourCheckboxes.forEach((checkbox) => {
                    const slotValue = `${selectedDay} ${checkbox.value}`;
                    checkbox.checked = selectedSlots.has(slotValue);
                });
            };

            heightInput?.addEventListener('input', syncImc);
            weightInput?.addEventListener('input', syncImc);
            daySelector?.addEventListener('change', syncCheckboxesForDay);

            hourCheckboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    const selectedDay = daySelector?.value;
                    const slotValue = `${selectedDay} ${checkbox.value}`;

                    if (checkbox.checked) {
                        selectedSlots.add(slotValue);
                    } else {
                        selectedSlots.delete(slotValue);
                    }

                    syncAvailableHoursInput();
                });
            });

            syncImc();
            syncCheckboxesForDay();
            syncAvailableHoursInput();
        })();
    </script>
@endsection
