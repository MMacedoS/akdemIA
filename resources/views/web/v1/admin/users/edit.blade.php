@extends('layouts.panel')

@section('pageTitle', 'Editar Admin')
@section('headerTitle', 'Editar Admin')

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.users.update', $user->id) }}" class="content-stack">
            @csrf
            @method('PUT')
            @include('web.v1.admin.users._form', ['user' => $user])
        </form>
    </div>
@endsection
