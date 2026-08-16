@props([
    /* Quanto o campo ocupa na grade: 25, 50, 75 ou 100. */
    'largura' => '100',
    'nome',
    'rotulo',
    'valor' => null,
    'ajuda' => null,
    'obrigatorio' => false,
    'linhas' => 4,
])

@php
    $id = $attributes->get('id', $nome);
    $temErro = $errors->has($nome);
    $descritores = trim(implode(' ', array_filter([
        $ajuda ? $id.'-ajuda' : null,
        $temErro ? $id.'-erro' : null,
    ]))) ?: null;
@endphp

<x-ui.grupo-campo :nome="$nome" :rotulo="$rotulo" :ajuda="$ajuda" :obrigatorio="$obrigatorio" :campo-id="$id" :largura="$largura">
    <textarea
        id="{{ $id }}"
        name="{{ $nome }}"
        rows="{{ $linhas }}"
        @if ($obrigatorio) required @endif
        @if ($descritores) aria-describedby="{{ $descritores }}" @endif
        @if ($temErro) aria-invalid="true" @endif
        {{ $attributes->except('id')->merge([
            'class' => 'w-full rounded-md bg-superficie px-3.5 py-2.5 text-base text-texto '
                .'border transition-colors placeholder:text-texto-mudo '
                .'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco '
                .($temErro ? 'border-vencido-forte' : 'border-borda-forte'),
        ]) }}
    >{{ old($nome, $valor) }}</textarea>
</x-ui.grupo-campo>
