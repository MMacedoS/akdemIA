@extends('layouts.panel')

@section('pageTitle', 'Detalhe do Admin')
@section('headerTitle', 'Detalhe do Admin')

@section('headerAction')
    <a class="btn btn-primary" href="{{ route('admin.users.edit', $user->id) }}">Editar</a>
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
