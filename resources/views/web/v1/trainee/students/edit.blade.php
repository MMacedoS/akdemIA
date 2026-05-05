@extends('layouts.panel')

@section('pageTitle', 'Editar dados do aluno')
@section('headerTitle', 'Atualizar saude do aluno')

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
        $availableHoursValue = old('available_hours', is_array(optional($student->preference)->available_hours) ? implode(', ', $student->preference->available_hours) : '');
        $calculatedImc = null;
        $heightValue = old('height', $student->height);
        $weightValue = old('weight', $student->weight);
        if (is_numeric($heightValue) && is_numeric($weightValue) && (float) $heightValue > 0) {
            $calculatedImc = round(((float) $weightValue) / (((float) $heightValue) * ((float) $heightValue)), 2);
        }
        $imcValue = old('imc', optional($student->physicalData)->imc ?? $calculatedImc);
    @endphp
    <div class="content-stack">
        <div class="card">
            <h3>{{ $student->name }}</h3>
            <p>{{ $student->email }}</p>
            <p style="margin-top: 6px;">Atualize dados fisicos, medicos e preferencias com foco em acompanhamento.</p>
        </div>

        <div class="card">
            <form method="POST" action="{{ route('trainee.students.update', $student->id) }}" class="content-stack">
                @csrf
                @method('PUT')

                <div class="form-shell">
                    <section class="form-section">
                        <h4>Cadastro do aluno</h4>
                        <p>Dados principais do aluno para identificacao e calculos automaticos.</p>
                        <div class="form-grid">
                            <div class="field">
                                <label for="birth_date">Data de nascimento</label>
                                <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', $student->birth_date?->format('Y-m-d')) }}">
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
                        <p>Indicadores corporais e nivel de atividade atual.</p>
                        <div class="form-grid">
                            <div class="field">
                                <label for="body_fat_percentage">Percentual de gordura</label>
                                <input id="body_fat_percentage" name="body_fat_percentage" type="number" step="0.01" value="{{ old('body_fat_percentage', optional($student->physicalData)->body_fat_percentage) }}" placeholder="Ex: 18.50">
                            </div>

                            <div class="field">
                                <label for="imc">IMC</label>
                                <input id="imc" name="imc" type="number" step="0.01" value="{{ $imcValue }}" placeholder="Ex: 24.20" readonly>
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="activity_level">Nivel de atividade</label>
                                <input id="activity_level" name="activity_level" type="text" value="{{ old('activity_level', optional($student->physicalData)->activity_level) }}" placeholder="Ex: Moderado, 4x por semana">
                            </div>
                        </div>
                    </section>

                    <section class="form-section">
                        <h4>Dados medicos</h4>
                        <p>Historico clinico relevante para treinos e dieta.</p>
                        <div class="form-grid">
                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="injuries">Lesoes</label>
                                <textarea id="injuries" name="injuries" placeholder="Descreva lesoes, dor recorrente ou observacoes">{{ old('injuries', optional($student->medicalData)->injuries) }}</textarea>
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="diseases">Doencas</label>
                                <textarea id="diseases" name="diseases" placeholder="Informe condicoes como hipertensao, diabetes, etc.">{{ old('diseases', optional($student->medicalData)->diseases) }}</textarea>
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="medications">Medicacoes</label>
                                <textarea id="medications" name="medications" placeholder="Medicamentos em uso continuo ou eventual">{{ old('medications', optional($student->medicalData)->medications) }}</textarea>
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="restrictions">Restricoes</label>
                                <textarea id="restrictions" name="restrictions" placeholder="Limites de movimento, restricoes alimentares ou clinicas">{{ old('restrictions', optional($student->medicalData)->restrictions) }}</textarea>
                            </div>
                        </div>
                    </section>

                    <section class="form-section">
                        <h4>Preferencias</h4>
                        <p>Use virgula para separar itens de listas.</p>
                        <div class="form-grid">
                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="preferred_foods">Alimentos preferidos</label>
                                <input id="preferred_foods" name="preferred_foods" type="text" value="{{ old('preferred_foods', is_array(optional($student->preference)->preferred_foods) ? implode(', ', $student->preference->preferred_foods) : '') }}" placeholder="Ex: frango, batata-doce, iogurte">
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="disliked_foods">Alimentos evitados</label>
                                <input id="disliked_foods" name="disliked_foods" type="text" value="{{ old('disliked_foods', is_array(optional($student->preference)->disliked_foods) ? implode(', ', $student->preference->disliked_foods) : '') }}" placeholder="Ex: brocolis, pimenta">
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="drinks">Bebidas</label>
                                <input id="drinks" name="drinks" type="text" value="{{ old('drinks', is_array(optional($student->preference)->drinks) ? implode(', ', $student->preference->drinks) : '') }}" placeholder="Ex: agua, cafe, cha">
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="available_hours">Horarios disponiveis</label>
                                <input id="available_hours" name="available_hours" type="text" value="{{ $availableHoursValue }}" placeholder="Ex: seg 19:00, qua 07:00, sex 18:00" readonly>
                                <small>Selecione um dia e marque os horarios para montar o campo automaticamente.</small>
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
                                        <option value="{{ $frequency }}x por semana" @selected(old('training_frequency', optional($student->preference)->training_frequency) === $frequency . 'x por semana')>{{ $frequency }}x por semana</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Salvar alteracoes</button>
                    <a class="btn btn-soft" href="{{ route('trainee.students.show', $student->id) }}">Cancelar</a>
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
