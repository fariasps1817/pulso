<div class="w-full max-w-md">
    <x-ui.cartao class="flex flex-col gap-5">
        <div>
            <h1 class="font-titulo text-2xl text-texto">Escolha sua senha</h1>
            <p class="mt-2 text-texto-2">
                A senha que você recebeu é temporária e outra pessoa a conhece.
                Defina a sua para continuar.
            </p>
        </div>

        <form wire:submit="salvar" class="flex flex-col gap-4">
            <x-ui.campo
                largura="100"
                nome="atual"
                rotulo="Senha temporária"
                tipo="password"
                autocomplete="current-password"
                wire:model="atual"
            />

            <x-ui.campo
                largura="100"
                nome="senha"
                rotulo="Sua nova senha"
                tipo="password"
                autocomplete="new-password"
                wire:model="senha"
            />

            <x-ui.campo
                largura="100"
                nome="senha_confirmation"
                rotulo="Repita a nova senha"
                tipo="password"
                autocomplete="new-password"
                wire:model="senha_confirmation"
            />

            <x-ui.botao tipo="submit" class="w-full">Definir senha e entrar</x-ui.botao>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            {{-- Prender alguém numa tela sem saída é pior do que o problema
                 que ela resolve. --}}
            <button type="submit"
                    class="rounded-sm text-sm text-texto-mudo hover:underline
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                Sair
            </button>
        </form>
    </x-ui.cartao>
</div>
