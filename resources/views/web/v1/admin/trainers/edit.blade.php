@extends('layouts.panel')

@section('pageTitle', 'Editar Trainer')
@section('headerTitle', 'Editar Trainer')

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.trainers.update', $user->id) }}" class="content-stack">
            @csrf
            @method('PUT')
            @include('web.v1.admin.trainers._form', ['user' => $user])
        </form>
    </div>
@endsection
