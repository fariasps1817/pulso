@props([
    /* Quanto o campo ocupa na grade: 25, 50, 75 ou 100. */
    'largura' => '100',
    'nome',
    'rotulo',
    'tipo' => 'text',
    'valor' => null,
    'ajuda' => null,
    'obrigatorio' => true,
    'autocomplete' => null,
])

{{-- Campo de texto comum. Para CPF, telefone, CEP e data use x-ui.campo-mascara. --}}

@php
    // "tipo" e "type" valem o mesmo — ver a explicação em x-ui.botao.
    $tipo = $attributes->get('type', $tipo);
    $attributes = $attributes->except('type');

    $id = $attributes->get('id', $nome);
    $temErro = $errors->has($nome);
    $descritores = trim(implode(' ', array_filter([
        $ajuda ? $id.'-ajuda' : null,
        $temErro ? $id.'-erro' : null,
    ]))) ?: null;
@endphp

<x-ui.grupo-campo :nome="$nome" :rotulo="$rotulo" :ajuda="$ajuda" :obrigatorio="$obrigatorio" :campo-id="$id" :largura="$largura">
    <input
        type="{{ $tipo }}"
        id="{{ $id }}"
        name="{{ $nome }}"
        value="{{ old($nome, $valor) }}"
        @if ($obrigatorio) required @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($descritores) aria-describedby="{{ $descritores }}" @endif
        @if ($temErro) aria-invalid="true" @endif
        {{ $attributes->except('id')->merge([
            'class' => 'min-h-toque w-full rounded-md bg-superficie px-3.5 text-base text-texto '
                .'border transition-colors placeholder:text-texto-mudo '
                .'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco '
                .($temErro ? 'border-vencido-forte' : 'border-borda-forte'),
        ]) }}
    >
</x-ui.grupo-campo>
