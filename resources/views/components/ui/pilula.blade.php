@props([
    'estado' => 'pago',   // pago | avencer | vencido | frequencia
    'texto' => null,
])

{{--
    Pilula de estado — o componente que o guia de marca mais protege.

    Regra que nao se quebra: estado nunca e comunicado so por cor. Sempre os
    tres juntos: icone + texto + cor. Um pontinho colorido sozinho esta
    proibido, porque exclui quem nao distingue as cores e some na impressao em
    preto e branco.

    "Baixa frequencia" e roxo de proposito: e risco de evasao, nao problema de
    caixa. Misturar com o vermelho de inadimplencia faria a gestao tratar as
    duas coisas do mesmo jeito.
--}}

@php
    $estados = [
        'pago' => [
            'texto' => 'Em dia',
            'classes' => 'bg-pago-fundo text-pago-texto border-pago-borda',
            'icone' => 'M4 10.5 8 14.5 16 6',
        ],
        'avencer' => [
            'texto' => 'A vencer',
            'classes' => 'bg-avencer-fundo text-avencer-texto border-avencer-borda',
            'icone' => 'M10 5v5.5l3.5 2M10 2.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15Z',
        ],
        'vencido' => [
            'texto' => 'Vencida',
            'classes' => 'bg-vencido-fundo text-vencido-texto border-vencido-borda',
            'icone' => 'M10 5.5v6M10 14.5v.5M10 2.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15Z',
        ],
        'frequencia' => [
            'texto' => 'Baixa frequência',
            'classes' => 'bg-freq-fundo text-freq-texto border-freq-borda',
            'icone' => 'M10 4v11m0 0-4.5-4.5M10 15l4.5-4.5',
        ],
    ];

    $atual = $estados[$estado] ?? $estados['pago'];

    // Precedencia: conteudo do slot vence a prop, que vence o rotulo padrao.
    $rotulo = $slot->isNotEmpty() ? trim($slot) : ($texto ?? $atual['texto']);
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 rounded-pill border px-2.5 py-1 '
        .'text-sm font-medium whitespace-nowrap '.$atual['classes'],
]) }}>
    <svg viewBox="0 0 20 20" class="size-4 shrink-0" fill="none" stroke="currentColor"
         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
         aria-hidden="true" focusable="false">
        <path d="{{ $atual['icone'] }}" />
    </svg>
    {{ $rotulo }}
</span>
