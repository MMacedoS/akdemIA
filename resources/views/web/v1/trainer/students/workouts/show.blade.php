@extends('layouts.panel')

@section('pageTitle', 'Treino do Aluno')
@section('headerTitle', 'Treino, dieta e recomendacoes')

@section('headerAction')
    <a class="btn btn-soft" href="{{ route('trainer.students.show', $student->id) }}">Voltar ao aluno</a>
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
            'updateRoute' => route('trainer.students.workouts.board.update', [$student->id, $workout->id]),
            'regenerateRoute' => route('trainer.students.workouts.regenerate', [$student->id, $workout->id]),
            'reuseRoute' => route('trainer.students.workouts.reuse', [$student->id, $workout->id]),
            'activateRoute' => route('trainer.students.workouts.activate', [$student->id, $workout->id]),
            'inactivateRoute' => route('trainer.students.workouts.inactivate', [$student->id, $workout->id]),
        ])
    </div>
@endsection
