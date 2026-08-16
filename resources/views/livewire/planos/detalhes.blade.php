@php
    use App\Support\Formato;
@endphp

<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        :titulo="$plano->nome"
        :subtitulo="$plano->duracao_meses === 1 ? 'Plano mensal' : 'Plano de '.$plano->duracao_meses.' meses'"
        :voltar-para="['rotulo' => 'Planos', 'url' => route('planos.lista')]"
    >
        @can('update', $plano)
            <x-ui.botao :href="route('planos.editar', $plano)" variante="primario">Editar</x-ui.botao>

            <x-ui.menu>
                <x-ui.menu-item wire:click="alternarAtivo"
                                icone="M6 10.5 9 13.5 14.5 7">
                    {{ $plano->ativo ? 'Desativar plano' : 'Reativar plano' }}
                </x-ui.menu-item>

                @can('delete', $plano)
                    <x-ui.menu-item data-abrir-modal="excluir-plano" :destrutivo="true"
                                    icone="M3.5 5.5h13M8 5.5V4h4v1.5M5.5 5.5l.8 11h7.4l.8-11">
                        Excluir plano
                    </x-ui.menu-item>
                @endcan
            </x-ui.menu>
        @endcan
    </x-ui.cabecalho-pagina>

    <div class="flex flex-wrap items-center gap-2">
        @if ($plano->ativo)
            <x-ui.pilula estado="pago">Ativo</x-ui.pilula>
        @else
            <x-ui.pilula estado="frequencia">Desativado</x-ui.pilula>
            <span class="text-sm text-texto-mudo">Não aparece na hora de matricular.</span>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.cartao>
            <p class="text-sm text-texto-mudo">Valor mensal</p>
            <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ Formato::dinheiro($plano->valor_mensal) }}</p>
        </x-ui.cartao>

        <x-ui.cartao>
            <p class="text-sm text-texto-mudo">Alunos com este plano</p>
            <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ $matriculasVigentes }}</p>
            @if ($matriculasTotal > $matriculasVigentes)
                <p class="mt-1 text-sm text-texto-mudo">
                    <span class="numeros">{{ $matriculasTotal }}</span> ao todo, contando encerradas.
                </p>
            @endif
        </x-ui.cartao>

        @php
            // Montado em PHP e não em Blade: a alternativa era encadear @if
            // no meio do texto, o que já rendeu um erro de sintaxe quando o
            // @endif ficou colado numa palavra acentuada.
            $partesExperiencia = array_filter([
                $plano->dias_experiencia > 0 ? $plano->dias_experiencia.' dias' : null,
                $plano->sessoes_experiencia > 0 ? $plano->sessoes_experiencia.' sessões' : null,
            ]);
        @endphp

        <x-ui.cartao>
            <p class="text-sm text-texto-mudo">Experiência</p>
            <p class="numeros mt-1 font-titulo text-3xl text-texto">
                {{ $partesExperiencia === [] ? 'Sem' : implode(' ou ', $partesExperiencia) }}
            </p>
            @if ($partesExperiencia !== [])
                <p class="mt-1 text-sm text-texto-mudo">Acaba pelo que vier primeiro.</p>
            @endif
        </x-ui.cartao>
    </div>

    <x-ui.cartao>
        <h2 class="text-lg text-texto">Detalhes</h2>

        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="flex justify-between gap-4 border-b border-borda pb-3">
                <dt class="text-texto-mudo">Taxa de matrícula</dt>
                <dd class="numeros text-texto">{{ Formato::dinheiro($plano->taxa_matricula) }}</dd>
            </div>
            <div class="flex justify-between gap-4 border-b border-borda pb-3">
                <dt class="text-texto-mudo">Multa por cancelamento</dt>
                <dd class="numeros text-texto">{{ Formato::dinheiro($plano->multa_cancelamento) }}</dd>
            </div>
            <div class="flex justify-between gap-4 border-b border-borda pb-3">
                <dt class="text-texto-mudo">Acesso às unidades</dt>
                <dd class="text-texto">
                    {{ $plano->acesso_todas_unidades ? 'Todas' : 'Só a da matrícula' }}
                </dd>
            </div>
            <div class="flex justify-between gap-4 border-b border-borda pb-3">
                <dt class="text-texto-mudo">Duração</dt>
                <dd class="text-texto">
                    {{ $plano->duracao_meses === 1 ? 'Mensal' : $plano->duracao_meses.' meses' }}
                </dd>
            </div>
        </dl>

        @if ($plano->descricao)
            <p class="prosa mt-5 whitespace-pre-line text-texto-2">{{ $plano->descricao }}</p>
        @endif
    </x-ui.cartao>

    @can('delete', $plano)
        <x-ui.modal
            nome="excluir-plano"
            titulo="Excluir {{ $plano->nome }}?"
            descricao="Este plano nunca foi usado em matrícula nenhuma, então some de vez. Se algum dia for contratado, o caminho passa a ser desativar — o histórico precisa saber o que foi vendido."
        >
            <x-slot:acoes>
                <x-ui.botao tipo="button" variante="secundario" data-fechar-modal>Cancelar</x-ui.botao>
                <x-ui.botao tipo="button" variante="primario" class="bg-vencido-forte hover:bg-vencido-texto"
                            wire:click="excluir">
                    Excluir plano
                </x-ui.botao>
            </x-slot:acoes>
        </x-ui.modal>
    @endcan
</div>
