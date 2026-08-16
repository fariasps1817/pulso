@php
    use App\Support\Formato;
@endphp

<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina titulo="Planos" subtitulo="O que a academia vende.">
        @can('create', App\Models\Plano::class)
            <x-ui.botao :href="route('planos.novo')" variante="primario">
                <svg viewBox="0 0 20 20" class="size-5" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" aria-hidden="true">
                    <path d="M10 4v12M4 10h12" />
                </svg>
                Novo plano
            </x-ui.botao>
        @endcan
    </x-ui.cabecalho-pagina>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <svg viewBox="0 0 20 20" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-texto-mudo"
                 fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                <circle cx="9" cy="9" r="5.5" /><path d="m13.5 13.5 3 3" />
            </svg>
            <input type="search" wire:model.live.debounce.400ms="termo" placeholder="Buscar plano…"
                   aria-label="Buscar plano pelo nome"
                   class="min-h-toque w-full rounded-md border border-borda-forte bg-superficie py-2 pr-3.5 pl-11
                          text-base text-texto placeholder:text-texto-mudo
                          focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
        </div>

        <div class="flex gap-1 rounded-md border border-borda bg-superficie p-1" role="group" aria-label="Filtrar planos">
            @foreach (['ativos' => 'Ativos', 'inativos' => 'Desativados', 'todos' => 'Todos'] as $valor => $rotulo)
                <button type="button" wire:click="$set('situacao', '{{ $valor }}')"
                    @class([
                        'min-h-toque rounded-sm px-3 text-sm whitespace-nowrap transition-colors',
                        'bg-acao-sutil font-medium text-acao' => $situacao === $valor,
                        'text-texto-2 hover:bg-superficie-2 hover:text-texto' => $situacao !== $valor,
                    ])
                    @if ($situacao === $valor) aria-pressed="true" @endif>
                    {{ $rotulo }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($planos->isEmpty())
        <x-ui.estado-vazio
            titulo="Nenhum plano por aqui"
            descricao="Cadastre os planos que a academia vende — é o que a matrícula do aluno vai apontar."
            icone="M3.5 5.5h13M3.5 10h13M3.5 14.5h8"
        >
            @can('create', App\Models\Plano::class)
                <x-ui.botao :href="route('planos.novo')" variante="primario">Cadastrar plano</x-ui.botao>
            @endcan
        </x-ui.estado-vazio>
    @else
        <x-ui.tabela :colunas="[
            'Plano',
            'Duração',
            ['rotulo' => 'Alunos', 'alinhamento' => 'direita'],
            ['rotulo' => 'Valor mensal', 'alinhamento' => 'direita'],
            ['rotulo' => 'Situação', 'alinhamento' => 'direita'],
        ]">
            @foreach ($planos as $plano)
                <tr wire:key="plano-{{ $plano->id }}" class="transition-colors hover:bg-superficie-2">
                    <td data-rotulo="Plano" data-principal class="px-4 py-3">
                        <a href="{{ route('planos.detalhes', $plano) }}"
                           class="rounded-sm text-acao hover:underline
                                  focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                            {{ $plano->nome }}
                        </a>
                        @if ($plano->temExperiencia())
                            @php
                                $experiencia = implode(' ou ', array_filter([
                                    $plano->dias_experiencia > 0 ? $plano->dias_experiencia.' dias' : null,
                                    $plano->sessoes_experiencia > 0 ? $plano->sessoes_experiencia.' sessões' : null,
                                ]));
                            @endphp
                            <span class="block text-sm text-texto-mudo">Experiência: {{ $experiencia }}</span>
                        @endif
                    </td>

                    <td data-rotulo="Duração" class="px-4 py-3 text-texto-2">
                        {{ $plano->duracao_meses === 1 ? 'Mensal' : $plano->duracao_meses.' meses' }}
                    </td>

                    <td data-rotulo="Alunos" class="numeros px-4 py-3 text-right text-texto-2">
                        {{ $plano->matriculas_vigentes_count ?? 0 }}
                    </td>

                    <td data-rotulo="Valor mensal" class="numeros px-4 py-3 text-right text-texto">
                        {{ Formato::dinheiro($plano->valor_mensal) }}
                    </td>

                    <td data-rotulo="Situação" class="px-4 py-3 text-right">
                        @if ($plano->ativo)
                            <x-ui.pilula estado="pago">Ativo</x-ui.pilula>
                        @else
                            <x-ui.pilula estado="frequencia">Desativado</x-ui.pilula>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.tabela>

        <x-ui.paginacao :paginador="$planos" rotulo="planos" />
    @endif
</div>
