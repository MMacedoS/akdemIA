<x-mail::message>
# Ola, {{ $recipientName }}!

Voce recebeu uma nova comunicacao da equipe da plataforma.

<x-mail::table>
| Campo | Valor |
| --- | --- |
| Assunto | {{ $subjectLine }} |
| Categoria | Comunicacao administrativa |
</x-mail::table>

<x-mail::panel>
{!! nl2br(e($messageBody)) !!}
</x-mail::panel>

<x-slot:subcopy>
Esta mensagem foi enviada automaticamente pela plataforma para manter seu cadastro informado.
</x-slot:subcopy>
</x-mail::message>
