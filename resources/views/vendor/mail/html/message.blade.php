@php
    $brandName = config('mail.from.name') ?: config('app.name');
@endphp

<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $brandName }}
</x-mail::header>
</x-slot:header>

{!! $slot !!}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
{{ $brandName }}<br>
{{ parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.url') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
