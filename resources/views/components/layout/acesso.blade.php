@props([
    'titulo' => null,
    'chamada' => null,
    'apoio' => null,
])

{{--
    Layout das telas de autenticacao: uma coluna, centrada, sem navegacao.
    Nada compete com o formulario — quem chega aqui tem uma tarefa so.
--}}

<x-layout.base :titulo="$titulo" class="min-h-dvh bg-fundo text-texto antialiased">
    <div class="flex min-h-dvh flex-col">
        <div class="flex items-center justify-end px-5 py-4">
            <x-ui.alternador-tema />
        </div>

        <main class="flex flex-1 items-center justify-center px-5 pb-16">
            <div class="w-full max-w-md">
                <div class="flex flex-col items-center text-center">
                    {{-- O logo e a saida para o site: quem chegou aqui por engano volta por ele. --}}
                    <a href="{{ route('site.inicio') }}"
                       class="inline-flex rounded-md p-1 transition-opacity hover:opacity-80
                              focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                        <x-marca.logo class="h-12 w-auto" rotulo="Pulso — ir para o site" />
                    </a>

                    @if ($chamada)
                        <h1 class="mt-7 text-2xl text-texto">{{ $chamada }}</h1>
                    @endif

                    @if ($apoio)
                        <p class="mt-2 text-texto-2">{{ $apoio }}</p>
                    @endif
                </div>

                @if (session('status'))
                    <div role="status"
                         class="mt-6 flex items-start gap-2 rounded-md border border-pago-borda bg-pago-fundo p-3.5 text-sm text-pago-texto">
                        <svg viewBox="0 0 20 20" class="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M4 10.5 8 14.5 16 6" />
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                <div class="mt-6 rounded-lg border border-borda bg-superficie p-6 shadow-1 sm:p-8">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-sm text-texto-mudo">
                    {{ config('pulso.marca.nome') }} · {{ config('pulso.marca.assinatura') }}
                </p>
            </div>
        </main>
    </div>
</x-layout.base>
