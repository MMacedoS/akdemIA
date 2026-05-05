@extends('layouts.panel')

@section('pageTitle', 'Novo Admin')
@section('headerTitle', 'Criar Admin')

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.users.store') }}" class="content-stack">
            @csrf
            @include('web.v1.admin.users._form')
        </form>
    </div>
@endsection
