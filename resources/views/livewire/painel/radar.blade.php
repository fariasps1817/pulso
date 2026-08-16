@php
    use App\Support\Formato;
    use App\Support\Nomes;
@endphp

{{--
    O Radar não é um painel de gráficos: é a lista do que fazer hoje.

    Cada cartão leva à tela onde o problema se resolve. Número que não vira
    ação não entra aqui.
--}}
<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        titulo="Olá, {{ Nomes::curto(auth()->user()->name) }}."
        subtitulo="{{ now()->translatedFormat('l, j \d\e F') }} — o pulso da sua academia hoje."
    />

    <div @class([
        'grid gap-4',
        'sm:grid-cols-2 lg:grid-cols-4' => $recebido !== null,
        'sm:grid-cols-3' => $recebido === null,
    ])>
        <a href="{{ route('mensalidades.lista', ['filtro' => 'vencidas']) }}"
           class="rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
            <x-ui.cartao class="h-full transition-colors hover:bg-superficie-2">
                <p class="text-sm text-texto-mudo">Vencidas</p>
                <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ Formato::dinheiro($vencidas['total']) }}</p>
                @if ($vencidas['alunos'] > 0)
                    <x-ui.pilula estado="vencido" class="mt-3">
                        {{ $vencidas['alunos'] }} {{ $vencidas['alunos'] === 1 ? 'aluno' : 'alunos' }}
                    </x-ui.pilula>
                @else
                    <x-ui.pilula estado="pago" class="mt-3">Ninguém devendo</x-ui.pilula>
                @endif
            </x-ui.cartao>
        </a>

        <a href="{{ route('mensalidades.lista', ['filtro' => 'vence_hoje']) }}"
           class="rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
            <x-ui.cartao class="h-full transition-colors hover:bg-superficie-2">
                <p class="text-sm text-texto-mudo">Vencem hoje</p>
                <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ Formato::dinheiro($vencemHoje['total']) }}</p>
                @if ($vencemHoje['alunos'] > 0)
                    <x-ui.pilula estado="avencer" class="mt-3">
                        {{ $vencemHoje['alunos'] }} {{ $vencemHoje['alunos'] === 1 ? 'aluno' : 'alunos' }}
                    </x-ui.pilula>
                @endif
            </x-ui.cartao>
        </a>

        <x-ui.cartao class="h-full">
            <p class="text-sm text-texto-mudo">Sumiram há {{ $dias }} dias</p>
            @if ($catracaEmUso)
                <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ $totalDeSumidos }}</p>
                @if ($totalDeSumidos > 0)
                    <x-ui.pilula estado="frequencia" class="mt-3">risco de evasão</x-ui.pilula>
                @else
                    <x-ui.pilula estado="pago" class="mt-3">Todo mundo treinando</x-ui.pilula>
                @endif
            @else
                {{--
                    Sem catraca integrada, TODO aluno apareceria como sumido — um
                    número que assusta e não significa nada. Melhor dizer a verdade.
                --}}
                <p class="mt-1 font-titulo text-3xl text-texto-mudo">—</p>
                <p class="mt-3 text-sm text-texto-mudo">A catraca ainda não registra acessos.</p>
            @endif
        </x-ui.cartao>

        @if ($recebido !== null)
            <x-ui.cartao class="h-full">
                <p class="text-sm text-texto-mudo">Recebido em {{ now()->translatedFormat('F') }}</p>
                <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ Formato::dinheiro($recebido) }}</p>
                @if ((float) $recebido > 0)
                    <x-ui.pilula estado="pago" class="mt-3">já entrou no caixa</x-ui.pilula>
                @else
                    <p class="mt-3 text-sm text-texto-mudo">Nenhum pagamento registrado no mês.</p>
                @endif
            </x-ui.cartao>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- A fila de cobrança, na ordem em que a recepção liga. --}}
        <x-ui.cartao class="flex flex-col gap-3">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-titulo text-lg text-texto">A cobrar</h2>
                @if ($listaDeVencidas->isNotEmpty())
                    <a href="{{ route('mensalidades.lista', ['filtro' => 'vencidas']) }}"
                       class="rounded-sm text-sm text-acao hover:underline
                              focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                        Ver todas →
                    </a>
                @endif
            </div>

            @forelse ($listaDeVencidas as $mensalidade)
                <a href="{{ route('mensalidades.detalhes', $mensalidade) }}"
                   wire:key="vencida-{{ $mensalidade->id }}"
                   class="-mx-2 flex min-h-toque items-center justify-between gap-3 rounded-md px-2 py-2
                          transition-colors hover:bg-superficie-2
                          focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                    <span class="min-w-0">
                        <span class="block truncate text-texto">{{ $mensalidade->aluno->nome }}</span>
                        @php
                            // diffInDays devolve fração; a recepção quer "5 dias", não "5,10773".
                            $atraso = (int) $mensalidade->vencimento->diffInDays(today());
                        @endphp
                        <span class="numeros block text-sm text-texto-mudo">
                            venceu em {{ $mensalidade->vencimento->format('d/m') }}
                            · {{ $atraso }} {{ $atraso === 1 ? 'dia' : 'dias' }}
                        </span>
                    </span>
                    <span class="numeros shrink-0 text-texto">{{ Formato::dinheiro($mensalidade->valorDevido()) }}</span>
                </a>
            @empty
                <p class="py-6 text-center text-sm text-texto-mudo">Ninguém em atraso. Bom sinal.</p>
            @endforelse
        </x-ui.cartao>

        <div class="flex flex-col gap-4">
            {{-- Quem sumiu. É aqui que a evasão se evita: antes de ela acontecer. --}}
            <x-ui.cartao class="flex flex-col gap-3">
                <h2 class="font-titulo text-lg text-texto">Sumiram</h2>

                @if (! $catracaEmUso)
                    <p class="py-4 text-sm text-texto-mudo">
                        Esta lista precisa dos registros da catraca. Assim que o equipamento
                        estiver integrado, quem parar de treinar aparece aqui.
                    </p>
                @else
                    @forelse ($sumidos as $aluno)
                        <a href="{{ route('alunos.detalhes', $aluno) }}"
                           wire:key="sumido-{{ $aluno->id }}"
                           class="-mx-2 flex min-h-toque items-center justify-between gap-3 rounded-md px-2 py-2
                                  transition-colors hover:bg-superficie-2
                                  focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                            <span class="min-w-0">
                                <span class="block truncate text-texto">{{ $aluno->nome }}</span>
                                @if ($aluno->deve)
                                    {{-- Sumiu E deve: é quem cancela no mês seguinte. --}}
                                    <span class="block text-sm text-vencido-texto">e está com mensalidade vencida</span>
                                @endif
                            </span>
                            <span class="numeros shrink-0 text-sm text-texto-mudo">
                                @if ($aluno->ultimo_acesso_em)
                                    há {{ (int) \Carbon\CarbonImmutable::parse($aluno->ultimo_acesso_em)->diffInDays(now()) }} dias
                                @else
                                    nunca veio
                                @endif
                            </span>
                        </a>
                    @empty
                        <p class="py-4 text-center text-sm text-texto-mudo">Todo mundo apareceu nos últimos {{ $dias }} dias.</p>
                    @endforelse
                @endif
            </x-ui.cartao>

            {{-- O motivo de a data de nascimento ser obrigatória no cadastro. --}}
            <x-ui.cartao class="flex flex-col gap-3">
                <h2 class="font-titulo text-lg text-texto">Aniversariantes de hoje</h2>

                @forelse ($aniversariantes as $aluno)
                    <a href="{{ route('alunos.detalhes', $aluno) }}"
                       wire:key="aniversariante-{{ $aluno->id }}"
                       class="-mx-2 flex min-h-toque items-center justify-between gap-3 rounded-md px-2 py-2
                              transition-colors hover:bg-superficie-2
                              focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                        <span class="min-w-0 truncate text-texto">{{ $aluno->nome }}</span>
                        <span class="numeros shrink-0 text-sm text-texto-mudo">{{ $aluno->idade() }} anos</span>
                    </a>
                @empty
                    <p class="py-4 text-center text-sm text-texto-mudo">Ninguém faz aniversário hoje.</p>
                @endforelse
            </x-ui.cartao>
        </div>
    </div>
</div>
