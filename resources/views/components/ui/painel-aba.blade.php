@props(['id'])

{{-- Conteúdo de uma aba. Vai dentro de <x-ui.abas>. --}}

<div
    role="tabpanel"
    id="painel-{{ $id }}"
    aria-labelledby="aba-{{ $id }}"
    x-show="aba === '{{ $id }}'"
    x-cloak
    {{ $attributes }}
>
    {{ $slot }}
</div>
