<x-layout.painel titulo="Configurações" secao="configuracoes">
    <x-ui.cabecalho-pagina
        titulo="Configurações"
        subtitulo="O que a academia ajusta por conta própria."
    />

    @php
        $areas = [
            [
                'rotulo' => 'Usuários',
                'descricao' => 'Quem entra no Pulso, com qual papel e em qual unidade.',
                'url' => auth()->user()->can('viewAny', App\Models\User::class) ? route('usuarios.lista') : null,
            ],
            [
                'rotulo' => 'Dados da academia',
                'descricao' => 'Razão social, CNPJ e logotipo — usados nos recibos e contratos em PDF.',
                'url' => null,
            ],
            [
                'rotulo' => 'Unidades',
                'descricao' => 'Endereço e telefone de cada filial.',
                'url' => null,
            ],
            [
                'rotulo' => 'Regras de cobrança',
                'descricao' => 'Dias de tolerância antes de bloquear na catraca, e o que conta como baixa frequência.',
                'url' => null,
            ],
        ];
    @endphp

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        @foreach ($areas as $area)
            @if ($area['url'])
                <a href="{{ $area['url'] }}"
                   class="rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                    <x-ui.cartao class="h-full transition-colors hover:bg-superficie-2">
                        <p class="font-titulo text-lg text-acao">{{ $area['rotulo'] }}</p>
                        <p class="mt-1 text-texto-2">{{ $area['descricao'] }}</p>
                    </x-ui.cartao>
                </a>
            @else
                {{-- Área ainda não construída: aparece esmaecida e dizendo isso,
                     em vez de sumir. Some sem explicação parece defeito. --}}
                <x-ui.cartao class="h-full opacity-60">
                    <p class="font-titulo text-lg text-texto">{{ $area['rotulo'] }}</p>
                    <p class="mt-1 text-texto-2">{{ $area['descricao'] }}</p>
                    <p class="mt-3 text-sm text-texto-mudo">Em construção.</p>
                </x-ui.cartao>
            @endif
        @endforeach
    </div>
</x-layout.painel>
