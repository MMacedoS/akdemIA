@extends('layouts.panel')

@section('pageTitle', 'Detalhe do Trainer')
@section('headerTitle', 'Detalhe do Trainer')

@section('headerAction')
    <a class="btn btn-primary" href="{{ route('admin.trainers.edit', $user->id) }}">Editar</a>
@endsection

@section('content')
    <div class="card content-stack">
        <div>
            <h3>{{ $user->name }}</h3>
            <p>{{ $user->email }}</p>
        </div>

        <div>
            <strong>Objetivo:</strong>
            <p>{{ $user->goal ?: 'Nao informado' }}</p>
        </div>
    </div>
@endsection
