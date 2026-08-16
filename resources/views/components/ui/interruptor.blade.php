@props([
    'nome',
    'rotulo',
    'descricao' => null,
    'ligado' => false,
    'valor' => '1',
])

{{--
    Interruptor liga/desliga (ativo, inativo, sessão única...).

    O estado nunca é comunicado só pela posição e pela cor: há um traço quando
    desligado e um visto quando ligado, dentro do botão. Quem não distingue as
    cores, e a impressão em preto e branco, continuam legíveis.
--}}

@php
    $id = $attributes->get('id', $nome);
    $estaLigado = (bool) old($nome, $ligado);
@endphp

<div {{ $attributes->except('id')->merge(['class' => 'flex items-start justify-between gap-4']) }}
     x-data="{ ligado: {{ $estaLigado ? 'true' : 'false' }} }">
    <div class="flex flex-col">
        <label for="{{ $id }}" class="cursor-pointer font-medium text-texto">{{ $rotulo }}</label>

        @if ($descricao)
            <p class="mt-0.5 text-sm text-texto-mudo">{{ $descricao }}</p>
        @endif
    </div>

    <input type="hidden" name="{{ $nome }}" :value="ligado ? '{{ $valor }}' : '0'">

    <button
        type="button"
        id="{{ $id }}"
        role="switch"
        :aria-checked="ligado ? 'true' : 'false'"
        @click="ligado = ! ligado"
        class="relative inline-flex h-7 w-12 shrink-0 cursor-pointer items-center rounded-pill
               border border-borda-forte transition-colors
               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
        :class="ligado ? 'bg-acao border-acao' : 'bg-superficie-2'"
    >
        {{-- Sem translate estático na classe: ele e o do :class colidiriam, e
             quem vence é a ordem no CSS, não a do atributo — o botão ficaria
             parado. O deslocamento vive só no binding. --}}
        <span class="inline-flex size-5 items-center justify-center rounded-pill
                     bg-superficie shadow-1 transition-transform"
              :class="ligado ? 'translate-x-6' : 'translate-x-1'">
            <svg x-show="ligado" viewBox="0 0 20 20" class="size-3 text-acao" fill="none" stroke="currentColor"
                 stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 10.5 8 14.5 16 6" />
            </svg>
            <svg x-show="! ligado" viewBox="0 0 20 20" class="size-3 text-texto-mudo" fill="none" stroke="currentColor"
                 stroke-width="3" stroke-linecap="round" aria-hidden="true">
                <path d="M5 10h10" />
            </svg>
        </span>
    </button>
</div>
