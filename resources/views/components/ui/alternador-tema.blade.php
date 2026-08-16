{{--
    Alterna: sistema → claro → escuro → sistema.

    TRÊS estados, TRÊS ícones. Antes, "sistema" e "claro" compartilhavam o sol:
    quando o sistema já estava no claro, clicar não mudava nada visível e dava
    a impressão de que o botão não funcionou.

    O ícone reflete a ESCOLHA (data-tema-escolhido no <html>), não o tema
    resolvido — é por isso que "sistema" consegue ter símbolo próprio. O
    atributo é gravado pelo script síncrono do layout base, antes da primeira
    pintura, então o ícone certo já aparece sem esperar o JS principal.
--}}

<button
    type="button"
    data-tema-alternar
    class="alternador-tema inline-flex size-toque items-center justify-center rounded-md
           text-texto-2 transition-colors hover:bg-superficie-2 hover:text-texto
           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
    aria-label="Tema"
    title="Tema"
>
    {{-- Monitor: acompanhando o sistema. --}}
    <svg viewBox="0 0 20 20" class="icone-sistema size-5" fill="none" stroke="currentColor"
         stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <rect x="2.5" y="3.5" width="15" height="10" rx="1.5" />
        <path d="M7 17h6M10 13.5V17" />
    </svg>

    {{-- Sol: tema claro escolhido explicitamente. --}}
    <svg viewBox="0 0 20 20" class="icone-claro size-5" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
        <circle cx="10" cy="10" r="3.6" />
        <path d="M10 1.5v2M10 16.5v2M18.5 10h-2M3.5 10h-2M16 4l-1.4 1.4M5.4 14.6 4 16M16 16l-1.4-1.4M5.4 5.4 4 4" />
    </svg>

    {{-- Lua: tema escuro escolhido explicitamente. --}}
    <svg viewBox="0 0 20 20" class="icone-escuro size-5" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="M16.5 12.3A7 7 0 0 1 7.7 3.5a7 7 0 1 0 8.8 8.8Z" />
    </svg>

    <span class="sr-only">Alternar entre acompanhar o sistema, tema claro e tema escuro</span>
</button>
