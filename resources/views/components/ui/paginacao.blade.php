@props([
    'paginador',
    /* Como chamar o que está sendo listado: "alunos", "mensalidades"... */
    'rotulo' => 'registros',
])

{{--
    Paginação com o total sempre visível. "312 alunos" responde de imediato
    quantos são; sem isso, a gestão fica contando página por página.
--}}

@if ($paginador->hasPages() || $paginador->total() > 0)
    <nav {{ $attributes->merge(['class' => 'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between']) }}
         aria-label="Paginação">
        <p class="text-sm text-texto-mudo">
            <span class="numeros">{{ $paginador->firstItem() ?? 0 }}</span>–<span class="numeros">{{ $paginador->lastItem() ?? 0 }}</span>
            de <span class="numeros font-medium text-texto-2">{{ $paginador->total() }}</span> {{ $rotulo }}
        </p>

        @if ($paginador->hasPages())
            <div class="flex items-center gap-1">
                @if ($paginador->onFirstPage())
                    <span class="inline-flex min-h-toque items-center rounded-md px-4 text-texto-mudo" aria-disabled="true">Anterior</span>
                @else
                    <a href="{{ $paginador->previousPageUrl() }}" rel="prev"
                       class="inline-flex min-h-toque items-center rounded-md px-4 text-acao transition-colors
                              hover:bg-acao-sutil focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                        Anterior
                    </a>
                @endif

                <span class="numeros px-2 text-sm text-texto-2">
                    {{ $paginador->currentPage() }} / {{ $paginador->lastPage() }}
                </span>

                @if ($paginador->hasMorePages())
                    <a href="{{ $paginador->nextPageUrl() }}" rel="next"
                       class="inline-flex min-h-toque items-center rounded-md px-4 text-acao transition-colors
                              hover:bg-acao-sutil focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                        Próxima
                    </a>
                @else
                    <span class="inline-flex min-h-toque items-center rounded-md px-4 text-texto-mudo" aria-disabled="true">Próxima</span>
                @endif
            </div>
        @endif
    </nav>
@endif
