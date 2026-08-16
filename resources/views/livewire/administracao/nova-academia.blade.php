<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        titulo="Nova academia"
        subtitulo="Academia, primeira unidade e acesso do dono — os três juntos."
        :voltar-para="['rotulo' => 'Academias', 'url' => route('administracao.academias.lista')]"
    />

    @if ($senhaTemporaria)
        <x-ui.cartao destaque class="flex flex-col gap-4">
            <div>
                <h2 class="font-titulo text-lg text-texto">{{ $criada->nome }} está no ar</h2>
                <p class="mt-1 text-texto-2">
                    Passe estes dados para {{ $dono_nome }}. A senha é temporária: no primeiro
                    acesso o sistema exige que ele escolha a dele.
                </p>
            </div>

            <dl class="grid gap-3 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-texto-mudo">E-mail de acesso</dt>
                    <dd class="text-texto select-all">{{ $dono_email }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-texto-mudo">Endereço</dt>
                    <dd class="text-texto">{{ config('pulso.site.url') }}/login</dd>
                </div>
            </dl>

            <p class="numeros rounded-md border border-borda-forte bg-superficie-2 px-4 py-3
                      text-center font-titulo text-2xl tracking-wider text-texto select-all">
                {{ $senhaTemporaria }}
            </p>

            <p class="text-sm text-texto-mudo">
                Esta senha não será mostrada de novo.
            </p>

            <div class="flex flex-wrap gap-2">
                <x-ui.botao :href="route('administracao.academias.detalhes', $criada)">Ver a academia</x-ui.botao>
                <x-ui.botao :href="route('administracao.academias.lista')" variante="secundario">Concluir</x-ui.botao>
            </div>
        </x-ui.cartao>
    @else
        <form wire:submit="salvar" class="flex flex-col gap-6">
            <x-ui.cartao class="flex flex-col gap-5">
                <h2 class="font-titulo text-lg text-texto">A academia</h2>

                <x-ui.grade-formulario>
                    <x-ui.campo largura="50" nome="nome" rotulo="Nome" wire:model.blur="nome" />
                    <x-ui.campo largura="50" nome="razao_social" rotulo="Razão social"
                                :obrigatorio="false" wire:model.blur="razao_social" />

                    <x-ui.campo-mascara largura="25" nome="cnpj" rotulo="CNPJ" formato="cnpj"
                                        :obrigatorio="false" wire:model.blur="cnpj" />
                    <x-ui.campo largura="50" nome="email" rotulo="E-mail" tipo="email" wire:model.blur="email" />
                    <x-ui.campo-mascara largura="25" nome="whatsapp" rotulo="WhatsApp" formato="telefone"
                                        :obrigatorio="false" wire:model.blur="whatsapp" />

                    <x-ui.campo largura="50" nome="cidade" rotulo="Cidade" wire:model.blur="cidade" />
                    <x-ui.campo largura="25" nome="uf" rotulo="Estado" wire:model.blur="uf" />
                    <x-ui.campo-mascara largura="25" nome="assinatura_vence_em" rotulo="Assinatura vence em"
                                        formato="data" :obrigatorio="false"
                                        wire:model.blur="assinatura_vence_em" />
                </x-ui.grade-formulario>
            </x-ui.cartao>

            <x-ui.cartao class="flex flex-col gap-5">
                <div>
                    <h2 class="font-titulo text-lg text-texto">A primeira unidade</h2>
                    <p class="mt-1 text-sm text-texto-mudo">
                        Sem unidade não há onde matricular ninguém. As filiais a academia cadastra depois.
                    </p>
                </div>

                <x-ui.grade-formulario>
                    <x-ui.campo largura="50" nome="unidade_nome" rotulo="Nome da unidade"
                                wire:model.blur="unidade_nome" />
                </x-ui.grade-formulario>
            </x-ui.cartao>

            <x-ui.cartao class="flex flex-col gap-5">
                <div>
                    <h2 class="font-titulo text-lg text-texto">Quem vai receber o acesso</h2>
                    <p class="mt-1 text-sm text-texto-mudo">
                        Entra como dono e cadastra o resto da equipe por conta própria.
                        O Pulso não consegue criar usuários dentro da academia depois — e é assim de propósito.
                    </p>
                </div>

                <x-ui.grade-formulario>
                    <x-ui.campo largura="50" nome="dono_nome" rotulo="Nome" wire:model.blur="dono_nome" />
                    <x-ui.campo largura="50" nome="dono_email" rotulo="E-mail" tipo="email"
                                wire:model.blur="dono_email" />
                </x-ui.grade-formulario>
            </x-ui.cartao>

            <div class="flex flex-wrap gap-2">
                <x-ui.botao tipo="submit">Criar academia e gerar acesso</x-ui.botao>
                <x-ui.botao :href="route('administracao.academias.lista')" variante="secundario">Cancelar</x-ui.botao>
            </div>
        </form>
    @endif
</div>
