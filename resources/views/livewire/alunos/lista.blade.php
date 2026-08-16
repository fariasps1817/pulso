@php
    use App\Support\Documentos;
@endphp

<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina titulo="Alunos" subtitulo="Quem treina na academia.">
        @can('create', App\Models\Aluno::class)
            <x-ui.botao :href="route('alunos.novo')" variante="primario">
                <svg viewBox="0 0 20 20" class="size-5" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" aria-hidden="true">
                    <path d="M10 4v12M4 10h12" />
                </svg>
                Novo aluno
            </x-ui.botao>
        @endcan
    </x-ui.cabecalho-pagina>

    {{-- Busca e filtros --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <svg viewBox="0 0 20 20" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-texto-mudo"
                 fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                <circle cx="9" cy="9" r="5.5" /><path d="m13.5 13.5 3 3" />
            </svg>

            {{--
                `live.debounce` espera a digitação parar: sem isso, "Ana Beatriz"
                dispararia onze consultas ao banco, uma por letra.
            --}}
            <input
                type="search"
                wire:model.live.debounce.400ms="termo"
                placeholder="Buscar por nome ou CPF…"
                aria-label="Buscar aluno por nome ou CPF"
                class="min-h-toque w-full rounded-md border border-borda-forte bg-superficie py-2 pr-3.5 pl-11
                       text-base text-texto transition-colors placeholder:text-texto-mudo
                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
            >

            <div wire:loading wire:target="termo"
                 class="absolute top-1/2 right-3.5 -translate-y-1/2 text-sm text-texto-mudo">
                buscando…
            </div>
        </div>

        {{-- Filtro em pílulas, não em combo escondido. --}}
        <div class="flex gap-1 rounded-md border border-borda bg-superficie p-1" role="group" aria-label="Filtrar por situação">
            @foreach (['todos' => 'Todos', 'ativos' => 'Matriculados', 'sem_matricula' => 'Sem matrícula'] as $valor => $rotulo)
                <button
                    type="button"
                    wire:click="$set('situacao', '{{ $valor }}')"
                    @class([
                        'min-h-toque rounded-sm px-3 text-sm whitespace-nowrap transition-colors',
                        'bg-acao-sutil font-medium text-acao' => $situacao === $valor,
                        'text-texto-2 hover:bg-superficie-2 hover:text-texto' => $situacao !== $valor,
                    ])
                    @if ($situacao === $valor) aria-pressed="true" @endif
                >
                    {{ $rotulo }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Lista --}}
    <div wire:loading.class="opacity-60" wire:target="termo,situacao,gotoPage,previousPage,nextPage">
        @if ($alunos->isEmpty())
            @if ($temFiltro)
                <x-ui.estado-vazio
                    titulo="Nenhum aluno encontrado"
                    descricao="Nenhum cadastro corresponde à busca ou ao filtro escolhido."
                    icone="M9 15.5a6.5 6.5 0 1 0 0-13 6.5 6.5 0 0 0 0 13Zm4.5-1.5 4 4"
                >
                    <x-ui.botao wire:click="limparFiltros" variante="secundario">Limpar filtros</x-ui.botao>
                </x-ui.estado-vazio>
            @else
                <x-ui.estado-vazio
                    titulo="Nenhum aluno cadastrado ainda"
                    descricao="Cadastre o primeiro aluno para começar a controlar matrículas, mensalidades e acesso."
                    icone="M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm-6 7a6 6 0 0 1 12 0"
                >
                    @can('create', App\Models\Aluno::class)
                        <x-ui.botao :href="route('alunos.novo')" variante="primario">Cadastrar aluno</x-ui.botao>
                    @endcan
                </x-ui.estado-vazio>
            @endif
        @else
            <x-ui.tabela :colunas="[
                'Aluno',
                'CPF',
                'WhatsApp',
                ['rotulo' => 'Situação', 'alinhamento' => 'direita'],
            ]">
                @foreach ($alunos as $aluno)
                    <tr wire:key="aluno-{{ $aluno->id }}" class="transition-colors hover:bg-superficie-2">
                        {{-- O nome é o link. Não há coluna de ícones disputando o toque. --}}
                        <td data-rotulo="Aluno" data-principal class="px-4 py-3">
                            <a href="{{ route('alunos.detalhes', $aluno) }}"
                               class="rounded-sm text-acao hover:underline
                                      focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                                {{ $aluno->nome }}
                            </a>
                            <span class="block text-sm text-texto-mudo">{{ $aluno->idade() }} anos</span>
                        </td>

                        <td data-rotulo="CPF" class="numeros px-4 py-3 text-texto-2">
                            {{ $aluno->cpfFormatado() }}
                        </td>

                        <td data-rotulo="WhatsApp" class="numeros px-4 py-3 text-texto-2">
                            {{ \App\Support\Formato::telefone($aluno->whatsapp) }}
                        </td>

                        <td data-rotulo="Situação" class="px-4 py-3 text-right">
                            @if ($aluno->matriculaVigente)
                                <x-ui.pilula :estado="$aluno->matriculaVigente->situacao->pilula()">
                                    {{ $aluno->matriculaVigente->situacao->rotulo() }}
                                </x-ui.pilula>
                            @else
                                <x-ui.pilula estado="frequencia">Sem matrícula</x-ui.pilula>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.tabela>

            <div class="mt-4">
                <x-ui.paginacao :paginador="$alunos" rotulo="alunos" />
            </div>
        @endif
    </div>
</div>
