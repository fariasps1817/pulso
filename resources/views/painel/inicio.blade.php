<x-layout.painel titulo="Radar" secao="radar">
    <x-slot:unidades>
        <div class="hidden min-w-0 items-center gap-2 sm:flex">
            <span class="text-sm text-texto-mudo">Unidade:</span>
            <span class="truncate font-medium text-texto">Centro</span>
        </div>
    </x-slot:unidades>

    <x-slot:acoesCabecalho>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.botao tipo="submit" variante="secundario">Sair</x-ui.botao>
        </form>
    </x-slot:acoesCabecalho>

    <x-slot:avisos>
        <x-ui.aviso tipo="informativo" titulo="Etapa 2b — design system">
            O painel de verdade entra depois das migrations. Esta tela existe para conferir o
            layout: recolha a barra lateral, alterne o tema e reduza a janela para ver a barra
            inferior aparecer.
        </x-ui.aviso>
    </x-slot:avisos>

    <x-ui.cabecalho-pagina titulo="Radar" subtitulo="O pulso da sua academia hoje">
        <x-ui.botao :href="route('catalogo')" variante="secundario">Ver catálogo</x-ui.botao>
    </x-ui.cabecalho-pagina>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.cartao>
            <p class="text-sm text-texto-mudo">Vencidas</p>
            <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ \App\Support\Formato::dinheiro(4820) }}</p>
            <x-ui.pilula estado="vencido" class="mt-3">12 alunos</x-ui.pilula>
        </x-ui.cartao>

        <x-ui.cartao>
            <p class="text-sm text-texto-mudo">Vencem hoje</p>
            <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ \App\Support\Formato::dinheiro(1350) }}</p>
            <x-ui.pilula estado="avencer" class="mt-3">7 alunos</x-ui.pilula>
        </x-ui.cartao>

        <x-ui.cartao>
            <p class="text-sm text-texto-mudo">Sumiram há 15 dias</p>
            <p class="numeros mt-1 font-titulo text-3xl text-texto">23</p>
            <x-ui.pilula estado="frequencia" class="mt-3">risco de evasão</x-ui.pilula>
        </x-ui.cartao>
    </div>

    <x-ui.estado-vazio
        titulo="Olá, {{ auth()->user()->name }}."
        descricao="A autenticação e o design system estão de pé. Alunos, matrículas e mensalidades entram depois das migrations."
        icone="M10 2.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15Zm0 4a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"
    />
</x-layout.painel>
