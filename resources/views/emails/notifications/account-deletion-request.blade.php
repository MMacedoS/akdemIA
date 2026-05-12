<x-mail::message>
# Ola, {{ $recipientName }}

Recebemos uma solicitacao para excluir sua conta na AcademAI.

<x-mail::button :url="$confirmationUrl">
Confirmar exclusao da conta
</x-mail::button>

Se voce nao fez esta solicitacao, ignore esta mensagem. Nenhuma alteracao sera feita sem a confirmacao final no link acima.

Em caso de duvida, fale com {{ $contactEmail }}.

Obrigado,<br>
{{ config('app.name') }}
</x-mail::message>
