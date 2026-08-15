@props([
    /** completo = capsula + logotipo | simbolo = so a capsula | reduzido = <= 24px */
    'variante' => 'completo',
    /** Rotulo acessivel. Use null quando houver texto equivalente ao lado. */
    'rotulo' => 'Pulso',
])

{{--
    Logotipo do Pulso, desenhado em vetor (nao e fonte — o guia de marca proibe
    redigitar). A capsula fica em Mare fixo porque e constante da marca; o
    logotipo segue --cor-marca, que ja muda por tema.

    Respiro obrigatorio: metade da altura da capsula em todos os lados. Quem usa
    o componente e responsavel por essa margem.

    Abaixo de 96px de largura, use variante="simbolo".
    Abaixo de 24px, use variante="reduzido".
--}}

@php
    $atributosSvg = $attributes->merge([
        'xmlns' => 'http://www.w3.org/2000/svg',
        'fill' => 'none',
    ]);

    $acessibilidade = $rotulo
        ? ['role' => 'img', 'aria-label' => $rotulo]
        : ['aria-hidden' => 'true', 'focusable' => 'false'];
@endphp

@if ($variante === 'reduzido')
    <svg viewBox="0 0 72 88" {{ $atributosSvg->merge($acessibilidade) }}>
        <rect x="0" y="8" width="72" height="72" rx="22" fill="var(--cor-marca-capsula)" />
        <path d="M 15 50 L 29 50 L 37 28 L 45 58 L 57 40"
              stroke="var(--cor-marca-sol)" stroke-width="10"
              stroke-linecap="round" stroke-linejoin="round" />
    </svg>
@elseif ($variante === 'simbolo')
    <svg viewBox="0 0 72 88" {{ $atributosSvg->merge($acessibilidade) }}>
        <rect x="0" y="8" width="72" height="72" rx="22" fill="var(--cor-marca-capsula)" />
        <path d="M 14 50 H 26 L 33 32 L 41 60 L 48 40 H 58"
              stroke="var(--cor-marca-sol)" stroke-width="8"
              stroke-linecap="round" stroke-linejoin="round" />
    </svg>
@else
    <svg viewBox="0 0 240 88" {{ $atributosSvg->merge($acessibilidade) }}>
        <rect x="0" y="8" width="72" height="72" rx="22" fill="var(--cor-marca-capsula)" />
        <path d="M 14 50 H 26 L 33 32 L 41 60 L 48 40 H 58"
              stroke="var(--cor-marca-sol)" stroke-width="8"
              stroke-linecap="round" stroke-linejoin="round" />
        <path d="M 92 28 V 60 M 92 28 H 103 A 9 9 0 0 1 103 46 H 92 M 120 28 V 47 A 13 13 0 0 0 146 47 V 28 M 154 28 V 60 H 170 M 194 36 A 8 8 0 1 0 186 44 A 8 8 0 1 1 178 52 M 202 44 A 16 16 0 1 1 234 44 A 16 16 0 1 1 202 44"
              stroke="var(--cor-marca)" stroke-width="5.2"
              stroke-linecap="round" stroke-linejoin="round" />
    </svg>
@endif
