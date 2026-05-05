@extends('layouts.panel')

@section('pageTitle', 'Meu Treino')
@section('headerTitle', 'Plano de Treino Atual')

@section('headerAction')
    <a class="btn btn-primary" href="{{ route('students.workout.start') }}">Iniciar meu treino</a>
@endsection

@section('content')
    <div class="content-stack">
        @if (! $workout)
            <div class="card">
                <h3>Sem treino gerado</h3>
                <p>Seu plano ainda nao foi gerado. Mantenha seus dados de saude e perfil atualizados.</p>
            </div>
        @else
            @include('web.v1.workouts.board', [
                'workout' => $workout,
                'activateRoute' => route('students.workout.activate', $workout->id),
                'inactivateRoute' => route('students.workout.inactivate', $workout->id),
            ])
        @endif
    </div>
@endsection
