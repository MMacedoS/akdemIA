@extends('layouts.auth')

@section('title', 'Aceite de Termos')

@section('content')
    <style>
        .policy-onboarding {
            display: grid;
            gap: 18px;
        }

        .policy-panel {
            padding: 18px;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(16, 37, 33, 0.98), rgba(55, 104, 88, 0.92));
            color: #f4f7f5;
        }

        .policy-panel p {
            margin: 0;
            color: rgba(244, 247, 245, 0.88);
            line-height: 1.7;
        }

        .policy-links {
            display: grid;
            gap: 10px;
        }

        .policy-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            border-radius: 999px;
            border: 1px solid rgba(24, 54, 46, 0.14);
            background: #f2f6f4;
            color: #18362e;
            text-decoration: none;
            font-weight: 700;
        }
    </style>

    <div class="policy-onboarding">
        <h1 class="auth-title">Confirme os documentos legais</h1>
        <p class="auth-subtitle">No primeiro acesso com Google, precisamos registrar seu aceite dos termos de uso e da politica de privacidade antes de continuar.</p>

        <section class="policy-panel">
            <p>Abra os documentos abaixo, revise o conteudo atual e confirme o aceite para liberar seu acesso web.</p>
        </section>

        <div class="policy-links">
            <a href="{{ $termsUrl }}" target="_blank" rel="noopener noreferrer" class="policy-link">Abrir Termos de Uso</a>
            <a href="{{ $privacyUrl }}" target="_blank" rel="noopener noreferrer" class="policy-link">Abrir Politica de Privacidade</a>
        </div>

        <form method="POST" action="{{ route('onboarding.policies.update') }}">
            @csrf

            <label class="remember-row" for="terms_of_use">
                <input id="terms_of_use" type="checkbox" name="terms_of_use" value="1" {{ old('terms_of_use') ? 'checked' : '' }} required>
                <span>Li e aceito os Termos de Uso.</span>
            </label>

            <label class="remember-row" for="privacy_policy">
                <input id="privacy_policy" type="checkbox" name="privacy_policy" value="1" {{ old('privacy_policy') ? 'checked' : '' }} required>
                <span>Li e aceito a Politica de Privacidade.</span>
            </label>

            <button type="submit" class="auth-btn">Continuar</button>
        </form>
    </div>
@endsection
