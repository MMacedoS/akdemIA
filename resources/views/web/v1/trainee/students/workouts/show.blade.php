@extends('layouts.panel')

@section('pageTitle', 'Treino do Aluno')
@section('headerTitle', 'Treino, dieta e recomendacoes')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('trainee.students.show', $student->id) }}">Voltar ao aluno</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="card" style="max-width: 760px;">
            <h3>{{ $student->name }}</h3>
            <p>{{ $student->email }}</p>
            <p style="margin-top: 8px;">Status da requisicao: {{ strtoupper((string) ($workout->request_status ?? 'active')) }}</p>
        </div>

        @include('web.v1.workouts.board', [
            'workout' => $workout,
            'editable' => true,
            'updateRoute' => route('trainee.students.workouts.board.update', [$student->id, $workout->id]),
            'catalogSearchRoute' => route('trainee.students.workouts.catalog.search', $student->id),
            'regenerateRoute' => route('trainee.students.workouts.regenerate', [$student->id, $workout->id]),
            'reuseRoute' => route('trainee.students.workouts.reuse', [$student->id, $workout->id]),
            'activateRoute' => route('trainee.students.workouts.activate', [$student->id, $workout->id]),
            'inactivateRoute' => route('trainee.students.workouts.inactivate', [$student->id, $workout->id]),
        ])
    </div>
@endsection
