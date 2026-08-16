@props([
    'nome',
    'rotulo',
    'ajuda' => null,
    /* Campo obrigatorio recebe asterisco vermelho ao lado do rotulo. O
       opcional nao recebe marca nenhuma. */
    'obrigatorio' => true,
    'campoId' => null,
    /* Alguns controles (caixa de selecao, interruptor) trazem o proprio
       rotulo ao lado, entao o rotulo de cima seria repeticao. */
    'semRotulo' => false,
    /* Quanto o campo ocupa na grade de formulario: 25, 50, 75 ou 100. */
    'largura' => '100',
])

@php
    $id = $campoId ?? $nome;
    $temErro = $errors->has($nome);

    /*
     * As classes precisam aparecer literais aqui: o Tailwind varre o codigo
     * fonte, e uma classe montada em tempo de execucao ("md:col-span-{$n}")
     * nunca seria gerada — o campo sairia sem largura nenhuma.
     */
    $colunas = [
        '25' => 'md:col-span-1',
        '50' => 'md:col-span-2',
        '75' => 'md:col-span-3',
        '100' => 'md:col-span-4',
    ][(string) $largura] ?? 'md:col-span-4';
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-1.5 '.$colunas]) }}>
    @unless ($semRotulo)
        <label for="{{ $id }}" class="text-sm font-medium text-texto-2">
            {{ $rotulo }}
            @if ($obrigatorio)
                {{--
                    O asterisco é decorativo para quem enxerga: o atributo
                    `required` no campo é o que o leitor de tela anuncia, e o
                    texto oculto abaixo cobre quem não distingue a cor.

                    Cor nunca carrega informação sozinha (guia de marca): aqui
                    são símbolo + atributo + texto para leitor de tela.
                --}}
                <span class="text-vencido-forte" aria-hidden="true">*</span>
                <span class="sr-only">(obrigatório)</span>
            @endif
        </label>
    @endunless

    {{ $slot }}

    @if ($ajuda)
        <p id="{{ $id }}-ajuda" class="text-sm text-texto-mudo">{{ $ajuda }}</p>
    @endif

    @error($nome)
        <p id="{{ $id }}-erro" class="flex items-start gap-1.5 text-sm text-vencido-texto">
            <svg viewBox="0 0 20 20" class="mt-0.5 size-4 shrink-0" fill="currentColor" aria-hidden="true" focusable="false">
                <path d="M10 1.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17ZM9 5.5h2v6H9v-6Zm0 7.5h2v2H9v-2Z" />
            </svg>
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
