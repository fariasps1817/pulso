{{-- Ponte para os componentes Livewire de página inteira — ver layouts/painel. --}}

<x-layout.administracao :titulo="$titulo ?? null" :secao="$secao ?? null">
    {{ $slot }}
</x-layout.administracao>
