@props([
    'nome',
    'rotulo',
    'tipo' => 'text',
    'valor' => null,
    'ajuda' => null,
    'obrigatorio' => false,
    'autocomplete' => null,
])

{{--
    Campo de formulario com rotulo, ajuda e erro amarrados por aria.

    O erro nunca e comunicado so pela cor da borda: vem com icone e texto, e o
    campo aponta para ele por aria-describedby.
--}}

@php
    $id = $attributes->get('id', $nome);
    $temErro = $errors->has($nome);
    $idAjuda = $ajuda ? $id.'-ajuda' : null;
    $idErro = $temErro ? $id.'-erro' : null;
    $descritores = trim(implode(' ', array_filter([$idAjuda, $idErro]))) ?: null;
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-sm font-medium text-texto-2">
        {{ $rotulo }}
        @unless ($obrigatorio)
            <span class="font-normal text-texto-mudo">(opcional)</span>
        @endunless
    </label>

    <input
        type="{{ $tipo }}"
        id="{{ $id }}"
        name="{{ $nome }}"
        value="{{ old($nome, $valor) }}"
        @if ($obrigatorio) required @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($descritores) aria-describedby="{{ $descritores }}" @endif
        @if ($temErro) aria-invalid="true" @endif
        {{ $attributes->merge([
            'class' => 'min-h-toque w-full rounded-md bg-superficie px-3.5 text-base text-texto '
                .'border transition-colors placeholder:text-texto-mudo '
                .'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco '
                .($temErro ? 'border-vencido-forte' : 'border-borda-forte'),
        ]) }}
    >

    @if ($ajuda)
        <p id="{{ $idAjuda }}" class="text-sm text-texto-mudo">{{ $ajuda }}</p>
    @endif

    @error($nome)
        <p id="{{ $idErro }}" class="flex items-start gap-1.5 text-sm text-vencido-texto">
            <svg viewBox="0 0 20 20" class="mt-0.5 size-4 shrink-0" fill="currentColor" aria-hidden="true" focusable="false">
                <path d="M10 1.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17ZM9 5.5h2v6H9v-6Zm0 7.5h2v2H9v-2Z"/>
            </svg>
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
