@php
    $usuario = auth()->user();
    $academia = $usuario->academia;
    $atual = $usuario->unidadeAtual();
    $podeTrocar = $usuario->podeTrocarDeUnidade();
@endphp

{{--
    Identificação da academia no cabeçalho.

    Três formas, nesta ordem de decisão:

    1. Sem unidade acessível  → aviso, porque é erro de cadastro e não deve
       passar despercebido. Antes, o vínculo vazio liberava a rede inteira em
       silêncio; agora não libera nada e avisa.
    2. Não pode trocar (ou só tem uma) → TEXTO SIMPLES, não lista desabilitada.
       Controle desligado na tela anuncia que existe algo que a pessoa não pode
       fazer, e isso só gera pergunta para o gerente.
    3. Pode trocar → seletor.

    Academia de uma unidade só nunca vê a palavra "unidade": é jargão do
    sistema vazando para quem tem uma loja e não pediu por isso.
--}}

@if ($academia)
    @php
        $icone = 'M3 17h14M4.5 17V6l5.5-3 5.5 3v11M8.5 17v-4h3v4';
        // Com filial, o nome da unidade acompanha o da academia; sem filial,
        // o nome da academia basta.
        $temFiliais = $academia->unidades()->where('ativa', true)->count() > 1;
    @endphp

    @if ($atual === null)
        <span class="hidden items-center gap-2 rounded-md bg-avencer-fundo px-2.5 py-1
                     text-sm text-avencer-texto sm:flex"
              title="Peça ao gestor da academia para vincular sua unidade.">
            <svg viewBox="0 0 20 20" class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10 7.5v4M10 14.5v.01M8.6 3.2 2.3 14a1.6 1.6 0 0 0 1.4 2.4h12.6a1.6 1.6 0 0 0 1.4-2.4L11.4 3.2a1.6 1.6 0 0 0-2.8 0Z" />
            </svg>
            Sem unidade vinculada
        </span>
    @elseif (! $podeTrocar)
        <span class="hidden min-w-0 items-center gap-2 sm:flex">
            <svg viewBox="0 0 20 20" class="size-4 shrink-0 text-texto-mudo" fill="none" stroke="currentColor"
                 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="{{ $icone }}" />
            </svg>
            <span class="truncate text-texto">
                <span class="font-medium">{{ $academia->nome }}</span>
                @if ($temFiliais)
                    <span class="text-texto-mudo">·</span> {{ $atual->nome }}
                @endif
            </span>
        </span>
    @else
        <x-ui.menu rotulo="Trocar de unidade" alinhamento="esquerda" class="hidden min-w-0 sm:block">
            <x-slot:gatilho>
                <span class="flex min-w-0 items-center gap-2">
                    <svg viewBox="0 0 20 20" class="size-4 shrink-0 text-texto-mudo" fill="none" stroke="currentColor"
                         stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="{{ $icone }}" />
                    </svg>
                    <span class="truncate text-texto">
                        <span class="font-medium">{{ $academia->nome }}</span>
                        <span class="text-texto-mudo">·</span> {{ $atual->nome }}
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

            @foreach ($usuario->unidadesAcessiveis() as $unidade)
                <form method="POST" action="{{ route('painel.trocar-unidade') }}">
                    @csrf
                    <input type="hidden" name="unidade_id" value="{{ $unidade->id }}">
                    <x-ui.menu-item
                        tipo="submit"
                        :icone="$unidade->is($atual) ? 'M4 10.5 8 14.5 16 6' : null"
                        :class="$unidade->is($atual) ? 'font-medium text-acao' : ''"
                    >
                        {{ $unidade->nome }}
                    </x-ui.menu-item>
                </form>
            @endforeach
        </x-ui.menu>
    @endif
@endif
