<x-mail::message>
# Ola, {{ $recipientName }}!

Seu acesso inicial ao tenant foi criado com sucesso.

<x-mail::panel>
Use as credenciais abaixo apenas no primeiro acesso e altere a senha assim que entrar no painel.
</x-mail::panel>

<x-mail::table>
| Dado | Valor |
| --- | --- |
| Tenant | {{ $tenantName }} |
| E-mail de acesso | {{ $email }} |
| Senha inicial | {{ $password }} |
| URL do tenant | {{ $tenantUrl }} |
</x-mail::table>

<x-mail::button :url="$loginUrl" color="primary">
Entrar no painel
</x-mail::button>

<x-slot:subcopy>
Se o botao nao funcionar, copie e cole este endereco no navegador: {{ $loginUrl }}
</x-slot:subcopy>
</x-mail::message>
