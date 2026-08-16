@props([
    'titulo' => null,
    /* Item ativo da navegação: radar | alunos | matriculas | mensalidades | planos | acesso | configuracoes */
    'secao' => null,
])

{{--
    Layout de todas as telas internas.

    Estrutura: cabeçalho fixo no topo, barra lateral à esquerda no desktop e
    em gaveta no celular, e uma barra inferior com os quatro destinos mais
    usados — no celular, o polegar alcança embaixo, não em cima.

    O estado da barra lateral (recolhida/expandida) é preferência do usuário e
    viaja com ele: fica em users.preferencias. O localStorage aqui só evita o
    "pulo" antes da primeira pintura.
--}}

@php
    $itens = [
        ['id' => 'radar', 'rotulo' => 'Radar', 'icone' => 'M10 2.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15Zm0 4a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Zm0 3v.01', 'principal' => true, 'url' => route('painel.inicio')],
        ['id' => 'alunos', 'rotulo' => 'Alunos', 'icone' => 'M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm-6 7a6 6 0 0 1 12 0', 'principal' => true, 'url' => route('alunos.lista')],
        ['id' => 'matriculas', 'rotulo' => 'Matrículas', 'icone' => 'M5 3h10v14l-5-3-5 3V3Z', 'principal' => false, 'url' => route('matriculas.lista')],
        ['id' => 'mensalidades', 'rotulo' => 'Mensalidades', 'icone' => 'M2.5 6.5h15v9h-15v-9Zm0 3.5h15M5.5 13h3', 'principal' => true, 'url' => route('mensalidades.lista')],
        ['id' => 'planos', 'rotulo' => 'Planos', 'icone' => 'M3.5 5.5h13M3.5 10h13M3.5 14.5h8', 'principal' => false, 'url' => route('planos.lista')],
        ['id' => 'acesso', 'rotulo' => 'Acesso', 'icone' => 'M6.5 3h-3v3M13.5 3h3v3M6.5 17h-3v-3M13.5 17h3v-3M7.5 8v1M12.5 8v1M7.5 12.5s1 1 2.5 1 2.5-1 2.5-1', 'principal' => true],
        ['id' => 'configuracoes', 'rotulo' => 'Configurações', 'icone' => 'M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Zm7-2.5c0 .5-.05.9-.13 1.35l1.4 1.1-1.5 2.6-1.7-.6a6.6 6.6 0 0 1-2.3 1.35L12.5 18h-3l-.27-1.8a6.6 6.6 0 0 1-2.3-1.35l-1.7.6-1.5-2.6 1.4-1.1a6.7 6.7 0 0 1 0-2.7l-1.4-1.1 1.5-2.6 1.7.6a6.6 6.6 0 0 1 2.3-1.35L9.5 2h3l.27 1.8a6.6 6.6 0 0 1 2.3 1.35l1.7-.6 1.5 2.6-1.4 1.1c.08.45.13.85.13 1.35Z', 'principal' => false],
    ];

    $principais = array_values(array_filter($itens, fn ($i) => $i['principal']));
@endphp

