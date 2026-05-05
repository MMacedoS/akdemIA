@extends('layouts.panel')

@section('pageTitle', 'Aluno')
@section('headerTitle', 'Perfil completo do aluno')

@section('headerAction')
    <a class="btn btn-primary" href="{{ route('admin.students.edit', $user->id) }}">Editar aluno</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="card">
            <div class="toolbar">
                <div>
                    <h3>{{ $user->name }}</h3>
                    <p>{{ $user->email }}</p>
                    <p>Trainee vinculado: {{ $assignedTrainee?->name ?? 'nao vinculado' }}</p>
                </div>
            </div>
        </div>

        <div class="stats">
            <div class="card">
                <h3>Dados pessoais</h3>
                <p>Genero: {{ $user->gender ?: 'Nao informado' }}</p>
                <p>Nascimento: {{ optional($user->birth_date)?->format('d/m/Y') ?: 'Nao informado' }}</p>
                <p>Objetivo: {{ $user->goal ?: 'Nao informado' }}</p>
            </div>

            <div class="card">
                <h3>Dados fisicos</h3>
                <p>Altura: {{ $user->height ?? 'Nao informado' }}</p>
                <p>Peso: {{ $user->weight ?? 'Nao informado' }}</p>
                <p>% Gordura: {{ optional($user->physicalData)->body_fat_percentage ?? 'Nao informado' }}</p>
                <p>IMC: {{ optional($user->physicalData)->imc ?? 'Nao informado' }}</p>
            </div>

            <div class="card">
                <h3>Dados medicos</h3>
                <p>Lesoes: {{ optional($user->medicalData)->injuries ?? 'Nao informado' }}</p>
                <p>Doencas: {{ optional($user->medicalData)->diseases ?? 'Nao informado' }}</p>
                <p>Restricoes: {{ optional($user->medicalData)->restrictions ?? 'Nao informado' }}</p>
                <p>Medicacoes: {{ optional($user->medicalData)->medications ?? 'Nao informado' }}</p>
            </div>
        </div>

        <div class="card">
            <h3>Preferencias</h3>
            <p>Comidas preferidas: {{ is_array(optional($user->preference)->preferred_foods) ? implode(', ', $user->preference->preferred_foods) : 'Nao informado' }}</p>
            <p>Comidas nao gostadas: {{ is_array(optional($user->preference)->disliked_foods) ? implode(', ', $user->preference->disliked_foods) : 'Nao informado' }}</p>
            <p>Bebidas: {{ is_array(optional($user->preference)->drinks) ? implode(', ', $user->preference->drinks) : 'Nao informado' }}</p>
            <p>Horarios disponiveis: {{ is_array(optional($user->preference)->available_hours) ? implode(', ', $user->preference->available_hours) : 'Nao informado' }}</p>
            <p>Frequencia de treino: {{ optional($user->preference)->training_frequency ?? 'Nao informado' }}</p>
        </div>

        <div class="card">
            <h3>Treinos gerados</h3>
            <div class="cards-grid" style="margin-top: 12px;">
                @forelse ($workouts as $workout)
                    <article class="user-card">
                        <h4>Treino #{{ $workout->id }}</h4>
                        <p>Status: {{ strtoupper((string) $workout->status) }}</p>
                        <p>Criado em: {{ optional($workout->created_at)?->format('d/m/Y H:i') }}</p>
                    </article>
                @empty
                    <div class="card">
                        <p>Nenhum treino encontrado.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
