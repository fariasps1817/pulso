{{--
    Ponte entre os componentes Livewire de página inteira e o layout do painel.

    O Livewire renderiza a view indicada em #[Layout(...)] passando $slot; o
    layout de verdade é o componente Blade, que continua sendo usado
    diretamente pelas telas que não são Livewire.
--}}

<x-layout.painel :titulo="$titulo ?? null" :secao="$secao ?? null">
    {{ $slot }}
</x-layout.painel>
