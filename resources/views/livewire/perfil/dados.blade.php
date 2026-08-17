<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        titulo="Meu perfil"
        subtitulo="Seus dados e sua senha."
    />

    <form wire:submit="salvarDados">
        <x-ui.cartao class="flex flex-col gap-5">
            <div>
                <h2 class="font-titulo text-lg text-texto">Seus dados</h2>
                <p class="mt-1 text-sm text-texto-mudo">
                    O e-mail é com o que você entra no sistema.
                </p>
            </div>

            <x-ui.grade-formulario>
                <x-ui.campo largura="50" nome="name" rotulo="Nome" wire:model.blur="name" />
                <x-ui.campo largura="50" nome="email" rotulo="E-mail" tipo="email" wire:model.blur="email" />
            </x-ui.grade-formulario>

            <div>
                <x-ui.botao tipo="submit">Salvar</x-ui.botao>
            </div>
        </x-ui.cartao>
    </form>

    <form wire:submit="salvarSenha">
        <x-ui.cartao class="flex flex-col gap-5">
            <div>
                <h2 class="font-titulo text-lg text-texto">Trocar a senha</h2>
                <p class="mt-1 text-sm text-texto-mudo">
                    A senha atual é pedida mesmo com você já logado — é o que protege
                    a conta num computador de balcão deixado destravado.
                </p>
            </div>

            <x-ui.grade-formulario>
                <x-ui.campo largura="50" nome="senha_atual" rotulo="Senha atual" tipo="password"
                            autocomplete="current-password" wire:model="senha_atual" />

                <x-ui.campo largura="25" nome="senha" rotulo="Nova senha" tipo="password"
                            autocomplete="new-password" wire:model="senha" />

                <x-ui.campo largura="25" nome="senha_confirmation" rotulo="Repita a nova senha" tipo="password"
                            autocomplete="new-password" wire:model="senha_confirmation" />
            </x-ui.grade-formulario>

            <div>
                <x-ui.botao tipo="submit">Trocar senha</x-ui.botao>
            </div>
        </x-ui.cartao>
    </form>

    {{-- O que a pessoa NÃO muda aqui, dito para não parecer falta. --}}
    <x-ui.cartao class="flex flex-col gap-3">
        <h2 class="font-titulo text-lg text-texto">Seu acesso</h2>

        <dl class="grid gap-3 sm:grid-cols-3">
            <div>
                <dt class="text-sm text-texto-mudo">Papel</dt>
                <dd class="text-texto">
                    {{ App\Support\Academia\Papeis::rotulo(auth()->user()->getRoleNames()->first()) }}
                </dd>
            </div>
            <div>
                <dt class="text-sm text-texto-mudo">Unidade</dt>
                <dd class="text-texto">
                    {{ auth()->user()->acessa_todas_unidades ? 'Todas' : (auth()->user()->unidadePadrao?->nome ?? '—') }}
                </dd>
            </div>
            <div>
                <dt class="text-sm text-texto-mudo">Sessão única</dt>
                <dd class="text-texto">{{ auth()->user()->sessao_unica ? 'Ligada' : 'Desligada' }}</dd>
            </div>
        </dl>

        <p class="text-sm text-texto-mudo">
            Papel, unidade e sessão única são definidos pela gerência da academia.
        </p>
    </x-ui.cartao>
</div>
