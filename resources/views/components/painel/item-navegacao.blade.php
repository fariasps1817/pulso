@props([
    'href' => '#',
    'rotulo',
    'icone',
    'ativo' => false,
])

{{--
    Item da barra lateral. Recolhida, resta só o ícone — e é por isso que o
    rótulo continua no DOM em `sr-only` e no `title`: leitor de tela e ponteiro
    parado continuam sabendo o que é.
--}}

<a
    href="{{ $href }}"
    @if ($ativo) aria-current="page" @endif
    :title="recolhida ? '{{ $rotulo }}' : null"
    {{ $attributes->merge([
        'class' => 'group flex min-h-toque items-center gap-3 rounded-md px-3 transition-colors '
            .'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco '
            .($ativo
                ? 'bg-acao-sutil font-medium text-acao'
                : 'text-texto-2 hover:bg-superficie-2 hover:text-texto'),
    ]) }}
>
    <svg viewBox="0 0 20 20" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="{{ $icone }}" />
    </svg>

    <span class="truncate" :class="recolhida ? 'lg:sr-only' : ''">{{ $rotulo }}</span>
</a>
