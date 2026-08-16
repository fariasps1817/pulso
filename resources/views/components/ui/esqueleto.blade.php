@props([
    'linhas' => 3,
    /* texto | cartao | tabela */
    'formato' => 'texto',
])

{{--
    Espera com o desenho do que está por vir, não com uma roda girando.

    A roda diz "algo acontece"; o esqueleto diz "vai chegar uma lista aqui" —
    a tela não pula quando o conteúdo entra, porque o espaço já estava
    reservado. Respeita prefers-reduced-motion pelo CSS global.
--}}

<div {{ $attributes->merge(['class' => 'animate-pulse']) }} aria-hidden="true">
    @if ($formato === 'cartao')
        <div class="rounded-lg border border-borda bg-superficie p-5">
            <div class="h-4 w-1/3 rounded-sm bg-superficie-2"></div>
            <div class="mt-4 h-8 w-2/3 rounded-sm bg-superficie-2"></div>
            <div class="mt-4 h-6 w-24 rounded-pill bg-superficie-2"></div>
        </div>
    @elseif ($formato === 'tabela')
        <div class="overflow-hidden rounded-lg border border-borda bg-superficie">
            <div class="h-11 border-b border-borda bg-superficie-2"></div>
            @for ($i = 0; $i < $linhas; $i++)
                <div class="flex items-center gap-4 border-b border-borda px-4 py-4 last:border-b-0">
                    <div class="h-4 flex-1 rounded-sm bg-superficie-2"></div>
                    <div class="h-4 w-24 rounded-sm bg-superficie-2"></div>
                    <div class="h-6 w-20 rounded-pill bg-superficie-2"></div>
                </div>
            @endfor
        </div>
    @else
        <div class="flex flex-col gap-3">
            @for ($i = 0; $i < $linhas; $i++)
                <div class="h-4 rounded-sm bg-superficie-2" style="width: {{ [100, 85, 60][$i % 3] }}%"></div>
            @endfor
        </div>
    @endif

    <span class="sr-only">Carregando…</span>
</div>
