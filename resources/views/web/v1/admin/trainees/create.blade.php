@extends('layouts.panel')

@section('pageTitle', 'Novo Trainee')
@section('headerTitle', 'Criar Trainee')

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.trainees.store') }}" class="content-stack">
            @csrf
            @include('web.v1.admin.trainees._form')
        </form>
    </div>
@endsection
