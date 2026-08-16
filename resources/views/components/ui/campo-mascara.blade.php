@props([
    /* Quanto o campo ocupa na grade: 25, 50, 75 ou 100. */
    'largura' => '100',
    'nome',
    'rotulo',
    /* cpf | cnpj | celular | telefone | cep | data */
    'formato',
    'valor' => null,
    'ajuda' => null,
    'obrigatorio' => true,
    'autocomplete' => null,
])

{{--
    Campo com formato conhecido. Traz sempre os tres juntos:

      mascara visual  +  limite rigido  +  teclado numerico

    O `inputmode="numeric"` e o que faz subir o teclado de numeros no celular.
    Sem ele a recepcionista digita CPF no teclado completo, mirando em teclas
    pequenas com o aluno esperando.

    O campo de DATA e de proposito um texto com mascara, nunca <input type="date">:
    o seletor nativo do celular obriga a rolar dezenas de telas para chegar a
    uma data de nascimento de 1974. Digitando, sao dois segundos.
--}}

@php
    $formatos = [
        'cpf' => ['mascara' => '999.999.999-99', 'limite' => 14, 'exemplo' => '000.000.000-00', 'rotuloTeclado' => 'numeric'],
        'cnpj' => ['mascara' => '99.999.999/9999-99', 'limite' => 18, 'exemplo' => '00.000.000/0000-00', 'rotuloTeclado' => 'numeric'],
        'celular' => ['mascara' => '(99) 99999-9999', 'limite' => 15, 'exemplo' => '(00) 00000-0000', 'rotuloTeclado' => 'tel'],
        'telefone' => ['mascara' => '(99) 9999-9999', 'limite' => 14, 'exemplo' => '(00) 0000-0000', 'rotuloTeclado' => 'tel'],
        'cep' => ['mascara' => '99999-999', 'limite' => 9, 'exemplo' => '00000-000', 'rotuloTeclado' => 'numeric'],
        'data' => ['mascara' => '99/99/9999', 'limite' => 10, 'exemplo' => 'dd/mm/aaaa', 'rotuloTeclado' => 'numeric'],
    ];

    $atual = $formatos[$formato] ?? $formatos['cpf'];

    $id = $attributes->get('id', $nome);
    $temErro = $errors->has($nome);
    $descritores = trim(implode(' ', array_filter([
        $ajuda ? $id.'-ajuda' : null,
        $temErro ? $id.'-erro' : null,
    ]))) ?: null;
@endphp

<x-ui.grupo-campo :nome="$nome" :rotulo="$rotulo" :ajuda="$ajuda" :obrigatorio="$obrigatorio" :campo-id="$id" :largura="$largura">
    <input
        type="text"
        id="{{ $id }}"
        name="{{ $nome }}"
        value="{{ old($nome, $valor) }}"
        inputmode="{{ $atual['rotuloTeclado'] }}"
        maxlength="{{ $atual['limite'] }}"
        placeholder="{{ $atual['exemplo'] }}"
        {{-- O x-data vazio não é sobra: o Alpine só inicializa elementos
             dentro de uma raiz x-data, e sem ele a máscara nunca é aplicada. --}}
        x-data
        x-mask="{{ $atual['mascara'] }}"
        data-somente-digitos
        @if ($obrigatorio) required @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($descritores) aria-describedby="{{ $descritores }}" @endif
        @if ($temErro) aria-invalid="true" @endif
        {{ $attributes->except('id')->merge([
            'class' => 'numeros min-h-toque w-full rounded-md bg-superficie px-3.5 text-base text-texto '
                .'border transition-colors placeholder:text-texto-mudo '
                .'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco '
                .($temErro ? 'border-vencido-forte' : 'border-borda-forte'),
        ]) }}
    >
</x-ui.grupo-campo>
