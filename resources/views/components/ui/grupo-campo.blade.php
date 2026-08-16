@props([
    'nome',
    'rotulo',
    'ajuda' => null,
    /* Obrigatorio e o padrao: numa ficha de aluno quase tudo e. Marcar
       asterisco em tudo vira ruido; marcamos o que e opcional. */
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
            @unless ($obrigatorio)
                <span class="font-normal text-texto-mudo">(opcional)</span>
            @endunless
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
