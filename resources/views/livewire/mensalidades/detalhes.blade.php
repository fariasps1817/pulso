@php
    use App\Enums\SituacaoMensalidade;
    use App\Support\Formato;

    $emAberto = $mensalidade->valorEmAberto();
    $pago = $mensalidade->valorPago();
    $vencida = $mensalidade->estaVencida();
@endphp

<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        :titulo="'Mensalidade de '.$mensalidade->competencia->translatedFormat('F/Y')"
        :subtitulo="$mensalidade->aluno->nome.' · '.$mensalidade->matricula->plano->nome"
        :voltar-para="['rotulo' => 'Mensalidades', 'url' => route('mensalidades.lista')]"
    >
        <x-ui.botao :href="route('alunos.detalhes', $mensalidade->aluno)" variante="secundario">
            Ver aluno
        </x-ui.botao>
    </x-ui.cabecalho-pagina>

    <div class="flex flex-wrap items-center gap-2">
        @if ($mensalidade->situacao === SituacaoMensalidade::Paga)
            <x-ui.pilula estado="pago" />
            <span class="text-sm text-texto-mudo">
                quitada em {{ $mensalidade->paga_em?->format('d/m/Y') }}
            </span>
        @elseif ($mensalidade->situacao === SituacaoMensalidade::Cancelada)
            <x-ui.pilula estado="frequencia">Cancelada</x-ui.pilula>
        @elseif ($vencida)
            <x-ui.pilula estado="vencido" />
            <span class="text-sm text-texto-mudo">
                venceu em {{ $mensalidade->vencimento->format('d/m/Y') }}
            </span>
        @else
            <x-ui.pilula estado="avencer" />
            <span class="text-sm text-texto-mudo">
                vence em {{ $mensalidade->vencimento->format('d/m/Y') }}
            </span>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.cartao>
            <p class="text-sm text-texto-mudo">Valor</p>
            <p class="numeros mt-1 font-titulo text-3xl text-texto">
                {{ Formato::dinheiro($mensalidade->valorDevido()) }}
            </p>
            @if ($mensalidade->desconto > 0)
                <p class="numeros mt-1 text-sm text-texto-mudo">
                    desconto de {{ Formato::dinheiro($mensalidade->desconto) }}
                </p>
            @endif
        </x-ui.cartao>

        <x-ui.cartao>
            <p class="text-sm text-texto-mudo">Já recebido</p>
            <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ Formato::dinheiro($pago) }}</p>
        </x-ui.cartao>

        <x-ui.cartao :destaque="bccomp($emAberto, '0', 2) === 1">
            <p class="text-sm text-texto-mudo">Falta pagar</p>
            <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ Formato::dinheiro($emAberto) }}</p>
        </x-ui.cartao>
    </div>

    {{-- ============ Receber ============ --}}
    @if ($podeReceber)
        <x-ui.cartao>
            <h2 class="text-lg text-texto">Registrar pagamento</h2>
            <p class="prosa mt-1 text-sm text-texto-2">
                A mensalidade aceita mais de um pagamento — metade em dinheiro e metade no Pix é rotina.
            </p>

            <x-ui.grade-formulario class="mt-6">
                <x-ui.campo-dinheiro largura="25" nome="valor" rotulo="Valor recebido" wire:model="valor" />

                <x-ui.selecao largura="25" nome="forma" rotulo="Forma" wire:model="forma"
                              :vazio="false" :opcoes="$formas" />

                <x-ui.campo-mascara largura="25" nome="recebido_em" rotulo="Recebido em" formato="data"
                                    wire:model.blur="recebido_em" />
            </x-ui.grade-formulario>

            <div class="mt-5">
                <x-ui.botao tipo="button" variante="primario" wire:click="registrar" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="registrar">Registrar pagamento</span>
                    <span wire:loading wire:target="registrar">Registrando…</span>
                </x-ui.botao>
            </div>
        </x-ui.cartao>
    @endif

    {{-- ============ Pagamentos ============ --}}
    <x-ui.cartao>
        <h2 class="text-lg text-texto">Pagamentos</h2>

        @if ($mensalidade->pagamentos->isEmpty())
            <p class="mt-4 text-texto-mudo">Nenhum pagamento registrado ainda.</p>
        @else
            <div class="mt-4">
                <x-ui.tabela :colunas="[
                    'Recebido em',
                    'Forma',
                    'Registrado por',
                    ['rotulo' => 'Valor', 'alinhamento' => 'direita'],
                    ['rotulo' => '', 'alinhamento' => 'direita'],
                ]">
                    @foreach ($mensalidade->pagamentos as $pagamento)
                        <tr wire:key="pagamento-{{ $pagamento->id }}"
                            @class(['opacity-60' => $pagamento->estaEstornado()])>
                            <td data-rotulo="Recebido em" data-principal class="numeros px-4 py-3 text-texto">
                                {{ $pagamento->recebido_em->format('d/m/Y') }}
                            </td>

                            <td data-rotulo="Forma" class="px-4 py-3 text-texto-2">
                                {{ $pagamento->forma->rotulo() }}
                            </td>

                            <td data-rotulo="Registrado por" class="px-4 py-3 text-texto-2">
                                {{-- Nulo = baixa automática por webhook do provedor. --}}
                                {{ $pagamento->registradoPor?->name ?? 'Automático' }}
                            </td>

                            <td data-rotulo="Valor" class="numeros px-4 py-3 text-right text-texto">
                                {{ Formato::dinheiro($pagamento->valor) }}
                            </td>

                            <td data-rotulo="" class="px-4 py-3 text-right">
                                @if ($pagamento->estaEstornado())
                                    <x-ui.pilula estado="vencido">Estornado</x-ui.pilula>
                                @elseif ($podeEstornar)
                                    <button type="button" wire:click="estornar({{ $pagamento->id }})"
                                            wire:confirm="Estornar este pagamento? A mensalidade volta a ficar em aberto."
                                            class="min-h-toque rounded-md px-3 text-sm text-vencido-texto
                                                   transition-colors hover:bg-vencido-fundo
                                                   focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                                        Estornar
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.tabela>
            </div>

            @if ($podeEstornar)
                <p class="mt-3 text-sm text-texto-mudo">
                    O estorno não apaga o pagamento: marca a data. Apagar dinheiro que entrou e depois
                    voltou destrói a conciliação com o extrato.
                </p>
            @endif
        @endif
    </x-ui.cartao>
</div>
