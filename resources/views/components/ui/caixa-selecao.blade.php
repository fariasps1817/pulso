@props([
    'nome',
    'rotulo',
    'valor' => '1',
    'marcado' => false,
    'ajuda' => null,
])

{{-- A area clicavel e o rotulo inteiro, com altura minima de toque: acertar
     uma caixa de 16px com o polegar, em pe no balcao, nao acontece. --}}

@php
    $id = $attributes->get('id', $nome);
    $estaMarcado = (bool) old($nome, $marcado);
@endphp

<x-ui.grupo-campo :nome="$nome" :rotulo="$rotulo" :ajuda="$ajuda" :campo-id="$id" sem-rotulo>
    <label for="{{ $id }}" class="flex min-h-toque cursor-pointer items-center gap-3 text-texto">
        <input
            type="checkbox"
            id="{{ $id }}"
            name="{{ $nome }}"
            value="{{ $valor }}"
            @checked($estaMarcado)
            {{ $attributes->except('id')->merge([
                'class' => 'size-5 shrink-0 rounded-sm border-borda-forte text-acao '
                    .'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco',
            ]) }}
        >
        <span>{{ $rotulo }}</span>
    </label>
</x-ui.grupo-campo>
