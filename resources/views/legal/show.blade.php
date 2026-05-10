@extends('layouts.legal')

@section('title', $document['title'])

@section('content')
    <header class="legal-header">
        <span class="legal-kicker">{{ $kicker }}</span>
        <h1>{{ $document['title'] }}</h1>
        <p class="legal-meta">Versao {{ $document['version'] }}. Ultima atualizacao em {{ $document['effective_date'] }}.</p>
        <p>{{ $intro }}</p>
    </header>

    {!! $document['content_html'] !!}

    <p>
        Para duvidas sobre este documento, entre em contato em
        <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.
    </p>
@endsection
