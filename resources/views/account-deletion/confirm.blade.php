@extends('layouts.legal')

@section('title', 'Confirmar exclusao da conta')

@section('content')
    <header class="legal-header">
        <span class="legal-kicker">Confirmacao</span>
        <h1>Confirmar exclusao da conta</h1>
        <p>
            Esta acao remove a conta vinculada a <strong>{{ $user->email }}</strong> e os dados associados na plataforma.
            Depois da confirmacao, a exclusao nao podera ser desfeita.
        </p>
        <p class="legal-meta">
            Se voce nao reconhece esta solicitacao, ignore este link ou fale com <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
        </p>
    </header>

    <form method="POST" action="{{ $signedActionUrl }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
        @csrf

        <button
            type="submit"
            style="border:none;border-radius:999px;padding:14px 22px;background:#991b1b;color:#fff;font:inherit;font-weight:700;cursor:pointer;"
        >
            Excluir conta agora
        </button>

        <a href="{{ route('drop-account.create') }}" style="font-weight:700;">Cancelar</a>
    </form>
@endsection
