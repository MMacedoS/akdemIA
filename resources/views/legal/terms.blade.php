@extends('layouts.legal')

@section('title', 'Termos de Uso')

@section('content')
    <header class="legal-header">
        <span class="legal-kicker">Documento legal</span>
        <h1>Termos de Uso</h1>
        <p class="legal-meta">Versao {{ config('legal.terms.version') }}. Ultima atualizacao em {{ config('legal.terms.updated_at') }}.</p>
        <p>Estes Termos de Uso regulam o acesso e a utilizacao da plataforma {{ config('legal.company_name') }}, incluindo site, painel web e aplicacoes integradas.</p>
    </header>

    <h2>1. Aceite</h2>
    <p>Ao criar uma conta ou utilizar a plataforma, voce declara que leu e concorda com estes Termos de Uso e com a Politica de Privacidade vigente.</p>

    <h2>2. Finalidade da plataforma</h2>
    <p>A plataforma oferece recursos de gestao de treino, acompanhamento de alunos, organizacao de informacoes de saude e comunicacao entre usuarios vinculados aos respectivos perfis e tenants.</p>

    <h2>3. Cadastro e responsabilidade do usuario</h2>
    <ul>
        <li>Fornecer dados verdadeiros, completos e atualizados.</li>
        <li>Manter a confidencialidade das credenciais de acesso.</li>
        <li>Utilizar a plataforma em conformidade com a legislacao aplicavel, inclusive a LGPD.</li>
        <li>Nao inserir conteudos ilicitos, ofensivos ou que violem direitos de terceiros.</li>
    </ul>

    <h2>4. Uso de dados e documentos de saude</h2>
    <p>Informacoes cadastrais, dados fisicos, preferencias e outros registros sensiveis so devem ser informados quando necessarios para a prestacao do servico. O tratamento desses dados segue a Politica de Privacidade e as bases legais aplicaveis.</p>

    <h2>5. Limites da plataforma</h2>
    <p>As funcionalidades da plataforma apoiam a rotina de acompanhamento e organizacao, mas nao substituem avaliacao medica, nutricional ou profissional individualizada quando exigida pela condicao do usuario.</p>

    <h2>6. Suspensao e encerramento</h2>
    <p>O acesso pode ser suspenso ou encerrado em caso de uso indevido, fraude, violacao destes termos ou necessidade tecnica e operacional devidamente justificada.</p>

    <h2>7. Propriedade intelectual</h2>
    <p>Os elementos da plataforma, incluindo identidade visual, estrutura, software e conteudos proprios, permanecem protegidos pela legislacao aplicavel. O uso e restrito aos fins permitidos nestes termos.</p>

    <h2>8. Contato</h2>
    <p>Solicitacoes sobre estes termos podem ser encaminhadas para <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>
@endsection
