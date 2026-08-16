@props([
    /* [['id' => 'dados', 'rotulo' => 'Dados pessoais'], ...] */
    'abas' => [],
    'inicial' => null,
])

{{--
    Abas da tela de detalhes. Teclado funciona como se espera de abas:
    setas navegam, Home e End vão para as pontas.

    No celular a faixa rola na horizontal em vez de quebrar linha — abas
    empilhadas ocupam meia tela e empurram o conteúdo para baixo.
--}}

@php
    $ativa = $inicial ?? ($abas[0]['id'] ?? null);
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col']) }} x-data="{ aba: '{{ $ativa }}' }">
    <div class="-mx-1 overflow-x-auto border-b border-borda px-1">
        <div role="tablist" class="flex gap-1 whitespace-nowrap">
            @foreach ($abas as $item)
                <button
                    type="button"
                    role="tab"
                    id="aba-{{ $item['id'] }}"
                    :aria-selected="aba === '{{ $item['id'] }}' ? 'true' : 'false'"
                    :tabindex="aba === '{{ $item['id'] }}' ? 0 : -1"
                    aria-controls="painel-{{ $item['id'] }}"
                    @click="aba = '{{ $item['id'] }}'"
                    @keydown.right.prevent="$el.nextElementSibling?.focus()"
                    @keydown.left.prevent="$el.previousElementSibling?.focus()"
                    @keydown.home.prevent="$el.parentElement.firstElementChild.focus()"
                    @keydown.end.prevent="$el.parentElement.lastElementChild.focus()"
                    @focus="aba = '{{ $item['id'] }}'"
                    class="min-h-toque border-b-2 px-4 text-base transition-colors
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
                    :class="aba === '{{ $item['id'] }}'
                        ? 'border-acao text-acao font-medium'
                        : 'border-transparent text-texto-2 hover:text-texto'"
                >
                    {{ $item['rotulo'] }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="pt-6">{{ $slot }}</div>
</div>
