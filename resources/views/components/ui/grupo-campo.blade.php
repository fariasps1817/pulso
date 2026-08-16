@props([
    'nome',
    'rotulo',
    'ajuda' => null,
    /* Obrigatorio e o padrao: numa ficha de aluno quase tudo e. Marcar
       asterisco em tudo vira ruido; marcamos o que e opcional. */
    'obrigatorio' => true,
    'campoId' => null,
    /* Alguns controles (caixa de selecao, interruptor) trazem o proprio
       rotulo ao lado, entao o rotulo de cima seria repeticao. */
    'semRotulo' => false,
])

@php
    $id = $campoId ?? $nome;
    $temErro = $errors->has($nome);
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-1.5']) }}>
    @unless ($semRotulo)
        <label for="{{ $id }}" class="text-sm font-medium text-texto-2">
            {{ $rotulo }}
            @unless ($obrigatorio)
                <span class="font-normal text-texto-mudo">(opcional)</span>
            @endunless
        </label>
    @endunless

    {{ $slot }}

    @if ($ajuda)
        <p id="{{ $id }}-ajuda" class="text-sm text-texto-mudo">{{ $ajuda }}</p>
    @endif

    @error($nome)
        <p id="{{ $id }}-erro" class="flex items-start gap-1.5 text-sm text-vencido-texto">
            <svg viewBox="0 0 20 20" class="mt-0.5 size-4 shrink-0" fill="currentColor" aria-hidden="true" focusable="false">
                <path d="M10 1.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17ZM9 5.5h2v6H9v-6Zm0 7.5h2v2H9v-2Z" />
            </svg>
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
