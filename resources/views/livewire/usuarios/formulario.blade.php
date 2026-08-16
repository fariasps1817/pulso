<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        :titulo="$this->editando() ? 'Editar usuário' : 'Novo usuário'"
        :voltar-para="['rotulo' => 'Usuários', 'url' => route('usuarios.lista')]"
    />

    @if ($senhaTemporaria)
        {{--
            Aparece UMA vez, no lugar do formulário. Sair desta tela sem
            copiar significa gerar outra senha — e é melhor assim do que
            guardar a senha em algum lugar para poder mostrar de novo.
        --}}
        <x-ui.cartao destaque class="flex flex-col gap-4">
            <div>
                <h2 class="font-titulo text-lg text-texto">Usuário criado</h2>
                <p class="mt-1 text-texto-2">
                    Passe a senha abaixo para {{ $name }}. Ela é temporária: no primeiro acesso,
                    o sistema vai exigir que ele escolha a própria senha.
                </p>
            </div>

            <p class="numeros rounded-md border border-borda-forte bg-superficie-2 px-4 py-3
                      text-center font-titulo text-2xl tracking-wider text-texto select-all">
                {{ $senhaTemporaria }}
            </p>

            <p class="text-sm text-texto-mudo">
                Esta senha não será mostrada de novo. Se ela se perder, gere outra pela lista de usuários.
            </p>

            <div class="flex flex-wrap gap-2">
                <x-ui.botao :href="route('usuarios.lista')">Concluir</x-ui.botao>
                <x-ui.botao :href="route('usuarios.novo')" variante="secundario">Cadastrar outro</x-ui.botao>
            </div>
        </x-ui.cartao>
    @else
        <form wire:submit="salvar" class="flex flex-col gap-6">
            <x-ui.cartao class="flex flex-col gap-5">
                <h2 class="font-titulo text-lg text-texto">Dados de acesso</h2>

                <x-ui.grade-formulario>
                    <x-ui.campo largura="50" nome="name" rotulo="Nome completo" wire:model.blur="name" />

                    <x-ui.campo largura="50" nome="email" rotulo="E-mail" tipo="email"
                                wire:model.blur="email" />

                    <x-ui.selecao
                        largura="25"
                        nome="papel"
                        rotulo="Papel"
                        wire:model.live="papel"
                        :vazio="null"
                        :opcoes="$papeisDisponiveis"
                    />

                    <x-ui.selecao
                        largura="25"
                        nome="unidade_padrao_id"
                        rotulo="Unidade padrão"
                        wire:model="unidade_padrao_id"
                        :opcoes="$listaDeUnidades->pluck('nome', 'id')->all()"
                    />
                </x-ui.grade-formulario>
            </x-ui.cartao>

            @if ($temFiliais)
                <x-ui.cartao class="flex flex-col gap-5">
                    <h2 class="font-titulo text-lg text-texto">Alcance entre unidades</h2>

                    <x-ui.interruptor
                        nome="acessa_todas_unidades"
                        rotulo="Enxerga todas as unidades"
                        :ligado="$acessa_todas_unidades"
                        wire:model.live="acessa_todas_unidades"
                        descricao="Ligado, dispensa o vinculo por unidade — inclusive nas filiais criadas depois."
                    />

                    @unless ($acessa_todas_unidades)
                        <x-ui.interruptor
                            nome="pode_alternar_unidade"
                            rotulo="Pode alternar entre as unidades vinculadas"
                            :ligado="$pode_alternar_unidade"
                            wire:model.live="pode_alternar_unidade"
                            descricao="Desligado, a pessoa fica presa a unidade padrao."
                        />

                        @if ($pode_alternar_unidade)
                            <fieldset class="flex flex-col gap-2">
                                <legend class="text-sm font-medium text-texto-2">Unidades vinculadas</legend>

                                @foreach ($listaDeUnidades as $unidade)
                                    <label class="flex min-h-toque items-center gap-3" wire:key="unidade-{{ $unidade->id }}">
                                        <input type="checkbox" value="{{ $unidade->id }}" wire:model="unidades"
                                               class="size-5 rounded-sm border-borda-forte text-acao
                                                      focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                                        <span class="text-texto">{{ $unidade->nome }}</span>
                                    </label>
                                @endforeach
                            </fieldset>
                        @endif
                    @endunless
                </x-ui.cartao>
            @endif

            <x-ui.cartao class="flex flex-col gap-5">
                <h2 class="font-titulo text-lg text-texto">Segurança</h2>

                <x-ui.interruptor
                    nome="sessao_unica"
                    rotulo="Sessão única"
                    :ligado="$sessao_unica"
                    wire:model="sessao_unica"
                    descricao="Entrar em outro aparelho encerra a sessão anterior."
                />

                @if ($this->editando())
                    <x-ui.interruptor
                        nome="ativo"
                        rotulo="Ativo"
                        :ligado="$ativo"
                        wire:model="ativo"
                        descricao="Inativo não entra no sistema, mas o histórico dele é preservado."
                    />
                @endif
            </x-ui.cartao>

            <div class="flex flex-wrap gap-2">
                <x-ui.botao tipo="submit">
                    {{ $this->editando() ? 'Salvar' : 'Cadastrar e gerar senha' }}
                </x-ui.botao>
                <x-ui.botao :href="route('usuarios.lista')" variante="secundario">Cancelar</x-ui.botao>
            </div>
        </form>
    @endif
</div>
