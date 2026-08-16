{{--
    Ponte entre os componentes Livewire de página inteira e o layout de acesso
    — a mesma ideia de layouts/painel.blade.php.

    Vale para as telas que existem antes de o painel abrir: login, redefinição
    de senha e a troca da senha temporária. Nenhuma delas tem menu, e é de
    propósito: quem chega ali tem uma coisa para fazer.
--}}

{{-- Esta ponte so e usada por componente Livewire, entao o pacote vem sempre. --}}
<x-layout.acesso :titulo="$titulo ?? null" :com-livewire="true">
    {{ $slot }}
</x-layout.acesso>
