<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        :titulo="$this->editando() ? 'Editar plano' : 'Novo plano'"
        :subtitulo="$this->editando() ? $plano->nome : 'O que a academia vende.'"
        :voltar-para="['rotulo' => 'Planos', 'url' => route('planos.lista')]"
    />

    <form wire:submit="salvar" class="flex flex-col gap-6">

        <x-ui.cartao>
            <h2 class="text-lg text-texto">Plano</h2>

            <x-ui.grade-formulario class="mt-6">
                <x-ui.campo largura="50" nome="nome" rotulo="Nome do plano" wire:model.blur="nome"
                            placeholder="Mensal Musculação" autocomplete="off" />

                <x-ui.selecao largura="25" nome="duracao_meses" rotulo="Duração" wire:model="duracao_meses"
                              :vazio="false" :opcoes="[
                                  1 => 'Mensal',
                                  3 => 'Trimestral',
                                  6 => 'Semestral',
                                  12 => 'Anual',
                              ]" />

                <x-ui.campo-dinheiro largura="25" nome="valor_mensal" rotulo="Valor mensal"
                                     wire:model.blur="valor_mensal" />

                <x-ui.area-texto largura="100" nome="descricao" rotulo="Descrição" :obrigatorio="false"
                                 :linhas="2" wire:model.blur="descricao"
                                 placeholder="O que está incluído — musculação, aulas, avaliação…" />
            </x-ui.grade-formulario>
        </x-ui.cartao>

        <x-ui.cartao>
            <h2 class="text-lg text-texto">Cobrança</h2>

            <x-ui.grade-formulario class="mt-6">
                <x-ui.campo-dinheiro largura="25" nome="taxa_matricula" rotulo="Taxa de matrícula"
                                     :obrigatorio="false" wire:model.blur="taxa_matricula" />

                <x-ui.campo-dinheiro largura="25" nome="multa_cancelamento" rotulo="Multa por cancelamento"
                                     :obrigatorio="false" wire:model.blur="multa_cancelamento" />
            </x-ui.grade-formulario>
        </x-ui.cartao>

        <x-ui.cartao>
            <h2 class="text-lg text-texto">Experiência</h2>
            <p class="prosa mt-1 text-sm text-texto-2">
                O período de teste acaba pelo que vier primeiro: os dias ou as sessões.
                Zero desliga o critério; zero nos dois desliga a experiência.
            </p>

            <x-ui.grade-formulario class="mt-6">
                <x-ui.campo largura="25" nome="dias_experiencia" rotulo="Dias" tipo="number"
                            wire:model.blur="dias_experiencia" min="0" max="30" inputmode="numeric" />

                <x-ui.campo largura="25" nome="sessoes_experiencia" rotulo="Sessões" tipo="number"
                            wire:model.blur="sessoes_experiencia" min="0" max="60" inputmode="numeric" />
            </x-ui.grade-formulario>
        </x-ui.cartao>

        <x-ui.cartao>
            <div class="flex flex-col gap-5">
                {{-- O acesso entre filiais só faz sentido em rede. --}}
                @if ($temFiliais)
                    <x-ui.interruptor
                        nome="acesso_todas_unidades"
                        rotulo="Dá acesso a todas as unidades"
                        :ligado="$acesso_todas_unidades"
                        wire:model="acesso_todas_unidades"
                        descricao="Desligado, o aluno só passa na catraca da unidade em que se matriculou."
                    />
                @endif

                <x-ui.interruptor
                    nome="ativo"
                    rotulo="Plano ativo"
                    :ligado="$ativo"
                    wire:model="ativo"
                    descricao="Desativado, some na hora de matricular. As matrículas atuais seguem valendo."
                />
            </div>
        </x-ui.cartao>

        <div class="sticky bottom-0 z-10 -mx-4 flex flex-col-reverse gap-3 border-t border-borda
                    bg-superficie/95 px-4 py-4 backdrop-blur sm:mx-0 sm:flex-row sm:justify-end sm:rounded-lg sm:border sm:px-5">
            <x-ui.botao :href="$this->editando() ? route('planos.detalhes', $plano) : route('planos.lista')"
                        variante="secundario">
                Cancelar
            </x-ui.botao>

            <x-ui.botao tipo="submit" variante="primario" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="salvar">
                    {{ $this->editando() ? 'Salvar alterações' : 'Cadastrar plano' }}
                </span>
                <span wire:loading wire:target="salvar">Salvando…</span>
            </x-ui.botao>
        </div>
    </form>
</div>
