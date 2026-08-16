@php
    $usuario = auth()->user();
    $academia = $usuario->academia;

    /*
     * O usuário sem vínculo com unidade nenhuma enxerga todas — é o caso do
     * dono, e evita criar um vínculo novo toda vez que uma filial abre.
     */
    /*
     * Ordenadas por cadastro, não por nome: a unidade registrada primeiro é a
     * principal, e é ela que deve abrir por padrão. Alfabético faria a rede
     * abrir na "Aldeota" em vez da "Matriz".
     */
    $unidades = $academia
        ? ($usuario->enxergaTodasAsUnidades()
            ? $academia->unidades()->where('ativa', true)->orderBy('id')->get()
            : $usuario->unidades()->where('ativa', true)->orderBy('id')->get())
        : collect();

    $atual = $unidades->firstWhere('id', $usuario->preferencia('unidade_id')) ?? $unidades->first();
    $temFiliais = $unidades->count() > 1;
@endphp

{{--
    Identificação da academia no cabeçalho.

    Academia de uma unidade só mostra apenas o nome dela — o guia de domínio é
    explícito: "cliente de uma loja só nunca vê a palavra unidade na tela".
    Escrever "Unidade: Matriz" para quem tem uma loja é jargão do sistema
    vazando para quem não pediu.

    Com filiais, aparece "Academia · Filial" e o nome vira seletor.
--}}

@if ($academia)
    @if (! $temFiliais)
        <span class="hidden min-w-0 items-center gap-2 sm:flex">
            <svg viewBox="0 0 20 20" class="size-4 shrink-0 text-texto-mudo" fill="none" stroke="currentColor"
                 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 17h14M4.5 17V6l5.5-3 5.5 3v11M8.5 17v-4h3v4" />
            </svg>
            <span class="truncate font-medium text-texto">{{ $academia->nome }}</span>
        </span>
    @else
        <x-ui.menu rotulo="Trocar de unidade" alinhamento="esquerda" class="hidden min-w-0 sm:block">
            <x-slot:gatilho>
                <span class="flex min-w-0 items-center gap-2">
                    <svg viewBox="0 0 20 20" class="size-4 shrink-0 text-texto-mudo" fill="none" stroke="currentColor"
                         stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 17h14M4.5 17V6l5.5-3 5.5 3v11M8.5 17v-4h3v4" />
                    </svg>
                    <span class="truncate text-texto">
                        <span class="font-medium">{{ $academia->nome }}</span>
                        <span class="text-texto-mudo">·</span>
                        <span>{{ $atual?->nome }}</span>
                    </span>
                    <svg viewBox="0 0 20 20" class="size-4 shrink-0 text-texto-mudo" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m6 8 4 4 4-4" />
                    </svg>
                </span>
            </x-slot:gatilho>

            <p class="px-4 pt-2 pb-1 text-xs font-medium tracking-wide text-texto-mudo uppercase">
                {{ $academia->nome }}
            </p>

            @foreach ($unidades as $unidade)
                <x-ui.menu-item
                    href="#"
                    :icone="$unidade->is($atual)
                        ? 'M4 10.5 8 14.5 16 6'
                        : null"
                    :class="$unidade->is($atual) ? 'font-medium text-acao' : ''"
                >
                    {{ $unidade->nome }}
                </x-ui.menu-item>
            @endforeach
        </x-ui.menu>
    @endif
@endif
