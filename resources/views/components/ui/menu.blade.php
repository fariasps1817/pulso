@props([
    'rotulo' => 'Mais ações',
    /* esquerda | direita — de que lado o menu se alinha ao gatilho */
    'alinhamento' => 'direita',
])

{{--
    Menu suspenso (o "⋯" da tela de detalhes).

    Ações destrutivas moram aqui, e não como botão na lista, justamente para
    exigir dois toques: excluir por engano é o erro que mais dói no balcão.

    Fecha com Esc e ao clicar fora. O item é <a> ou <button> normal — o menu
    não interfere no que vai dentro.
--}}

<div {{ $attributes->merge(['class' => 'relative']) }} x-data="{ aberto: false }" @keydown.escape.window="aberto = false">
    {{--
        Com gatilho próprio (menu do usuário, por exemplo) o botão perde a
        moldura e a largura fixa: quem passou o conteúdo decide a aparência.
    --}}
    <button
        type="button"
        @click="aberto = ! aberto"
        :aria-expanded="aberto ? 'true' : 'false'"
        aria-haspopup="menu"
        class="inline-flex min-h-toque items-center justify-center rounded-md transition-colors
               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco
               {{ isset($gatilho)
                   ? 'px-2 hover:bg-superficie-2'
                   : 'size-toque border border-borda-forte bg-superficie text-texto-2 hover:bg-superficie-2 hover:text-texto' }}"
    >
        <span class="sr-only">{{ $rotulo }}</span>
        {{ $gatilho ?? '' }}
        @unless (isset($gatilho))
            <svg viewBox="0 0 20 20" class="size-5" fill="currentColor" aria-hidden="true" focusable="false">
                <circle cx="4.5" cy="10" r="1.5" /><circle cx="10" cy="10" r="1.5" /><circle cx="15.5" cy="10" r="1.5" />
            </svg>
        @endunless
    </button>

    <div
        x-show="aberto"
        x-cloak
        @click.outside="aberto = false"
        role="menu"
        class="absolute z-30 mt-1 min-w-52 overflow-hidden rounded-md border border-borda
               bg-superficie py-1 shadow-2 {{ $alinhamento === 'direita' ? 'right-0' : 'left-0' }}"
    >
        {{ $slot }}
    </div>
</div>
