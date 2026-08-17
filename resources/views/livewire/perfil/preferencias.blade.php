<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        titulo="Preferências"
        subtitulo="Valem em qualquer aparelho onde você entrar."
    />

    <form wire:submit="salvar">
        <x-ui.cartao class="flex flex-col gap-5">
            <x-ui.grade-formulario>
                <x-ui.selecao
                    largura="25"
                    nome="tema"
                    rotulo="Tema"
                    wire:model="tema"
                    :vazio="null"
                    :opcoes="[
                        'sistema' => 'Acompanhar o sistema',
                        'claro' => 'Claro',
                        'escuro' => 'Escuro',
                    ]"
                />

                <x-ui.selecao
                    largura="25"
                    nome="itens_por_pagina"
                    rotulo="Itens por página"
                    wire:model="itens_por_pagina"
                    :vazio="null"
                    :opcoes="[10 => '10', 25 => '25', 50 => '50', 100 => '100']"
                />

                @if ($unidades->isNotEmpty())
                    <x-ui.selecao
                        largura="50"
                        nome="unidade_padrao_id"
                        rotulo="Unidade que abre por padrão"
                        :obrigatorio="false"
                        wire:model="unidade_padrao_id"
                        :vazio="null"
                        :opcoes="$unidades->pluck('nome', 'id')->all()"
                    />
                @endif
            </x-ui.grade-formulario>

            <p class="text-sm text-texto-mudo">
                O tema também é guardado no navegador, para a tela não piscar antes de carregar.
                O que fica aqui é o que viaja com você.
            </p>

            <div>
                <x-ui.botao tipo="submit">Salvar</x-ui.botao>
            </div>
        </x-ui.cartao>
    </form>
</div>
