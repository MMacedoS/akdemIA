@extends('layouts.panel')

@section('pageTitle', 'Editar Trainee')
@section('headerTitle', 'Editar Trainee')

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.trainees.update', $trainee->id) }}" class="content-stack">
            @csrf
            @method('PUT')
            @include('web.v1.admin.trainees._form', ['trainee' => $trainee])
        </form>
    </div>
@endsection
