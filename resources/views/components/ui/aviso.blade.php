@props([
    /* informativo | atencao | erro | sucesso */
    'tipo' => 'informativo',
    'titulo' => null,
    'dispensavel' => false,
])

{{--
    Faixa de recado no topo do conteúdo: aviso do super administrador,
    confirmação de ação, alerta de bloqueio próximo.

    Como toda comunicação de estado no Pulso, traz ícone + texto + cor — cor
    sozinha não informa.
--}}

@php
    $tipos = [
        'informativo' => [
            'classes' => 'border-borda bg-superficie-2 text-texto',
            'iconeCor' => 'text-acao',
            'icone' => 'M10 13.5v-4M10 6.75v.01M10 2.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15Z',
            'papel' => 'status',
        ],
        'atencao' => [
            'classes' => 'border-avencer-borda bg-avencer-fundo text-avencer-texto',
            'iconeCor' => 'text-avencer-texto',
            'icone' => 'M10 7.5v4M10 14.5v.01M8.6 3.2 2.3 14a1.6 1.6 0 0 0 1.4 2.4h12.6a1.6 1.6 0 0 0 1.4-2.4L11.4 3.2a1.6 1.6 0 0 0-2.8 0Z',
            'papel' => 'status',
        ],
        'erro' => [
            'classes' => 'border-vencido-borda bg-vencido-fundo text-vencido-texto',
            'iconeCor' => 'text-vencido-texto',
            'icone' => 'M10 5.5v6M10 14.5v.5M10 2.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15Z',
            'papel' => 'alert',
        ],
        'sucesso' => [
            'classes' => 'border-pago-borda bg-pago-fundo text-pago-texto',
            'iconeCor' => 'text-pago-texto',
            'icone' => 'M4 10.5 8 14.5 16 6',
            'papel' => 'status',
        ],
    ];

    $atual = $tipos[$tipo] ?? $tipos['informativo'];
@endphp

<div
    role="{{ $atual['papel'] }}"
    @if ($dispensavel) x-data="{ visivel: true }" x-show="visivel" @endif
    {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-md border p-4 '.$atual['classes']]) }}
>
    <svg viewBox="0 0 20 20" class="mt-0.5 size-5 shrink-0 {{ $atual['iconeCor'] }}" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="{{ $atual['icone'] }}" />
    </svg>

    <div class="min-w-0 flex-1">
        @if ($titulo)
            <p class="font-medium">{{ $titulo }}</p>
        @endif

        <div class="{{ $titulo ? 'mt-1 ' : '' }}text-sm">{{ $slot }}</div>
    </div>

    @if ($dispensavel)
        <button type="button" @click="visivel = false"
                class="-m-1 shrink-0 rounded-md p-1 transition-opacity hover:opacity-70
                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
                aria-label="Dispensar aviso">
            <svg viewBox="0 0 20 20" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" aria-hidden="true" focusable="false">
                <path d="m5 5 10 10M15 5 5 15" />
            </svg>
        </button>
    @endif
</div>
