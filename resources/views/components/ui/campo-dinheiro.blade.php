@props([
    /* Quanto o campo ocupa na grade: 25, 50, 75 ou 100. */
    'largura' => '100',
    'nome',
    'rotulo',
    'valor' => null,
    'ajuda' => null,
    'obrigatorio' => true,
])

{{--
    Valor em reais. Digita-se da direita para a esquerda, como em caixa:
    "12990" vira "129,90". Por isso usa a diretiva x-dinheiro em vez do
    x-mask posicional.

    Alinhado a direita e em tabular-nums, para os valores baterem em coluna
    quando aparecem numa lista.
--}}

@php
    $id = $attributes->get('id', $nome);
    $temErro = $errors->has($nome);
    $descritores = trim(implode(' ', array_filter([
        $ajuda ? $id.'-ajuda' : null,
        $temErro ? $id.'-erro' : null,
    ]))) ?: null;

    $valorInicial = old($nome, $valor);
    $valorFormatado = $valorInicial === null || $valorInicial === ''
        ? ''
        : number_format((float) $valorInicial, 2, ',', '.');
@endphp

<x-ui.grupo-campo :nome="$nome" :rotulo="$rotulo" :ajuda="$ajuda" :obrigatorio="$obrigatorio" :campo-id="$id" :largura="$largura">
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-texto-mudo"
              aria-hidden="true">R$</span>

        <input
            type="text"
            id="{{ $id }}"
            name="{{ $nome }}"
            value="{{ $valorFormatado }}"
            inputmode="numeric"
            maxlength="14"
            placeholder="0,00"
            {{-- Raiz x-data obrigatória: sem ela o Alpine não inicializa a
                 diretiva e o campo fica sem formatação. --}}
            x-data
            x-dinheiro
            data-somente-digitos
            @if ($obrigatorio) required @endif
            @if ($descritores) aria-describedby="{{ $descritores }}" @endif
            @if ($temErro) aria-invalid="true" @endif
            {{ $attributes->except('id')->merge([
                'class' => 'numeros min-h-toque w-full rounded-md bg-superficie py-2 pr-3.5 pl-11 '
                    .'text-right text-base text-texto border transition-colors placeholder:text-texto-mudo '
                    .'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco '
                    .($temErro ? 'border-vencido-forte' : 'border-borda-forte'),
            ]) }}
        >
    </div>
</x-ui.grupo-campo>
