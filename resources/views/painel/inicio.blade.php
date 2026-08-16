<x-layout.painel titulo="Radar" secao="radar">
    <x-slot:avisos>
        <x-ui.aviso tipo="informativo" titulo="Radar em construção">
            Os números abaixo ainda são ilustrativos. O Radar de verdade entra depois das telas
            de cadastro, quando houver aluno e mensalidade para medir.
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
        titulo="Olá, {{ \App\Support\Nomes::curto(auth()->user()->name) }}."
        descricao="O banco, o isolamento entre academias e o design system estão de pé. As telas de cadastro entram a seguir."
        icone="M10 2.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15Zm0 4a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"
    />
</x-layout.painel>
