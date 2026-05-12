@extends('layouts.legal')

@section('title', 'Excluir conta')

@section('content')
    <header class="legal-header">
        <span class="legal-kicker">Privacidade</span>
        <h1>Solicitar exclusao da conta</h1>
        <p>
            Informe o e-mail da sua conta AcademAI para receber um link seguro de confirmacao.
            A exclusao remove o cadastro e os dados vinculados a ele na plataforma.
        </p>
        <p class="legal-meta">
            Se voce nao tiver acesso ao e-mail da conta, solicite apoio em <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
        </p>
    </header>

    @if (session('status'))
        <div style="margin-bottom:16px;padding:14px 16px;border-radius:14px;background:#def7ec;color:#166534;font-weight:600;">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div style="margin-bottom:16px;padding:14px 16px;border-radius:14px;background:#fee2e2;color:#991b1b;font-weight:600;">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('drop-account.store') }}" style="display:grid;gap:16px;">
        @csrf

        <div>
            <label for="email" style="display:block;margin-bottom:8px;font-weight:700;">E-mail da conta</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ $prefilledEmail }}"
                required
                autocomplete="email"
                spellcheck="false"
                style="width:100%;padding:14px 16px;border:1px solid #cbd5d1;border-radius:14px;font:inherit;box-sizing:border-box;"
            >
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
            <button
                type="submit"
                style="border:none;border-radius:999px;padding:14px 22px;background:#0f766e;color:#fff;font:inherit;font-weight:700;cursor:pointer;"
            >
                Enviar link de exclusao
            </button>

            <a href="{{ route('login') }}" style="font-weight:700;">Entrar na plataforma</a>
        </div>
    </form>

    <section style="margin-top:28px;">
        <h2>Como funciona</h2>
        <ul>
            <li>Voce informa o e-mail da conta.</li>
            <li>Enviamos um link temporario de confirmacao para esse e-mail.</li>
            <li>Ao confirmar, a conta e os dados vinculados sao removidos da plataforma.</li>
        </ul>
    </section>
@endsection
