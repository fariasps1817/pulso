@props([
    'href' => null,
    'destrutivo' => false,
    'icone' => null,
])

@php
    $classes = 'flex min-h-toque w-full items-center gap-3 px-4 text-left text-base transition-colors '
        .'focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-foco '
        .($destrutivo
            ? 'text-vencido-texto hover:bg-vencido-fundo'
            : 'text-texto hover:bg-superficie-2');
@endphp

@if ($href)
    <a href="{{ $href }}" role="menuitem" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icone)
            <svg viewBox="0 0 20 20" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="{{ $icone }}" />
            </svg>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="button" role="menuitem" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icone)
            <svg viewBox="0 0 20 20" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="{{ $icone }}" />
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif
