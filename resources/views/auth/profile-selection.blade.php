@extends('layouts.auth')

@section('title', 'Escolha de Perfil')

@section('content')
    <style>
        .profile-onboarding {
            display: grid;
            gap: 20px;
        }

        .profile-onboarding-grid {
            display: grid;
            gap: 14px;
        }

        .profile-choice {
            position: relative;
        }

        .profile-choice input {
            position: absolute;
            opacity: 0;
            inset: 0;
        }

        .profile-choice-card {
            display: grid;
            gap: 8px;
            padding: 18px;
            border-radius: 22px;
            border: 1px solid rgba(20, 32, 30, 0.12);
            background:
                radial-gradient(circle at top right, rgba(116, 162, 146, 0.16), transparent 34%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(243, 247, 244, 0.95));
            transition: border-color .2s ease, transform .2s ease, box-shadow .2s ease;
            cursor: pointer;
        }

        .profile-choice input:checked + .profile-choice-card {
            border-color: #2f6e5c;
            box-shadow: 0 14px 32px rgba(47, 110, 92, 0.18);
            transform: translateY(-1px);
        }

        .profile-kicker {
            font-size: 12px;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #5c6b63;
            font-weight: 700;
        }

        .profile-title {
            font-size: 22px;
            line-height: 1;
            margin: 0;
            color: #13211e;
        }

        .profile-copy {
            margin: 0;
            color: #4c5a54;
            line-height: 1.6;
        }

        .profile-note {
            margin: 0;
            padding: 14px 16px;
            border-radius: 18px;
            background: #f3f6f4;
            color: #50615a;
            font-size: 14px;
            line-height: 1.6;
        }
    </style>

    <div class="profile-onboarding">
        <h1 class="auth-title">Como voce vai usar a plataforma?</h1>
        <p class="auth-subtitle">Escolha o perfil inicial do seu acesso web. Isso define seu primeiro fluxo depois do login.</p>

        <form method="POST" action="{{ route('onboarding.profile.update') }}" class="profile-onboarding-grid">
            @csrf

            <label class="profile-choice" for="profile_type_admin">
                <input id="profile_type_admin" type="radio" name="profile_type" value="admin" {{ $selectedProfile === 'admin' ? 'checked' : '' }}>
                <span class="profile-choice-card">
                    <span class="profile-kicker">Contratante</span>
                    <strong class="profile-title">Tenant Admin</strong>
                    <span class="profile-copy">Para quem vai administrar um espaco, contratar a operacao e depois acessar usuarios, creditos e configuracoes do tenant.</span>
                </span>
            </label>

            <label class="profile-choice" for="profile_type_trainer">
                <input id="profile_type_trainer" type="radio" name="profile_type" value="trainer" {{ $selectedProfile === 'trainer' ? 'checked' : '' }}>
                <span class="profile-choice-card">
                    <span class="profile-kicker">Profissional</span>
                    <strong class="profile-title">Trainer</strong>
                    <span class="profile-copy">Para personal, treinador ou profissional que quer operar sua propria landing e atender alunos direto pela plataforma.</span>
                </span>
            </label>

            <label class="profile-choice" for="profile_type_student">
                <input id="profile_type_student" type="radio" name="profile_type" value="student" {{ $selectedProfile === 'student' ? 'checked' : '' }}>
                <span class="profile-choice-card">
                    <span class="profile-kicker">Aluno</span>
                    <strong class="profile-title">Student</strong>
                    <span class="profile-copy">Para quem vai usar o ambiente pessoal de treino, acompanhar evolucao, dados fisicos e rotinas como aluno.</span>
                </span>
            </label>

            <p class="profile-note">Na API publica o cadastro continua sendo estudante. Esta escolha vale apenas para o fluxo web autenticado.</p>

            <button type="submit" class="auth-btn">Continuar</button>
        </form>
    </div>
@endsection
