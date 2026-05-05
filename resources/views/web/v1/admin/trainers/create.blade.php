@extends('layouts.panel')

@section('pageTitle', 'Novo Trainer')
@section('headerTitle', 'Criar Trainer')

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.trainers.store') }}" class="content-stack">
            @csrf
            @include('web.v1.admin.trainers._form')
        </form>
    </div>
@endsection
