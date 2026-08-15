@props([
    'titulo' => null,
    'descricao' => null,
])

@php
    $contato = config('pulso.contato');
    $whatsapp = 'https://wa.me/'.$contato['whatsapp'];
@endphp

<x-layout.base :titulo="$titulo" :descricao="$descricao">
    <a href="#conteudo"
       class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50
              focus:rounded-md focus:bg-acao focus:px-4 focus:py-2 focus:text-acao-texto">
        Ir para o conteúdo
    </a>

    <header class="sticky top-0 z-40 border-b border-borda bg-superficie/85 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center gap-4 px-5 py-3">
            <a href="{{ route('site.inicio') }}" class="flex shrink-0 items-center rounded-md
                      focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                <x-marca.logo class="h-9 w-auto" rotulo="Pulso — página inicial" />
            </a>

            <nav class="ml-auto hidden items-center gap-1 md:flex" aria-label="Seções do site">
                <a href="#recursos" class="rounded-md px-3 py-2 text-texto-2 transition-colors hover:bg-superficie-2 hover:text-texto">Recursos</a>
                <a href="#radar" class="rounded-md px-3 py-2 text-texto-2 transition-colors hover:bg-superficie-2 hover:text-texto">Radar</a>
                <a href="#seguranca" class="rounded-md px-3 py-2 text-texto-2 transition-colors hover:bg-superficie-2 hover:text-texto">Segurança</a>
                <a href="#contato" class="rounded-md px-3 py-2 text-texto-2 transition-colors hover:bg-superficie-2 hover:text-texto">Contato</a>
            </nav>

            <div class="ml-auto flex items-center gap-2 md:ml-0">
                <x-ui.alternador-tema />
                <x-ui.botao :href="route('login')" variante="primario">Entrar</x-ui.botao>
            </div>
        </div>
    </header>

    <main id="conteudo">
        {{ $slot }}
    </main>

    <footer class="border-t border-borda bg-superficie">
        <div class="mx-auto max-w-6xl px-5 py-10">
            <div class="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">
                <div>
                    <x-marca.logo class="h-8 w-auto" />
                    <p class="mt-3 max-w-xs text-sm text-texto-mudo">
                        {{ config('pulso.slogans.origem') }}
                    </p>
                </div>

                <div class="text-sm">
                    <h2 class="font-titulo text-base text-texto">Falar com a gente</h2>
                    <ul class="mt-3 flex flex-col gap-2 text-texto-2">
                        <li>
                            <a href="{{ $whatsapp }}" rel="noopener" target="_blank"
                               class="rounded-sm text-acao hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                                WhatsApp {{ \App\Support\Formato::telefone($contato['whatsapp']) }}
                            </a>
                        </li>
                        <li>
                            <a href="mailto:{{ $contato['email'] }}"
                               class="rounded-sm text-acao hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                                {{ $contato['email'] }}
                            </a>
                        </li>
                        <li class="text-texto-mudo">{{ $contato['cidade'] }} — {{ $contato['uf'] }}</li>
                    </ul>
                </div>
            </div>

            <p class="mt-10 border-t border-borda pt-6 text-sm text-texto-mudo">
                &copy; {{ now()->year }} {{ config('pulso.marca.nome') }} ·
                Desenvolvido e mantido por {{ config('pulso.marca.mantenedor') }}.
            </p>
        </div>
    </footer>
</x-layout.base>
