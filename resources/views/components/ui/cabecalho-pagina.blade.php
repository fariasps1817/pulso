@props([
    'titulo',
    'subtitulo' => null,
    /* ['rotulo' => 'Alunos', 'url' => '...'] — o caminho de volta */
    'voltarPara' => null,
])

{{-- Topo de toda tela do painel: sempre no mesmo lugar, com as ações à
     direita. Previsibilidade vale mais que criatividade aqui. --}}

<div {{ $attributes->merge(['class' => 'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($voltarPara)
            <a href="{{ $voltarPara['url'] }}"
               class="mb-1 -ml-1 inline-flex items-center gap-1 rounded-md px-1 py-0.5 text-sm text-texto-2
                      transition-colors hover:text-texto
                      focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                <svg viewBox="0 0 20 20" class="size-4" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M11.5 4.5 6 10l5.5 5.5" />
                </svg>
                {{ $voltarPara['rotulo'] }}
            </a>
        @endif

        <h1 class="truncate text-2xl text-texto">{{ $titulo }}</h1>

        @if ($subtitulo)
            <p class="mt-1 text-texto-2">{{ $subtitulo }}</p>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="flex shrink-0 items-center gap-2">{{ $slot }}</div>
    @endif
</div>
