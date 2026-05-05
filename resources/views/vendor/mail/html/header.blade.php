@props(['url'])

@php
    $brandName = config('mail.from.name') ?: config('app.name');
@endphp

<tr>
<td class="header">
<a href="{{ $url }}" class="mail-brand" target="_blank" rel="noopener">
<span class="mail-brand-mark">{{ strtoupper(substr((string) $brandName, 0, 1)) }}</span>
<span class="mail-brand-copy">
<span class="mail-brand-name">{{ $brandName }}</span>
<span class="mail-brand-tag">Painel de operacao e acompanhamento</span>
</span>
</a>
</td>
</tr>
