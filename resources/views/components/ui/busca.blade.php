@props([
    'nome' => 'busca',
    'placeholder' => 'Buscar…',
    'valor' => null,
])

{{-- Busca do topo das listas. `type="search"` dá o botão de limpar nativo,
     que no celular evita apagar caractere por caractere. --}}

<div {{ $attributes->merge(['class' => 'relative']) }}>
    <svg viewBox="0 0 20 20" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-texto-mudo"
         fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="5.5" />
        <path d="m13.5 13.5 3 3" />
    </svg>

    <input
        type="search"
        name="{{ $nome }}"
        value="{{ $valor }}"
        placeholder="{{ $placeholder }}"
        aria-label="{{ $placeholder }}"
        class="min-h-toque w-full rounded-md border border-borda-forte bg-superficie py-2 pr-3.5 pl-11
               text-base text-texto transition-colors placeholder:text-texto-mudo
               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
    >
</div>
