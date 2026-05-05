@extends('layouts.panel')

@section('pageTitle', 'Editar Aluno')
@section('headerTitle', 'Editar Aluno')

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.students.update', $user->id) }}" class="content-stack">
            @csrf
            @method('PUT')
            @include('web.v1.admin.students._form', ['user' => $user])
        </form>
    </div>
@endsection
