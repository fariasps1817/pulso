@php
    use App\Enums\SituacaoMensalidade;
    use App\Support\Formato;

    $hoje = \Carbon\CarbonImmutable::now()->startOfDay();
@endphp

<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina titulo="Mensalidades" subtitulo="O que os alunos devem, e o que já entrou." />

    {{-- Os três números que a gestão olha primeiro. --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <button type="button" wire:click="$set('filtro', 'vencidas')" class="text-left">
            <x-ui.cartao :destaque="$filtro === 'vencidas'" class="h-full transition-colors hover:bg-superficie-2">
                <p class="text-sm text-texto-mudo">Vencidas</p>
                <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ Formato::dinheiro($totais['vencido']) }}</p>
            </x-ui.cartao>
        </button>

        <button type="button" wire:click="$set('filtro', 'vence_hoje')" class="text-left">
            <x-ui.cartao :destaque="$filtro === 'vence_hoje'" class="h-full transition-colors hover:bg-superficie-2">
                <p class="text-sm text-texto-mudo">Vencem hoje</p>
                <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ Formato::dinheiro($totais['vence_hoje']) }}</p>
            </x-ui.cartao>
        </button>

        <button type="button" wire:click="$set('filtro', 'em_aberto')" class="text-left">
            <x-ui.cartao :destaque="$filtro === 'em_aberto'" class="h-full transition-colors hover:bg-superficie-2">
                <p class="text-sm text-texto-mudo">Total em aberto</p>
                <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ Formato::dinheiro($totais['em_aberto']) }}</p>
            </x-ui.cartao>
        </button>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <svg viewBox="0 0 20 20" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-texto-mudo"
                 fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                <circle cx="9" cy="9" r="5.5" /><path d="m13.5 13.5 3 3" />
            </svg>
            <input type="search" wire:model.live.debounce.400ms="termo" placeholder="Buscar pelo nome do aluno…"
                   aria-label="Buscar mensalidade pelo nome do aluno"
                   class="min-h-toque w-full rounded-md border border-borda-forte bg-superficie py-2 pr-3.5 pl-11
                          text-base text-texto placeholder:text-texto-mudo
                          focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
        </div>

        <div class="-mx-1 overflow-x-auto px-1">
            <div class="flex gap-1 rounded-md border border-borda bg-superficie p-1" role="group" aria-label="Filtrar">
                @foreach ([
                    'em_aberto' => 'Em aberto',
                    'vencidas' => 'Vencidas',
                    'vence_hoje' => 'Vencem hoje',
                    'pagas' => 'Pagas',
                    'todas' => 'Todas',
                ] as $valor => $rotulo)
                    <button type="button" wire:click="$set('filtro', '{{ $valor }}')"
                        @class([
                            'min-h-toque rounded-sm px-3 text-sm whitespace-nowrap transition-colors',
                            'bg-acao-sutil font-medium text-acao' => $filtro === $valor,
                            'text-texto-2 hover:bg-superficie-2 hover:text-texto' => $filtro !== $valor,
                        ])
                        @if ($filtro === $valor) aria-pressed="true" @endif>
                        {{ $rotulo }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    @if ($mensalidades->isEmpty())
        <x-ui.estado-vazio
            titulo="Nenhuma mensalidade aqui"
            descricao="As mensalidades nascem das matrículas ativas, geradas todo mês pela rotina automática."
            icone="M2.5 6.5h15v9h-15v-9Zm0 3.5h15M5.5 13h3"
        />
    @else
        <x-ui.tabela :colunas="[
            'Aluno',
            'Competência',
            ['rotulo' => 'Vencimento', 'alinhamento' => 'direita'],
            ['rotulo' => 'Valor', 'alinhamento' => 'direita'],
            ['rotulo' => 'Situação', 'alinhamento' => 'direita'],
        ]">
            @foreach ($mensalidades as $mensalidade)
                @php
                    // "Vencida" é derivada, nunca lida de uma coluna.
                    $vencida = $mensalidade->estaVencida($hoje);
                    $venceHoje = $mensalidade->situacao === SituacaoMensalidade::Aberta
                        && $mensalidade->vencimento->isSameDay($hoje);
                @endphp

                <tr wire:key="mensalidade-{{ $mensalidade->id }}" class="transition-colors hover:bg-superficie-2">
                    <td data-rotulo="Aluno" data-principal class="px-4 py-3">
                        <a href="{{ route('mensalidades.detalhes', $mensalidade) }}"
                           class="rounded-sm text-acao hover:underline
                                  focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                            {{ $mensalidade->aluno->nome }}
                        </a>
                        <span class="block text-sm text-texto-mudo">{{ $mensalidade->unidade->nome }}</span>
                    </td>

                    <td data-rotulo="Competência" class="px-4 py-3 text-texto-2">
                        {{ $mensalidade->competencia->translatedFormat('F/Y') }}
                    </td>

                    <td data-rotulo="Vencimento" class="numeros px-4 py-3 text-right text-texto-2">
                        {{ $mensalidade->vencimento->format('d/m/Y') }}
                    </td>

                    <td data-rotulo="Valor" class="numeros px-4 py-3 text-right text-texto">
                        {{ Formato::dinheiro($mensalidade->valorDevido()) }}
                    </td>

                    <td data-rotulo="Situação" class="px-4 py-3 text-right">
                        @if ($mensalidade->situacao === SituacaoMensalidade::Paga)
                            <x-ui.pilula estado="pago" />
                        @elseif ($mensalidade->situacao === SituacaoMensalidade::Cancelada)
                            <x-ui.pilula estado="frequencia">Cancelada</x-ui.pilula>
                        @elseif ($vencida)
                            <x-ui.pilula estado="vencido" />
                        @elseif ($venceHoje)
                            <x-ui.pilula estado="avencer">Vence hoje</x-ui.pilula>
                        @else
                            <x-ui.pilula estado="avencer" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.tabela>

        <x-ui.paginacao :paginador="$mensalidades" rotulo="mensalidades" />
    @endif
</div>
