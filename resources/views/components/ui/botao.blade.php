@props([
    'variante' => 'primario',   // primario | secundario | fantasma | sol
    'tamanho' => 'medio',       // medio | grande
    'href' => null,
    'tipo' => 'submit',
])

{{--
    Botao base do Pulso.

    Altura minima sempre >= 44px (--alvo-toque): a equipe opera no celular, com
    o polegar. O texto diz o que acontece ("Registrar pagamento"), nunca "OK".
--}}

@php
    /*
     * "tipo" e "type" valem o mesmo. Sem isto, quem escreve type="submit" —
     * o reflexo de quem conhece HTML — produz um botão com dois atributos
     * type, e o navegador usa o primeiro. O botão não envia o formulário e
     * nada indica o motivo.
     */
    $tipo = $attributes->get('type', $tipo);
    $attributes = $attributes->except('type');

    $base = 'inline-flex items-center justify-center gap-2 rounded-md font-medium '
        .'transition-colors duration-150 select-none '
        .'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco '
        .'disabled:opacity-50 disabled:pointer-events-none';

    $tamanhos = [
        'medio' => 'min-h-toque px-5 text-base',
        'grande' => 'min-h-toque py-3.5 px-7 text-lg',
    ];

    $variantes = [
        'primario' => 'bg-acao text-acao-texto hover:bg-acao-hover',
        'secundario' => 'bg-superficie text-texto border border-borda-forte hover:bg-superficie-2',
        'fantasma' => 'text-acao hover:bg-acao-sutil',
        // Amarelo Sol so passa no contraste com texto escuro por cima.
        'sol' => 'bg-sol-400 text-mare-950 hover:bg-sol-300',
    ];

    $classes = trim($base.' '.($tamanhos[$tamanho] ?? $tamanhos['medio']).' '.($variantes[$variante] ?? $variantes['primario']));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $tipo }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
