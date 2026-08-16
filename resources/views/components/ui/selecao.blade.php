@props([
    /* Quanto o campo ocupa na grade: 25, 50, 75 ou 100. */
    'largura' => '100',
    'nome',
    'rotulo',
    /* array<valor, rotulo> */
    'opcoes' => [],
    'valor' => null,
    'ajuda' => null,
    'obrigatorio' => true,
    'vazio' => 'Selecione…',
])

@php
    $id = $attributes->get('id', $nome);
    $temErro = $errors->has($nome);
    $selecionado = old($nome, $valor);
    $descritores = trim(implode(' ', array_filter([
        $ajuda ? $id.'-ajuda' : null,
        $temErro ? $id.'-erro' : null,
    ]))) ?: null;
@endphp

<x-ui.grupo-campo :nome="$nome" :rotulo="$rotulo" :ajuda="$ajuda" :obrigatorio="$obrigatorio" :campo-id="$id" :largura="$largura">
    <div class="relative">
        <select
            id="{{ $id }}"
            name="{{ $nome }}"
            @if ($obrigatorio) required @endif
            @if ($descritores) aria-describedby="{{ $descritores }}" @endif
            @if ($temErro) aria-invalid="true" @endif
            {{ $attributes->except('id')->merge([
                'class' => 'min-h-toque w-full appearance-none rounded-md bg-superficie py-2 pr-10 pl-3.5 '
                    .'text-base text-texto border transition-colors '
                    .'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco '
                    .($temErro ? 'border-vencido-forte' : 'border-borda-forte'),
            ]) }}
        >
            @if ($vazio !== false)
                <option value="">{{ $vazio }}</option>
            @endif

            @foreach ($opcoes as $opcaoValor => $opcaoRotulo)
                <option value="{{ $opcaoValor }}" @selected((string) $selecionado === (string) $opcaoValor)>
                    {{ $opcaoRotulo }}
                </option>
            @endforeach
        </select>

        <svg viewBox="0 0 20 20" class="pointer-events-none absolute top-1/2 right-3 size-5 -translate-y-1/2 text-texto-mudo"
             fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true" focusable="false">
            <path d="m6 8 4 4 4-4" />
        </svg>
    </div>
</x-ui.grupo-campo>
