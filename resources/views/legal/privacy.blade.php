@extends('layouts.legal')

@section('title', 'Politica de Privacidade')

@section('content')
    <header class="legal-header">
        <span class="legal-kicker">LGPD</span>
        <h1>Politica de Privacidade</h1>
        <p class="legal-meta">Versao {{ config('legal.privacy_policy.version') }}. Ultima atualizacao em {{ config('legal.privacy_policy.updated_at') }}.</p>
        <p>Esta Politica de Privacidade descreve como {{ config('legal.company_name') }} coleta, utiliza, armazena e protege dados pessoais em conformidade com a Lei Geral de Protecao de Dados Pessoais.</p>
    </header>

    <h2>1. Dados tratados</h2>
    <p>Podemos tratar dados cadastrais, dados de autenticacao, dados de uso da plataforma, informacoes de perfil, preferencias e, quando fornecidos pelo usuario ou pelo responsavel pelo cadastro, dados relacionados a saude e condicionamento fisico.</p>

    <h2>2. Finalidades do tratamento</h2>
    <ul>
        <li>Criar e manter contas de usuario.</li>
        <li>Permitir autenticacao e operacao segura do app e do painel.</li>
        <li>Gerar, organizar e acompanhar rotinas de treino e vinculacoes entre aluno e profissional.</li>
        <li>Atender obrigacoes legais, regulatorias e de seguranca.</li>
        <li>Registrar evidencias de consentimento e de operacoes relevantes na plataforma.</li>
    </ul>

    <h2>3. Bases legais</h2>
    <p>O tratamento pode ocorrer com fundamento na execucao de contrato, cumprimento de obrigacao legal, exercicio regular de direitos, legitimo interesse e, quando aplicavel, consentimento do titular ou de seu responsavel.</p>

    <h2>4. Compartilhamento</h2>
    <p>Os dados podem ser compartilhados com profissionais vinculados ao usuario, prestadores de servico essenciais para hospedagem, autenticacao, pagamentos, comunicacao e operacao da plataforma, sempre dentro do limite necessario para a finalidade informada.</p>

    <h2>5. Retencao e seguranca</h2>
    <p>Adotamos medidas administrativas e tecnicas razoaveis para proteger os dados pessoais. Os dados sao mantidos pelo tempo necessario para cumprimento das finalidades, obrigacoes legais, auditoria e defesa em processos administrativos ou judiciais.</p>

    <h2>6. Direitos do titular</h2>
    <p>Nos termos da LGPD, o titular pode solicitar confirmacao de tratamento, acesso, correcao, anonimização quando aplicavel, portabilidade, informacao sobre compartilhamentos e revisao de consentimentos, observado o que a legislacao permitir para cada caso.</p>

    <h2>7. Contato do encarregado ou canal de privacidade</h2>
    <p>Solicitacoes relacionadas a privacidade e protecao de dados podem ser encaminhadas para <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>
@endsection