<x-layout.base :titulo="$titulo" :com-livewire="true">
    <div
        class="min-h-dvh"
        x-data="{
            recolhida: JSON.parse(localStorage.getItem('pulso.sidebar') ?? 'false'),
            gaveta: false,
            alternar() {
                this.recolhida = ! this.recolhida;
                localStorage.setItem('pulso.sidebar', JSON.stringify(this.recolhida));
                fetch('{{ route('preferencias.salvar') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ sidebar_recolhida: this.recolhida }),
                }).catch(() => {});
            },
        }"
    >
        <a href="#conteudo"
           class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50
                  focus:rounded-md focus:bg-acao focus:px-4 focus:py-2 focus:text-acao-texto">
            Ir para o conteúdo
        </a>

        {{-- ============ Cabeçalho ============ --}}
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-borda bg-superficie px-4">
            <button type="button" @click="gaveta = true"
                    class="inline-flex size-toque items-center justify-center rounded-md text-texto-2
                           transition-colors hover:bg-superficie-2 hover:text-texto lg:hidden
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                <span class="sr-only">Abrir menu</span>
                <svg viewBox="0 0 20 20" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" aria-hidden="true"><path d="M3 6h14M3 10h14M3 14h14" /></svg>
            </button>

            <a href="{{ route('painel.inicio') }}" class="shrink-0 rounded-md
                      focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                <x-marca.logo class="h-8 w-auto" rotulo="Pulso — início" />
            </a>

            {{-- Academia (e filial, quando houver mais de uma). --}}
            <x-painel.seletor-unidade />

            <div class="ml-auto flex items-center gap-1">
                {{ $acoesCabecalho ?? '' }}
                <x-ui.alternador-tema />
                <x-painel.menu-usuario />
            </div>
        </header>

        <div class="flex">
            {{-- ============ Barra lateral (desktop) ============ --}}
            <aside
                class="sticky top-16 hidden h-[calc(100dvh-4rem)] shrink-0 flex-col border-r border-borda
                       bg-superficie transition-[width] duration-200 lg:flex"
                :class="recolhida ? 'w-[76px]' : 'w-60'"
            >
                <nav class="flex flex-1 flex-col gap-1 overflow-y-auto p-3" aria-label="Navegação principal">
                    @foreach ($itens as $item)
                        <x-painel.item-navegacao
                            :href="$item['url'] ?? '#'"
                            :rotulo="$item['rotulo']"
                            :icone="$item['icone']"
                            :ativo="$secao === $item['id']"
                        />
                    @endforeach
                </nav>

                <div class="border-t border-borda p-3">
                    <button type="button" @click="alternar"
                            class="flex min-h-toque w-full items-center gap-3 rounded-md px-3 text-texto-2
                                   transition-colors hover:bg-superficie-2 hover:text-texto
                                   focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
                            :aria-label="recolhida ? 'Expandir menu' : 'Recolher menu'">
                        <svg viewBox="0 0 20 20" class="size-5 shrink-0 transition-transform" fill="none"
                             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                             :class="recolhida ? 'rotate-180' : ''" aria-hidden="true">
                            <path d="M11.5 4.5 6 10l5.5 5.5" />
                        </svg>
                        <span :class="recolhida ? 'sr-only' : ''">Recolher</span>
                    </button>
                </div>
            </aside>

            {{-- ============ Gaveta (celular) ============ --}}
            <div x-show="gaveta" x-cloak class="fixed inset-0 z-40 lg:hidden">
                <div class="absolute inset-0 bg-areia-950/50" @click="gaveta = false" aria-hidden="true"></div>

                <nav class="absolute inset-y-0 left-0 flex w-72 flex-col gap-1 border-r border-borda
                            bg-superficie p-3 shadow-3"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="-translate-x-full"
                     aria-label="Navegação principal">
                    <div class="mb-2 flex items-center justify-between">
                        <x-marca.logo class="h-8 w-auto" />
                        <button type="button" @click="gaveta = false"
                                class="inline-flex size-toque items-center justify-center rounded-md text-texto-2
                                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                            <span class="sr-only">Fechar menu</span>
                            <svg viewBox="0 0 20 20" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" /></svg>
                        </button>
                    </div>

                    @foreach ($itens as $item)
                        <x-painel.item-navegacao
                            :href="$item['url'] ?? '#'"
                            :rotulo="$item['rotulo']"
                            :icone="$item['icone']"
                            :ativo="$secao === $item['id']"
                        />
                    @endforeach
                </nav>
            </div>

            {{-- ============ Conteúdo ============ --}}
            <main id="conteudo" class="min-w-0 flex-1 pb-24 lg:pb-0">
                <div class="mx-auto flex max-w-6xl flex-col gap-6 p-4 sm:p-6">
                    {{ $avisos ?? '' }}
                    {{ $slot }}
                </div>
            </main>
        </div>

        {{-- ============ Barra inferior (celular) ============ --}}
        <nav class="fixed inset-x-0 bottom-0 z-30 flex border-t border-borda bg-superficie lg:hidden"
             aria-label="Atalhos">
            @foreach ($principais as $item)
                <a href="{{ $item['url'] ?? '#' }}"
                   @if ($secao === $item['id']) aria-current="page" @endif
                   class="flex min-h-toque flex-1 flex-col items-center justify-center gap-0.5 py-2 text-xs
                          transition-colors
                          {{ $secao === $item['id'] ? 'text-acao' : 'text-texto-mudo hover:text-texto' }}">
                    <svg viewBox="0 0 20 20" class="size-6" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="{{ $item['icone'] }}" />
                    </svg>
                    {{ $item['rotulo'] }}
                </a>
            @endforeach
        </nav>

        <x-ui.notificacoes />
    </div>
</x-layout.base>
