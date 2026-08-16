@props([
    'href' => null,
    'destrutivo' => false,
    'icone' => null,
    /* "submit" quando o item envia um formulário — o caso do Sair. */
    'tipo' => 'button',
])

@php
    /*
     * As propriedades deste projeto são em português, mas o atributo HTML é
     * "type". Escrever type="submit" — o reflexo natural de quem conhece HTML —
     * fazia o valor cair nos atributos extras, e o botão saía com DOIS type:
     * o do componente primeiro, o de quem chamou depois. O navegador usa o
     * primeiro, então o botão simplesmente não enviava o formulário. Sem erro,
     * sem aviso: o "Sair" só não fazia nada.
     *
     * Aqui as duas grafias passam a valer, e o atributo duplicado é removido.
     */
    $tipo = $attributes->get('type', $tipo);
    $attributes = $attributes->except('type');

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
    <button type="{{ $tipo }}" role="menuitem" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icone)
            <svg viewBox="0 0 20 20" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="{{ $icone }}" />
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif
