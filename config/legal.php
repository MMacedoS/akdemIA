<?php

$companyName = env('LEGAL_COMPANY_NAME', env('APP_NAME', 'AcademAI'));
$contactEmail = env('LEGAL_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', 'contato@academai.com.br'));

return [
    'company_name' => $companyName,
    'contact_email' => $contactEmail,
    'terms' => [
        'version' => env('LEGAL_TERMS_VERSION', '2026-05-09'),
        'updated_at' => env('LEGAL_TERMS_UPDATED_AT', '2026-05-09'),
        'default_content_html' => <<<HTML
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
<p>Solicitacoes sobre estes termos podem ser encaminhadas para <a href="mailto:{$contactEmail}">{$contactEmail}</a>.</p>
HTML,
    ],
    'privacy_policy' => [
        'version' => env('LEGAL_PRIVACY_POLICY_VERSION', '2026-05-09'),
        'updated_at' => env('LEGAL_PRIVACY_POLICY_UPDATED_AT', '2026-05-09'),
        'default_content_html' => <<<HTML
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
<p>Solicitacoes relacionadas a privacidade e protecao de dados podem ser encaminhadas para <a href="mailto:{$contactEmail}">{$contactEmail}</a>.</p>
HTML,
    ],
];
