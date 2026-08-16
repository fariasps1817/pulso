@props([
    'titulo',
    'descricao' => null,
    'icone' => 'M4 6h12M4 10h12M4 14h8',
])

{{--
    Lista sem nada. Nunca uma tela em branco: diz o que está faltando e
    oferece o caminho para o primeiro registro — quem abriu a tela pela
    primeira vez precisa saber o que fazer, não concluir que quebrou.
--}}

<div {{ $attributes->merge(['class' => 'flex flex-col items-center rounded-lg border border-dashed border-borda bg-superficie px-6 py-14 text-center']) }}>
    <span class="inline-flex size-14 items-center justify-center rounded-pill bg-superficie-2 text-texto-mudo">
        <svg viewBox="0 0 20 20" class="size-7" fill="none" stroke="currentColor" stroke-width="1.6"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <path d="{{ $icone }}" />
        </svg>
    </span>

    <h3 class="mt-4 text-lg text-texto">{{ $titulo }}</h3>

    @if ($descricao)
        <p class="prosa mt-2 text-texto-2">{{ $descricao }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-6">{{ $slot }}</div>
    @endif
</div>
