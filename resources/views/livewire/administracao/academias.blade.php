@php
    use App\Enums\SituacaoAcademia;
@endphp

<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        titulo="Academias"
        subtitulo="Os clientes do Pulso."
    >
        <x-ui.botao :href="route('administracao.academias.nova')">Nova academia</x-ui.botao>
    </x-ui.cabecalho-pagina>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.cartao>
            <p class="text-sm text-texto-mudo">Academias</p>
            <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ $totais['academias'] }}</p>
        </x-ui.cartao>

        <x-ui.cartao>
            <p class="text-sm text-texto-mudo">Com filial</p>
            <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ $totais['com_filial'] }}</p>
        </x-ui.cartao>

        <x-ui.cartao>
            <p class="text-sm text-texto-mudo">Alunos atendidos</p>
            <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ $totais['alunos'] }}</p>
        </x-ui.cartao>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <svg viewBox="0 0 20 20" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-texto-mudo"
                 fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                <circle cx="9" cy="9" r="5.5" /><path d="m13.5 13.5 3 3" />
            </svg>
            <input type="search" wire:model.live.debounce.400ms="termo" placeholder="Buscar por nome ou CNPJ…"
                   aria-label="Buscar academia"
                   class="min-h-toque w-full rounded-md border border-borda-forte bg-superficie py-2 pr-3.5 pl-11
                          text-base text-texto placeholder:text-texto-mudo
                          focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
        </div>

        <div class="-mx-1 overflow-x-auto px-1">
            <div class="flex gap-1 rounded-md border border-borda bg-superficie p-1" role="group" aria-label="Filtrar">
                <button type="button" wire:click="$set('filtro', 'todas')"
                    @class([
                        'min-h-toque rounded-sm px-3 text-sm whitespace-nowrap transition-colors',
                        'bg-acao-sutil font-medium text-acao' => $filtro === 'todas',
                        'text-texto-2 hover:bg-superficie-2' => $filtro !== 'todas',
                    ])>Todas</button>

                @foreach ($situacoes as $situacao)
                    <button type="button" wire:click="$set('filtro', '{{ $situacao->value }}')"
                        @class([
                            'min-h-toque rounded-sm px-3 text-sm whitespace-nowrap transition-colors',
                            'bg-acao-sutil font-medium text-acao' => $filtro === $situacao->value,
                            'text-texto-2 hover:bg-superficie-2' => $filtro !== $situacao->value,
                        ])>{{ $situacao->rotulo() }}</button>
                @endforeach
            </div>
        </div>
    </div>

    @if ($academias->isEmpty())
        <x-ui.estado-vazio
            titulo="Nenhuma academia aqui"
            descricao="Ajuste a busca ou cadastre a primeira."
            icone="M3 17V7l7-4 7 4v10M8 17v-5h4v5"
        />
    @else
        <x-ui.tabela :colunas="[
            'Academia',
            ['rotulo' => 'Unidades', 'alinhamento' => 'direita'],
            ['rotulo' => 'Alunos', 'alinhamento' => 'direita'],
            ['rotulo' => 'Equipe', 'alinhamento' => 'direita'],
            ['rotulo' => 'Situação', 'alinhamento' => 'direita'],
        ]">
            @foreach ($academias as $academia)
                <tr wire:key="academia-{{ $academia->id }}" class="transition-colors hover:bg-superficie-2">
                    <td data-rotulo="Academia" data-principal class="px-4 py-3">
                        <a href="{{ route('administracao.academias.detalhes', $academia) }}"
                           class="rounded-sm text-acao hover:underline
                                  focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                            {{ $academia->nome }}
                        </a>
                        <span class="block text-sm text-texto-mudo">
                            {{ $academia->cidade }}@if ($academia->uf)/{{ $academia->uf }}@endif
                        </span>
                    </td>

                    <td data-rotulo="Unidades" class="numeros px-4 py-3 text-right text-texto-2">
                        {{ $academia->unidades_count }}
                        @if ($academia->unidades_count > 1)
                            {{-- O que muda o contrato: rede cobra diferente de
                                 academia de uma unidade só. --}}
                            <span class="block text-sm text-texto-mudo">com filial</span>
                        @endif
                    </td>

                    <td data-rotulo="Alunos" class="numeros px-4 py-3 text-right text-texto">
                        {{ $academia->total_alunos_ativos }}
                    </td>

                    <td data-rotulo="Equipe" class="numeros px-4 py-3 text-right text-texto-2">
                        {{ $academia->usuarios_count }}
                    </td>

                    <td data-rotulo="Situação" class="px-4 py-3 text-right">
                        @if ($academia->situacao === SituacaoAcademia::Ativa)
                            <x-ui.pilula estado="pago">Ativa</x-ui.pilula>
                        @elseif ($academia->situacao === SituacaoAcademia::EmAviso)
                            <x-ui.pilula estado="avencer">Em aviso</x-ui.pilula>
                        @else
                            <x-ui.pilula estado="vencido">{{ $academia->situacao->rotulo() }}</x-ui.pilula>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.tabela>

        <x-ui.paginacao :paginador="$academias" rotulo="academias" />

        <p class="text-sm text-texto-mudo">
            O número de alunos é mantido por cada academia. O Pulso não lê os dados delas —
            as políticas de isolamento do banco não abrem exceção nem para esta tela.
        </p>
    @endif
</div>
