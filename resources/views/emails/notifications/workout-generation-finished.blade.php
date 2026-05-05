<x-mail::message>
# Ola, {{ $recipientName }}!

@if ($status === 'done')
A geracao do seu treino foi concluida.
@else
A geracao do seu treino encontrou um problema.
@endif

<x-mail::table>
| Campo | Valor |
| --- | --- |
| Treino | #{{ $workoutId }} |
| Status | {{ $statusLabel }} |
</x-mail::table>

<x-mail::panel>
{!! nl2br(e($messageBody)) !!}
</x-mail::panel>

<x-slot:subcopy>
Esta notificacao tambem fica registrada no painel para consulta posterior.
</x-slot:subcopy>
</x-mail::message>
