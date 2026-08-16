@php
    use App\Support\Nomes;

    $usuario = auth()->user();
    $curto = Nomes::curto($usuario->name);
    $iniciais = Nomes::iniciais($usuario->name);

    $papel = $usuario->ehSuperAdministrador()
        ? 'Pulso'
        : ($usuario->getRoleNames()->first() ? __('papeis.'.$usuario->getRoleNames()->first()) : null);
@endphp

{{--
    Menu do usuário. Absorve o "Sair", que antes ocupava espaço fixo no
    cabeçalho para uma ação que se usa uma vez por dia.

    No celular resta só o círculo com as iniciais: o nome ocuparia metade da
    barra e a academia inteira já sabe quem está no balcão.
--}}

<x-ui.menu rotulo="Menu do usuário" alinhamento="direita">
    <x-slot:gatilho>
        <span class="flex items-center gap-2">
            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-pill
                         bg-acao-sutil text-sm font-medium text-acao">{{ $iniciais }}</span>

            <span class="hidden text-left sm:block">
                <span class="block text-sm leading-tight font-medium text-texto">{{ $curto }}</span>
                @if ($papel)
                    <span class="block text-xs leading-tight text-texto-mudo">{{ $papel }}</span>
                @endif
            </span>

            <svg viewBox="0 0 20 20" class="hidden size-4 text-texto-mudo sm:block" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m6 8 4 4 4-4" />
            </svg>
        </span>
    </x-slot:gatilho>

    <div class="border-b border-borda px-4 pt-3 pb-3">
        <p class="font-medium text-texto">{{ $usuario->name }}</p>
        <p class="truncate text-sm text-texto-mudo">{{ $usuario->email }}</p>

        @if ($papel)
            <p class="mt-2 text-sm text-texto-2">
                {{ $papel }}@if ($usuario->academia) · {{ $usuario->academia->nome }} @endif
            </p>
        @endif
    </div>

    <x-ui.menu-item href="#" icone="M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm-6 7a6 6 0 0 1 12 0">
        Meu perfil
    </x-ui.menu-item>

    <x-ui.menu-item href="#" icone="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Zm7-2.5c0 .5-.05.9-.13 1.35l1.4 1.1-1.5 2.6-1.7-.6a6.6 6.6 0 0 1-2.3 1.35L12.5 18h-3l-.27-1.8a6.6 6.6 0 0 1-2.3-1.35l-1.7.6-1.5-2.6 1.4-1.1a6.7 6.7 0 0 1 0-2.7l-1.4-1.1 1.5-2.6 1.7.6a6.6 6.6 0 0 1 2.3-1.35L9.5 2h3l.27 1.8a6.6 6.6 0 0 1 2.3 1.35l1.7-.6 1.5 2.6-1.4 1.1c.08.45.13.85.13 1.35Z">
        Preferências
    </x-ui.menu-item>

    <form method="POST" action="{{ route('logout') }}" class="border-t border-borda">
        @csrf
        <x-ui.menu-item type="submit" icone="M12.5 14.5 17 10l-4.5-4.5M17 10H7M9 3.5H4.5v13H9">
            Sair
        </x-ui.menu-item>
    </form>
</x-ui.menu>
