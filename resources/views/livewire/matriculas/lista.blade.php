@php
    use App\Enums\SituacaoMatricula;
    use App\Support\Formato;

    $colunas = array_values(array_filter([
        'Aluno',
        'Plano',
        'Unidade',
        $mostraValores ? ['rotulo' => 'Valor', 'alinhamento' => 'direita'] : null,
        ['rotulo' => 'Situação', 'alinhamento' => 'direita'],
    ]));
@endphp

<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina titulo="Matrículas" subtitulo="O vínculo entre aluno, plano e unidade.">
        @can('create', App\Models\Matricula::class)
            <x-ui.botao :href="route('matriculas.nova')" variante="primario">
                <svg viewBox="0 0 20 20" class="size-5" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" aria-hidden="true">
                    <path d="M10 4v12M4 10h12" />
                </svg>
                Nova matrícula
            </x-ui.botao>
        @endcan
    </x-ui.cabecalho-pagina>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <svg viewBox="0 0 20 20" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-texto-mudo"
                 fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                <circle cx="9" cy="9" r="5.5" /><path d="m13.5 13.5 3 3" />
            </svg>
            <input type="search" wire:model.live.debounce.400ms="termo" placeholder="Buscar pelo nome do aluno…"
                   aria-label="Buscar matrícula pelo nome do aluno"
                   class="min-h-toque w-full rounded-md border border-borda-forte bg-superficie py-2 pr-3.5 pl-11
                          text-base text-texto placeholder:text-texto-mudo
                          focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
        </div>

        <div class="-mx-1 overflow-x-auto px-1">
            <div class="flex gap-1 rounded-md border border-borda bg-superficie p-1" role="group"
                 aria-label="Filtrar por situação">
                @foreach ([
                    'vigentes' => 'Em vigor',
                    SituacaoMatricula::Experiencia->value => 'Experiência',
                    SituacaoMatricula::Suspensa->value => 'Trancadas',
                    SituacaoMatricula::Encerrada->value => 'Encerradas',
                    'todas' => 'Todas',
                ] as $valor => $rotulo)
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
    </div>

    @if ($matriculas->isEmpty())
        <x-ui.estado-vazio
            titulo="Nenhuma matrícula por aqui"
            descricao="A matrícula liga o aluno a um plano — é ela que gera as mensalidades e libera a catraca."
            icone="M5 3h10v14l-5-3-5 3V3Z"
        >
            @can('create', App\Models\Matricula::class)
                <x-ui.botao :href="route('matriculas.nova')" variante="primario">Nova matrícula</x-ui.botao>
            @endcan
        </x-ui.estado-vazio>
    @else
        <x-ui.tabela :colunas="$colunas">
            @foreach ($matriculas as $matricula)
                <tr wire:key="matricula-{{ $matricula->id }}" class="transition-colors hover:bg-superficie-2">
                    <td data-rotulo="Aluno" data-principal class="px-4 py-3">
                        <a href="{{ route('matriculas.detalhes', $matricula) }}"
                           class="rounded-sm text-acao hover:underline
                                  focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                            {{ $matricula->aluno->nome }}
                        </a>
                        <span class="block text-sm text-texto-mudo">
                            desde {{ $matricula->inicio_em->format('d/m/Y') }}
                        </span>
                    </td>

                    <td data-rotulo="Plano" class="px-4 py-3 text-texto-2">{{ $matricula->plano->nome }}</td>

                    <td data-rotulo="Unidade" class="px-4 py-3 text-texto-2">{{ $matricula->unidade->nome }}</td>

                    @if ($mostraValores)
                        <td data-rotulo="Valor" class="numeros px-4 py-3 text-right text-texto">
                            {{ Formato::dinheiro($matricula->valor_mensal) }}
                        </td>
                    @endif

                    <td data-rotulo="Situação" class="px-4 py-3 text-right">
                        <x-ui.pilula :estado="$matricula->situacao->pilula()">
                            {{ $matricula->situacao->rotulo() }}
                        </x-ui.pilula>
                    </td>
                </tr>
            @endforeach
        </x-ui.tabela>

        <x-ui.paginacao :paginador="$matriculas" rotulo="matrículas" />
    @endif
</div>
