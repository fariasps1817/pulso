@php
    use App\Support\Formato;
@endphp

<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        :titulo="$matricula->aluno->nome"
        :subtitulo="$matricula->plano->nome.' · '.$matricula->unidade->nome"
        :voltar-para="['rotulo' => 'Matrículas', 'url' => route('matriculas.lista')]"
    >
        <x-ui.botao :href="route('alunos.detalhes', $matricula->aluno)" variante="secundario">
            Ver aluno
        </x-ui.botao>

        @canany(['update', 'encerrar'], $matricula)
            <x-ui.menu>
                @can('update', $matricula)
                    @if ($matricula->podeSerSuspensa())
                        <x-ui.menu-item wire:click="suspender"
                                        icone="M7.5 5v10M12.5 5v10">
                            Trancar matrícula
                        </x-ui.menu-item>
                    @endif

                    @if ($matricula->podeSerReativada())
                        <x-ui.menu-item wire:click="reativar" icone="M6 4l10 6-10 6V4Z">
                            Reativar matrícula
                        </x-ui.menu-item>
                    @endif
                @endcan

                @can('encerrar', $matricula)
                    @if ($matricula->podeSerEncerrada())
                        <x-ui.menu-item data-abrir-modal="encerrar-matricula" :destrutivo="true"
                                        icone="M5 5l10 10M15 5L5 15">
                            Encerrar matrícula
                        </x-ui.menu-item>
                    @endif
                @endcan
            </x-ui.menu>
        @endcanany
    </x-ui.cabecalho-pagina>

    <div class="flex flex-wrap items-center gap-2">
        <x-ui.pilula :estado="$matricula->situacao->pilula()">{{ $matricula->situacao->rotulo() }}</x-ui.pilula>
        <span class="text-sm text-texto-mudo">
            desde {{ $matricula->inicio_em->format('d/m/Y') }}
            @if ($matricula->encerrada_em)
                · encerrada em {{ $matricula->encerrada_em->format('d/m/Y') }}
            @elseif ($matricula->fim_previsto_em)
                · até {{ $matricula->fim_previsto_em->format('d/m/Y') }}
            @endif
        </span>
    </div>

    {{-- ============ Conversão da experiência ============ --}}
    @if ($matricula->podeSerConvertida())
        @can('update', $matricula)
            <x-ui.cartao :destaque="true">
                <h2 class="text-lg text-texto">Converter em matrícula</h2>

                @if ($experienciaEsgotada)
                    <p class="prosa mt-1 text-sm text-avencer-texto">
                        O período de experiência acabou. Converta ou encerre — enquanto isso,
                        o aluno não passa mais na catraca.
                    </p>
                @else
                    <p class="prosa mt-1 text-sm text-texto-2">
                        Quando o aluno decidir ficar, registre o contrato assinado e escolha o dia de vencimento.
                        A vigência recomeça hoje.
                    </p>
                @endif

                <x-ui.grade-formulario class="mt-6">
                    <x-ui.campo-mascara largura="25" nome="contrato_assinado_em" rotulo="Contrato assinado em"
                                        formato="data" wire:model.blur="contrato_assinado_em" />

                    <x-ui.selecao largura="25" nome="dia_vencimento" rotulo="Vence todo dia"
                                  wire:model="dia_vencimento" :vazio="false"
                                  :opcoes="collect(range(1, 28))->mapWithKeys(fn ($d) => [$d => 'Dia '.$d])->all()" />
                </x-ui.grade-formulario>

                @error('conversao')
                    <p class="mt-3 text-sm text-vencido-texto">{{ $message }}</p>
                @enderror

                <div class="mt-5">
                    <x-ui.botao tipo="button" variante="primario" wire:click="converter">
                        Converter em matrícula
                    </x-ui.botao>
                </div>
            </x-ui.cartao>
        @endcan
    @endif

    {{-- ============ Dados ============ --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.cartao>
            <h3 class="text-lg text-texto">Contrato</h3>
            <dl class="mt-4 flex flex-col gap-3">
                <div class="flex justify-between gap-4">
                    <dt class="text-texto-mudo">Plano</dt>
                    <dd class="text-texto">{{ $matricula->plano->nome }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-texto-mudo">Unidade</dt>
                    <dd class="text-texto">{{ $matricula->unidade->nome }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-texto-mudo">Tipo</dt>
                    <dd class="text-texto">{{ $matricula->tipo->rotulo() }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-texto-mudo">Contrato assinado</dt>
                    <dd class="numeros text-texto">
                        {{ $matricula->contrato_assinado_em?->format('d/m/Y') ?? '—' }}
                    </dd>
                </div>
            </dl>
        </x-ui.cartao>

        <x-ui.cartao>
            <h3 class="text-lg text-texto">Cobrança</h3>

            @if ($mostraValores)
                <dl class="mt-4 flex flex-col gap-3">
                    <div class="flex justify-between gap-4">
                        <dt class="text-texto-mudo">Valor mensal</dt>
                        <dd class="numeros text-texto">{{ Formato::dinheiro($matricula->valor_mensal) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-texto-mudo">Vence todo dia</dt>
                        <dd class="numeros text-texto">{{ $matricula->dia_vencimento }}</dd>
                    </div>
                    @if ($matricula->valor_mensal != $matricula->plano->valor_mensal)
                        {{-- O valor da matrícula é o que vale. O do plano só
                             serviu de ponto de partida na contratação. --}}
                        <div class="flex justify-between gap-4 border-t border-borda pt-3">
                            <dt class="text-texto-mudo">Valor atual do plano</dt>
                            <dd class="numeros text-texto-mudo">
                                {{ Formato::dinheiro($matricula->plano->valor_mensal) }}
                            </dd>
                        </div>
                    @endif
                </dl>
            @else
                {{-- Professor não vê dinheiro — nem valor de plano. --}}
                <p class="mt-4 text-texto-mudo">Valores visíveis apenas para quem cuida do financeiro.</p>
            @endif
        </x-ui.cartao>

        @if ($matricula->observacoes)
            <x-ui.cartao class="lg:col-span-2">
                <h3 class="text-lg text-texto">Observações</h3>
                <p class="prosa mt-3 whitespace-pre-line text-texto-2">{{ $matricula->observacoes }}</p>
            </x-ui.cartao>
        @endif

        @if ($matricula->motivo_encerramento)
            <x-ui.cartao class="lg:col-span-2">
                <h3 class="text-lg text-texto">Motivo do encerramento</h3>
                <p class="prosa mt-3 text-texto-2">{{ $matricula->motivo_encerramento }}</p>
            </x-ui.cartao>
        @endif
    </div>

    {{-- ============ Encerramento ============ --}}
    @can('encerrar', $matricula)
        <x-ui.modal
            nome="encerrar-matricula"
            titulo="Encerrar a matrícula de {{ $matricula->aluno->nome }}?"
            descricao="O aluno para de gerar mensalidade e não passa mais na catraca. As mensalidades já emitidas continuam existindo — inclusive as em aberto."
        >
            <x-ui.campo nome="motivo_encerramento" rotulo="Motivo" :obrigatorio="false"
                        wire:model="motivo_encerramento" placeholder="Mudou de cidade, parou de treinar…" />

            <x-slot:acoes>
                <x-ui.botao tipo="button" variante="secundario" data-fechar-modal>Cancelar</x-ui.botao>
                <x-ui.botao tipo="button" variante="primario" class="bg-vencido-forte hover:bg-vencido-texto"
                            wire:click="encerrar">
                    Encerrar matrícula
                </x-ui.botao>
            </x-slot:acoes>
        </x-ui.modal>
    @endcan
</div>
