@extends('layouts.panel')

@section('pageTitle', 'Novo Aluno')
@section('headerTitle', 'Criar Aluno')

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.students.store') }}" class="content-stack">
            @csrf
            @include('web.v1.admin.students._form')
        </form>
    </div>
@endsection
