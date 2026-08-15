@props([
    'destaque' => false,
])

{{--
    Superficie base: usada de cartao de aluno a bloco do Radar. No celular a
    tabela financeira vira lista destes, com o valor a direita em tabular-nums.
--}}

<div {{ $attributes->merge([
    'class' => 'rounded-lg border bg-superficie p-5 shadow-1 '
        .($destaque ? 'border-acao' : 'border-borda'),
]) }}>
    {{ $slot }}
</div>
