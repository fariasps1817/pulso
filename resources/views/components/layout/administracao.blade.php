@props([
    'titulo' => null,
    /* Item ativo: academias | avisos */
    'secao' => null,
])

{{--
    Layout da administração do SaaS — a área da equipe do Pulso.

    Deliberadamente DIFERENTE do painel da academia: barra escura no topo e a
    palavra "Administração" ao lado da marca. Quem opera as duas coisas
    precisa saber, de relance, em qual delas está — bloquear a academia errada
    é o tipo de engano que uma tela igual à outra provoca.

    Sem seletor de unidade: o super administrador não pertence a academia
    nenhuma, e é isso que faz o isolamento valer também para ele.
--}}

@php
    $itens = [
        ['id' => 'academias', 'rotulo' => 'Academias', 'url' => route('administracao.academias.lista')],
    ];
@endphp

{{--
    `com-livewire` é obrigatório aqui: as telas desta área são componentes
    Livewire, e é esse sinalizador que traz o pacote `painel.js` — onde moram o
    Livewire, o Alpine e os nossos plugins (notificações, máscaras).

    Sem ele o Livewire ainda se injeta sozinho e a página até funciona, mas o
    Alpine sobe sem os plugins: `notificacoesPulso is not defined` no console,
    aviso de sucesso que nunca aparece e máscara de CNPJ que não formata.
--}}
<x-layout.base :titulo="$titulo" :com-livewire="true">
    <div class="min-h-dvh bg-fundo">
        <a href="#conteudo"
           class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50
                  focus:rounded-md focus:bg-acao focus:px-4 focus:py-2 focus:text-acao-texto">
            Ir para o conteúdo
        </a>

        <header class="sticky top-0 z-30 border-b border-areia-800 bg-areia-950 text-areia-50">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-5">
                <a href="{{ route('administracao.academias.lista') }}"
                   class="flex shrink-0 items-center gap-3 rounded-md
                          focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                    {{-- Só a cápsula: o logotipo segue --cor-marca, que nesta
                         barra escura ficaria ilegível. A cápsula tem cor
                         própria e constante. --}}
                    <x-marca.logo class="h-8 w-auto" variante="simbolo" :rotulo="null" />
                    <span class="font-titulo text-lg">Pulso</span>
                </a>

                <span class="rounded-pill border border-areia-700 px-2.5 py-0.5 text-sm">
                    Administração
                </span>

                <nav class="ml-4 hidden gap-1 sm:flex" aria-label="Navegação da administração">
                    @foreach ($itens as $item)
                        <a href="{{ $item['url'] }}"
                           @class([
                               'min-h-toque inline-flex items-center rounded-md px-3 transition-colors',
                               'bg-areia-800 font-medium' => $secao === $item['id'],
                               'hover:bg-areia-900' => $secao !== $item['id'],
                           ])
                           @if ($secao === $item['id']) aria-current="page" @endif>
                            {{ $item['rotulo'] }}
                        </a>
                    @endforeach
                </nav>

                <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                    @csrf
                    <button type="submit"
                            class="min-h-toque rounded-md px-3 transition-colors hover:bg-areia-900
                                   focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                        Sair
                    </button>
                </form>
            </div>
        </header>

        <main id="conteudo" class="mx-auto max-w-6xl px-5 py-8">
            <x-ui.notificacoes />

            {{ $slot }}
        </main>
    </div>
</x-layout.base>
