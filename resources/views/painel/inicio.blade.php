<x-layout.base titulo="Painel">
    <div class="mx-auto flex min-h-dvh max-w-3xl flex-col px-5 py-10">
        <header class="flex items-center justify-between gap-4">
            <x-marca.logo class="h-9 w-auto" />

            <div class="flex items-center gap-2">
                <x-ui.alternador-tema />

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-ui.botao tipo="submit" variante="secundario">Sair</x-ui.botao>
                </form>
            </div>
        </header>

        <main class="flex flex-1 items-center">
            <x-ui.cartao class="w-full">
                <h1 class="text-2xl text-texto">Olá, {{ auth()->user()->name }}.</h1>

                <p class="prosa mt-3 text-texto-2">
                    A autenticação está funcionando. O painel de verdade — Radar, alunos, planos,
                    matrículas e mensalidades — entra nas próximas etapas do projeto.
                </p>

                <p class="mt-6 text-sm text-texto-mudo">
                    Etapa concluída: fundação, design system, página inicial e acesso.
                </p>
            </x-ui.cartao>
        </main>
    </div>
</x-layout.base>
