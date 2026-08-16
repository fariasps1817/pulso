<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        titulo="Dados da academia"
        subtitulo="O que sai impresso nos recibos e contratos."
        :voltar-para="['rotulo' => 'Configurações', 'url' => route('configuracoes.painel')]"
    />

    <form wire:submit="salvar" class="flex flex-col gap-6">
        <x-ui.cartao class="flex flex-col gap-5">
            <h2 class="font-titulo text-lg text-texto">Identificação</h2>

            <x-ui.grade-formulario>
                <x-ui.campo largura="50" nome="nome" rotulo="Nome" wire:model.blur="nome" />

                <x-ui.campo largura="50" nome="razao_social" rotulo="Razão social"
                            :obrigatorio="false" wire:model.blur="razao_social" />

                <x-ui.campo-mascara largura="25" nome="cnpj" rotulo="CNPJ" formato="cnpj"
                                    :obrigatorio="false" wire:model.blur="cnpj" />

                <x-ui.campo largura="25" nome="email" rotulo="E-mail" tipo="email" wire:model.blur="email" />

                <x-ui.campo-mascara largura="25" nome="telefone" rotulo="Telefone" formato="telefone"
                                    :obrigatorio="false" wire:model.blur="telefone" />

                <x-ui.campo-mascara largura="25" nome="whatsapp" rotulo="WhatsApp" formato="telefone"
                                    :obrigatorio="false" wire:model.blur="whatsapp" />
            </x-ui.grade-formulario>
        </x-ui.cartao>

        <x-ui.cartao class="flex flex-col gap-5">
            <h2 class="font-titulo text-lg text-texto">Endereço</h2>

            @if ($avisoDeCep)
                {{-- O CEP ajuda, nunca trava: academia em rua nova não pode
                     ficar sem cadastro porque um serviço externo caiu. --}}
                <x-ui.aviso tipo="atencao">{{ $avisoDeCep }}</x-ui.aviso>
            @endif

            <x-ui.grade-formulario>
                <x-ui.campo-mascara largura="25" nome="cep" rotulo="CEP" formato="cep"
                                    :obrigatorio="false" wire:model.live.debounce.500ms="cep" />

                <x-ui.campo largura="50" nome="logradouro" rotulo="Rua"
                            :obrigatorio="false" wire:model.blur="logradouro" />

                <x-ui.campo largura="25" nome="numero" rotulo="Número"
                            :obrigatorio="false" wire:model.blur="numero" />

                <x-ui.campo largura="25" nome="complemento" rotulo="Complemento"
                            :obrigatorio="false" wire:model.blur="complemento" />

                <x-ui.campo largura="25" nome="bairro" rotulo="Bairro"
                            :obrigatorio="false" wire:model.blur="bairro" />

                <x-ui.campo largura="25" nome="cidade" rotulo="Cidade"
                            :obrigatorio="false" wire:model.blur="cidade" />

                <x-ui.campo largura="25" nome="uf" rotulo="Estado"
                            :obrigatorio="false" wire:model.blur="uf" />
            </x-ui.grade-formulario>
        </x-ui.cartao>

        <x-ui.cartao class="flex flex-col gap-5">
            <div>
                <h2 class="font-titulo text-lg text-texto">Logotipo</h2>
                <p class="mt-1 text-sm text-texto-mudo">
                    Entra no cabeçalho dos recibos e contratos. Não substitui a marca do Pulso na
                    interface — o sistema continua sendo o Pulso.
                </p>
            </div>

            @if ($logoAtual)
                <div class="flex flex-wrap items-center gap-4">
                    <img src="{{ $logoAtual }}" alt="Logotipo atual da academia"
                         class="h-16 w-auto rounded-md border border-borda bg-superficie-2 p-2">

                    <button type="button" wire:click="removerLogo"
                            class="min-h-toque rounded-md px-3 text-sm text-vencido-texto hover:underline
                                   focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                        Remover
                    </button>
                </div>
            @endif

            <x-ui.grade-formulario>
                <x-ui.envio-imagem
                    largura="50"
                    nome="logo"
                    rotulo="{{ $logoAtual ? 'Trocar o logotipo' : 'Enviar o logotipo' }}"
                    formato="retangulo"
                    wire:model="logo"
                />
            </x-ui.grade-formulario>
        </x-ui.cartao>

        <div class="flex flex-wrap gap-2">
            <x-ui.botao tipo="submit">Salvar</x-ui.botao>
            <x-ui.botao :href="route('configuracoes.painel')" variante="secundario">Voltar</x-ui.botao>
        </div>
    </form>
</div>
