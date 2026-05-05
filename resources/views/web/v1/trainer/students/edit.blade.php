@extends('layouts.panel')

@section('pageTitle', 'Editar dados do aluno')
@section('headerTitle', 'Atualizar saude do aluno')

@section('content')
    <div class="content-stack">
        <div class="card">
            <h3>{{ $student->name }}</h3>
            <p>{{ $student->email }}</p>
            <p style="margin-top: 6px;">Atualize dados fisicos, medicos e preferencias com foco em acompanhamento.</p>
        </div>

        <div class="card">
            <form method="POST" action="{{ route('trainer.students.update', $student->id) }}" class="content-stack">
                @csrf
                @method('PUT')

                <div class="form-shell">
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
                                <input id="imc" name="imc" type="number" step="0.01" value="{{ old('imc', optional($student->physicalData)->imc) }}" placeholder="Ex: 24.20">
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
                                <input id="available_hours" name="available_hours" type="text" value="{{ old('available_hours', is_array(optional($student->preference)->available_hours) ? implode(', ', $student->preference->available_hours) : '') }}" placeholder="Ex: seg 19h, qua 7h, sex 18h">
                            </div>

                            <div class="field" style="grid-column: 1 / -1;">
                                <label for="training_frequency">Frequencia de treino</label>
                                <input id="training_frequency" name="training_frequency" type="text" value="{{ old('training_frequency', optional($student->preference)->training_frequency) }}" placeholder="Ex: 4x por semana">
                            </div>
                        </div>
                    </section>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Salvar alteracoes</button>
                    <a class="btn btn-soft" href="{{ route('trainer.students.show', $student->id) }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
