@php
    use App\Enums\SentidoAcesso;
    use App\Enums\SituacaoComando;
@endphp

{{--
    `wire:poll` de 15 segundos: a tela fica aberta no balcão o dia inteiro e
    precisa acompanhar a catraca sem ninguém apertar nada. Quinze segundos é
    curto o bastante para parecer imediato e longo o bastante para não virar
    carga desnecessária num computador de recepção.
--}}
<div class="flex flex-col gap-6" wire:poll.15s>
    <x-ui.cabecalho-pagina
        titulo="Acesso"
        subtitulo="Quem está treinando agora, e o que a catraca registrou hoje."
    />

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- A pergunta mais urgente: dá para fechar? --}}
        <x-ui.cartao class="flex flex-col gap-3">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-titulo text-lg text-texto">Na academia agora</h2>
                <span class="numeros font-titulo text-2xl text-texto">{{ $presentes->count() }}</span>
            </div>

            @forelse ($presentes as $entrada)
                <a href="{{ route('alunos.detalhes', $entrada->aluno) }}"
                   wire:key="presente-{{ $entrada->id }}"
                   class="-mx-2 flex min-h-toque items-center justify-between gap-3 rounded-md px-2 py-2
                          transition-colors hover:bg-superficie-2
                          focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                    <span class="min-w-0 truncate text-texto">{{ $entrada->aluno->nome }}</span>
                    <span class="numeros shrink-0 text-sm text-texto-mudo">
                        desde {{ $entrada->ocorreu_em->format('H:i') }}
                    </span>
                </a>
            @empty
                <p class="py-6 text-center text-sm text-texto-mudo">Ninguém na academia neste momento.</p>
            @endforelse
        </x-ui.cartao>

        {{-- O movimento do dia, o mais recente primeiro. --}}
        <x-ui.cartao class="flex flex-col gap-3">
            <h2 class="font-titulo text-lg text-texto">Movimento de hoje</h2>

            @forelse ($movimento as $passagem)
                <div wire:key="passagem-{{ $passagem->id }}"
                     class="flex min-h-toque items-center justify-between gap-3 border-b border-borda py-2 last:border-0">
                    <span class="min-w-0">
                        <span class="block truncate text-texto">
                            {{ $passagem->aluno?->nome ?? 'Não reconhecido' }}
                        </span>
                        @unless ($passagem->aluno)
                            {{-- A pista de um leitor com cadastro velho. --}}
                            <span class="numeros block text-sm text-texto-mudo">matrícula {{ $passagem->pin }}</span>
                        @endunless
                    </span>

                    <span class="flex shrink-0 items-center gap-3">
                        <span @class([
                            'text-sm',
                            'text-pago-texto' => $passagem->sentido === SentidoAcesso::Entrada,
                            'text-texto-mudo' => $passagem->sentido === SentidoAcesso::Saida,
                        ])>{{ $passagem->sentido->rotulo() }}</span>
                        <span class="numeros text-sm text-texto-2">{{ $passagem->ocorreu_em->format('H:i') }}</span>
                    </span>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-texto-mudo">Nenhuma passagem hoje.</p>
            @endforelse
        </x-ui.cartao>
    </div>

    @if ($aparelhos !== null)
        <x-ui.cartao class="flex flex-col gap-4">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-titulo text-lg text-texto">Aparelhos</h2>
                @if (! app()->isProduction())
                    <a href="{{ route('acesso.simulador') }}"
                       class="rounded-sm text-sm text-acao hover:underline
                              focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                        Abrir o simulador →
                    </a>
                @endif
            </div>

            @forelse ($aparelhos as $aparelho)
                <div wire:key="aparelho-{{ $aparelho->id }}"
                     class="flex flex-col gap-2 border-b border-borda pb-4 last:border-0 last:pb-0
                            sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-texto">{{ $aparelho->nome }}</p>
                        <p class="numeros text-sm text-texto-mudo">
                            {{ $aparelho->modelo ?? 'modelo não informado' }}
                            @if ($aparelho->numero_serie)
                                · {{ $aparelho->numero_serie }}
                            @endif
                        </p>
                        @if ($aparelho->firmware)
                            <p class="numeros text-sm text-texto-mudo">{{ $aparelho->firmware }}</p>
                        @endif
                    </div>

                    <div class="shrink-0 sm:text-right">
                        @if ($aparelho->online())
                            <x-ui.pilula estado="pago">Conectado</x-ui.pilula>
                        @elseif ($aparelho->ultimo_contato_em)
                            <x-ui.pilula estado="vencido">Sem contato</x-ui.pilula>
                        @else
                            <x-ui.pilula estado="frequencia">Nunca conectou</x-ui.pilula>
                        @endif

                        @if ($aparelho->ultimo_contato_em)
                            <p class="numeros mt-1 text-sm text-texto-mudo">
                                último contato {{ $aparelho->ultimo_contato_em->diffForHumans() }}
                            </p>
                        @endif
                    </div>
                </div>
            @empty
                <x-ui.estado-vazio
                    titulo="Nenhum aparelho cadastrado"
                    descricao="O leitor precisa estar cadastrado com o número de série para o Pulso reconhecê-lo."
                    icone="M6.5 3h-3v3M13.5 3h3v3M6.5 17h-3v-3M13.5 17h3v-3"
                />
            @endforelse
        </x-ui.cartao>

        @if ($pendencias->isNotEmpty())
            <x-ui.cartao class="flex flex-col gap-3">
                <h2 class="font-titulo text-lg text-texto">Fila do aparelho</h2>

                @foreach ($pendencias as $comando)
                    <div wire:key="comando-{{ $comando->id }}"
                         class="flex items-center justify-between gap-3 border-b border-borda py-2 last:border-0">
                        <span class="min-w-0">
                            <span class="block truncate text-texto">{{ $comando->verbo }}</span>
                            @if ($comando->aluno)
                                <span class="block truncate text-sm text-texto-mudo">{{ $comando->aluno->nome }}</span>
                            @endif
                        </span>

                        <span class="shrink-0 text-right">
                            @if ($comando->situacao === SituacaoComando::Falhou)
                                <x-ui.pilula estado="vencido">{{ $comando->situacao->rotulo() }}</x-ui.pilula>
                                {{-- O código do retorno é o diagnóstico, e vale ouro
                                     quando o aparelho está a duzentos quilômetros. --}}
                                <span class="numeros mt-1 block text-sm text-texto-mudo">código {{ $comando->retorno }}</span>
                            @else
                                <span class="text-sm text-texto-2">{{ $comando->situacao->rotulo() }}</span>
                            @endif
                        </span>
                    </div>
                @endforeach
            </x-ui.cartao>
        @endif
    @endif
</div>
