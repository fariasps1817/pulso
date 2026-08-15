{{--
    Alterna claro -> escuro -> sistema -> claro.

    O comportamento vive em resources/js/tema.js, ligado por delegacao no
    atributo data-tema-alternar. Os dois icones ficam sempre no DOM e quem
    decide qual aparece e o CSS, a partir do tema vigente — assim o botao
    funciona mesmo antes do JS carregar.
--}}

<button
    type="button"
    data-tema-alternar
    class="alternador-tema inline-flex size-toque items-center justify-center rounded-md
           text-texto-2 transition-colors hover:bg-superficie-2 hover:text-texto
           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
    aria-label="Alternar tema"
    title="Alternar tema"
>
    {{-- Sol: aparece no tema claro, oferecendo a troca para o escuro. --}}
    <svg viewBox="0 0 20 20" class="icone-claro size-5" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
        <circle cx="10" cy="10" r="3.6" />
        <path d="M10 1.5v2M10 16.5v2M18.5 10h-2M3.5 10h-2M16 4l-1.4 1.4M5.4 14.6 4 16M16 16l-1.4-1.4M5.4 5.4 4 4" />
    </svg>

    {{-- Lua: aparece no tema escuro. --}}
    <svg viewBox="0 0 20 20" class="icone-escuro size-5" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
         aria-hidden="true" focusable="false">
        <path d="M16.5 12.3A7 7 0 0 1 7.7 3.5a7 7 0 1 0 8.8 8.8Z" />
    </svg>

    <span class="sr-only">Alternar entre tema claro, escuro e o do sistema</span>
</button>
