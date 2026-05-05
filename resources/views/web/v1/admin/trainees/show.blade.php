@extends('layouts.panel')

@section('pageTitle', 'Detalhes do Trainee')
@section('headerTitle', 'Detalhes do Trainee')

@section('headerAction')
    <a class="btn btn-primary" href="{{ route('admin.trainees.edit', $trainee->id) }}">Editar trainee</a>
@endsection

@section('content')
    <div class="content-stack">
        <div class="card">
            <h3>{{ $trainee->name }}</h3>
            <p>{{ $trainee->email }}</p>
            <p>Status: {{ $trainee->is_active ? 'Ativo' : 'Inativo' }}</p>
            <p>Perfil: {{ $trainee->profile_type }}</p>
        </div>
    </div>
@endsection
