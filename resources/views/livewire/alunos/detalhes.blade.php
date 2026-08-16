@php
    use App\Enums\SituacaoMensalidade;
    use App\Support\Formato;

    $matricula = $aluno->matriculaVigente;
@endphp

<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        :titulo="$aluno->nome"
        :subtitulo="$aluno->idade().' anos · CPF '.$aluno->cpfFormatado()"
        :voltar-para="['rotulo' => 'Alunos', 'url' => route('alunos.lista')]"
    >
        @can('update', $aluno)
            <x-ui.botao :href="route('alunos.editar', $aluno)" variante="primario">Editar</x-ui.botao>
        @endcan

        {{-- Ações menos usadas em menu, para não errar o toque no balcão. --}}
        @can('delete', $aluno)
            <x-ui.menu>
                <x-ui.menu-item
                    data-abrir-modal="excluir-aluno"
                    icone="M3.5 5.5h13M8 5.5V4h4v1.5M5.5 5.5l.8 11h7.4l.8-11"
                    :destrutivo="true"
                >
                    Excluir aluno
                </x-ui.menu-item>
            </x-ui.menu>
        @endcan
    </x-ui.cabecalho-pagina>

    {{-- Situação em pílula, logo abaixo do título. --}}
    <div class="flex flex-wrap items-center gap-2">
        @if ($matricula)
            <x-ui.pilula :estado="$matricula->situacao->pilula()">{{ $matricula->situacao->rotulo() }}</x-ui.pilula>
            <span class="text-sm text-texto-mudo">
                {{ $matricula->plano->nome }} · {{ $matricula->unidade->nome }}
                · desde {{ $matricula->inicio_em->format('d/m/Y') }}
            </span>
        @else
            <x-ui.pilula estado="frequencia">Sem matrícula</x-ui.pilula>
            <span class="text-sm text-texto-mudo">O aluno não passa na catraca até ser matriculado.</span>
        @endif
    </div>

    {{-- `?aba=` permite apontar um link direto para uma aba — da lista de
         vencidas para as mensalidades deste aluno, por exemplo. Valor
         desconhecido cai na primeira, em vez de mostrar tela vazia. --}}
    @php
        $abas = ['dados', 'matricula', 'mensalidades', 'frequencia'];
        $abaInicial = in_array(request('aba'), $abas, true) ? request('aba') : null;
    @endphp

    <x-ui.abas :inicial="$abaInicial" :abas="[
        ['id' => 'dados', 'rotulo' => 'Dados pessoais'],
        ['id' => 'matricula', 'rotulo' => 'Matrícula'],
        ['id' => 'mensalidades', 'rotulo' => 'Mensalidades'],
        ['id' => 'frequencia', 'rotulo' => 'Frequência'],
    ]">
        {{-- ==================== Dados ==================== --}}
        <x-ui.painel-aba id="dados">
            <div class="grid gap-6 lg:grid-cols-2">
                <x-ui.cartao>
                    <h3 class="text-lg text-texto">Contato</h3>
                    <dl class="mt-4 flex flex-col gap-3">
                        <div class="flex justify-between gap-4">
                            <dt class="text-texto-mudo">WhatsApp</dt>
                            <dd class="numeros text-texto">{{ Formato::telefone($aluno->whatsapp) }}</dd>
                        </div>
                        @if ($aluno->telefone)
                            <div class="flex justify-between gap-4">
                                <dt class="text-texto-mudo">Telefone</dt>
                                <dd class="numeros text-texto">{{ Formato::telefone($aluno->telefone) }}</dd>
                            </div>
                        @endif
                        @if ($aluno->email)
                            <div class="flex justify-between gap-4">
                                <dt class="text-texto-mudo">E-mail</dt>
                                <dd class="truncate text-texto">{{ $aluno->email }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-4">
                            <dt class="text-texto-mudo">Nascimento</dt>
                            <dd class="numeros text-texto">{{ $aluno->data_nascimento->format('d/m/Y') }}</dd>
                        </div>
                    </dl>
                </x-ui.cartao>

                <x-ui.cartao>
                    <h3 class="text-lg text-texto">Endereço</h3>
                    @if ($aluno->logradouro)
                        <p class="mt-4 text-texto-2">
                            {{ $aluno->logradouro }}{{ $aluno->numero ? ', '.$aluno->numero : '' }}
                            @if ($aluno->complemento) — {{ $aluno->complemento }} @endif
                            <br>
                            {{ $aluno->bairro }}
                            <br>
                            {{ $aluno->cidade }}@if ($aluno->uf) — {{ $aluno->uf }} @endif
                            @if ($aluno->cep)
                                <br><span class="numeros">CEP {{ substr($aluno->cep, 0, 5) }}-{{ substr($aluno->cep, 5) }}</span>
                            @endif
                        </p>
                    @else
                        <p class="mt-4 text-texto-mudo">Endereço não informado.</p>
                    @endif
                </x-ui.cartao>

                @if ($aluno->ehMenorDeIdade())
                    <x-ui.cartao :destaque="true" class="lg:col-span-2">
                        <h3 class="text-lg text-texto">Responsável</h3>
                        <p class="mt-1 text-sm text-texto-mudo">Aluno menor de 18 anos.</p>
                        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="flex justify-between gap-4">
                                <dt class="text-texto-mudo">Nome</dt>
                                <dd class="text-texto">{{ $aluno->responsavel_nome ?: '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-texto-mudo">Parentesco</dt>
                                <dd class="text-texto">{{ $aluno->responsavel_parentesco ?: '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-texto-mudo">CPF</dt>
                                <dd class="numeros text-texto">
                                    {{ $aluno->responsavel_cpf ? \App\Support\Documentos::formatarCpf($aluno->responsavel_cpf) : '—' }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-texto-mudo">Telefone</dt>
                                <dd class="numeros text-texto">
                                    {{ $aluno->responsavel_telefone ? Formato::telefone($aluno->responsavel_telefone) : '—' }}
                                </dd>
                            </div>
                        </dl>
                    </x-ui.cartao>
                @endif

                @if ($aluno->observacoes)
                    <x-ui.cartao class="lg:col-span-2">
                        <h3 class="text-lg text-texto">Observações</h3>
                        <p class="prosa mt-3 whitespace-pre-line text-texto-2">{{ $aluno->observacoes }}</p>
                    </x-ui.cartao>
                @endif
            </div>
        </x-ui.painel-aba>

        <x-ui.painel-aba id="matricula">
            @forelse ($matriculas as $vinculo)
                <x-ui.cartao wire:key="matricula-{{ $vinculo->id }}" class="mb-4"
                             :destaque="$vinculo->estaVigente()">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <a href="{{ route('matriculas.detalhes', $vinculo) }}"
                               class="rounded-sm text-lg text-acao hover:underline
                                      focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                                {{ $vinculo->plano->nome }}
                            </a>
                            <p class="text-sm text-texto-mudo">{{ $vinculo->unidade->nome }}</p>
                        </div>

                        <x-ui.pilula :estado="$vinculo->situacao->pilula()" class="shrink-0">
                            {{ $vinculo->situacao->rotulo() }}
                        </x-ui.pilula>
                    </div>

                    <dl class="mt-4 grid gap-4 sm:grid-cols-4">
                        <div>
                            <dt class="text-sm text-texto-mudo">Início</dt>
                            <dd class="numeros text-texto">{{ $vinculo->inicio_em->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-texto-mudo">Vencimento</dt>
                            <dd class="numeros text-texto">todo dia {{ $vinculo->dia_vencimento }}</dd>
                        </div>
                        @if ($mensalidades !== null)
                            <div>
                                <dt class="text-sm text-texto-mudo">Mensalidade</dt>
                                <dd class="numeros text-texto">{{ Formato::dinheiro($vinculo->valor_mensal) }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm text-texto-mudo">Encerrada em</dt>
                            <dd class="numeros text-texto">{{ $vinculo->encerrada_em?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                    </dl>

                    @if ($vinculo->motivo_encerramento)
                        <p class="mt-3 text-sm text-texto-mudo">Motivo: {{ $vinculo->motivo_encerramento }}</p>
                    @endif
                </x-ui.cartao>
            @empty
                <x-ui.estado-vazio
                    titulo="Este aluno ainda não tem matrícula"
                    descricao="Sem matrícula não há cobrança nem liberação na catraca."
                    icone="M5 3h10v14l-5-3-5 3V3Z"
                />
            @endforelse
        </x-ui.painel-aba>

        <x-ui.painel-aba id="mensalidades">
            @if ($mensalidades === null)
                {{-- Professor não vê dinheiro. Dizer isso é melhor do que sumir
                     com a aba: o que some sem explicação parece defeito. --}}
                <x-ui.estado-vazio
                    titulo="Valores não fazem parte do seu acesso"
                    descricao="A situação financeira do aluno fica com a recepção, a gerência e a direção."
                    icone="M6 9V6.5a4 4 0 0 1 8 0V9M4.5 9h11v8.5h-11V9Z"
                />
            @elseif ($mensalidades->isEmpty())
                <x-ui.estado-vazio
                    titulo="Nenhuma mensalidade ainda"
                    descricao="Elas nascem da matrícula ativa, geradas todo mês pela rotina automática."
                    icone="M2.5 6.5h15v9h-15v-9Zm0 3.5h15M5.5 13h3"
                />
            @else
                @if (bccomp($emAberto, '0', 2) === 1)
                    <x-ui.cartao class="mb-4">
                        <p class="text-sm text-texto-mudo">Em aberto</p>
                        <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ Formato::dinheiro($emAberto) }}</p>
                    </x-ui.cartao>
                @endif

                <x-ui.tabela :colunas="[
                    'Competência',
                    ['rotulo' => 'Vencimento', 'alinhamento' => 'direita'],
                    ['rotulo' => 'Valor', 'alinhamento' => 'direita'],
                    ['rotulo' => 'Situação', 'alinhamento' => 'direita'],
                ]">
                    @foreach ($mensalidades as $mensalidade)
                        <tr wire:key="mensalidade-{{ $mensalidade->id }}" class="transition-colors hover:bg-superficie-2">
                            <td data-rotulo="Competência" data-principal class="px-4 py-3">
                                <a href="{{ route('mensalidades.detalhes', $mensalidade) }}"
                                   class="rounded-sm text-acao hover:underline
                                          focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                                    {{ $mensalidade->competencia->translatedFormat('F/Y') }}
                                </a>
                            </td>
                            <td data-rotulo="Vencimento" class="numeros px-4 py-3 text-right text-texto-2">
                                {{ $mensalidade->vencimento->format('d/m/Y') }}
                            </td>
                            <td data-rotulo="Valor" class="numeros px-4 py-3 text-right text-texto">
                                {{ Formato::dinheiro($mensalidade->valorDevido()) }}
                            </td>
                            <td data-rotulo="Situação" class="px-4 py-3 text-right">
                                @if ($mensalidade->situacao === SituacaoMensalidade::Paga)
                                    <x-ui.pilula estado="pago" />
                                @elseif ($mensalidade->situacao === SituacaoMensalidade::Cancelada)
                                    <x-ui.pilula estado="frequencia">Cancelada</x-ui.pilula>
                                @elseif ($mensalidade->estaVencida())
                                    <x-ui.pilula estado="vencido" />
                                @else
                                    <x-ui.pilula estado="avencer" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.tabela>
            @endif
        </x-ui.painel-aba>

        <x-ui.painel-aba id="frequencia">
            @if (! $catracaEmUso)
                {{-- Mesmo cuidado do Radar: dizer "nunca treinou" de um aluno
                     assíduo, só porque o equipamento não foi integrado, é pior
                     do que não dizer nada. --}}
                <x-ui.estado-vazio
                    titulo="A catraca ainda não registra acessos"
                    descricao="Assim que o equipamento estiver integrado, as passagens deste aluno aparecem aqui."
                    icone="M6.5 3h-3v3M13.5 3h3v3M6.5 17h-3v-3M13.5 17h3v-3"
                />
            @elseif ($frequencia->isEmpty())
                <x-ui.estado-vazio
                    titulo="Nenhuma passagem registrada"
                    descricao="Este aluno ainda não passou na catraca desta unidade."
                    icone="M6.5 3h-3v3M13.5 3h3v3M6.5 17h-3v-3M13.5 17h3v-3"
                />
            @else
                <x-ui.cartao class="mb-4">
                    <p class="text-sm text-texto-mudo">Treinos nos últimos 30 dias</p>
                    <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ $treinosNoMes }}</p>
                </x-ui.cartao>

                <x-ui.tabela :colunas="[
                    'Dia',
                    ['rotulo' => 'Entrada', 'alinhamento' => 'direita'],
                    ['rotulo' => 'Permanência', 'alinhamento' => 'direita'],
                    ['rotulo' => 'Reconhecido por', 'alinhamento' => 'direita'],
                ]">
                    @foreach ($frequencia as $passagem)
                        @php $minutos = $passagem->permanenciaEmMinutos(); @endphp

                        <tr wire:key="passagem-{{ $passagem->id }}">
                            <td data-rotulo="Dia" data-principal class="px-4 py-3 text-texto">
                                {{ $passagem->ocorreu_em->translatedFormat('D, d/m/Y') }}
                            </td>
                            <td data-rotulo="Entrada" class="numeros px-4 py-3 text-right text-texto-2">
                                {{ $passagem->ocorreu_em->format('H:i') }}
                            </td>
                            <td data-rotulo="Permanência" class="numeros px-4 py-3 text-right text-texto-2">
                                @if ($minutos !== null)
                                    {{ intdiv($minutos, 60) }}h{{ str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT) }}
                                @elseif ($passagem->estaDentro())
                                    na academia
                                @else
                                    {{-- Saída presumida: ninguém mediu a hora. --}}
                                    não registrada
                                @endif
                            </td>
                            <td data-rotulo="Reconhecido por" class="px-4 py-3 text-right text-texto-2">
                                {{ $passagem->tipo_credencial ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </x-ui.tabela>
            @endif
        </x-ui.painel-aba>

    </x-ui.abas>

    {{-- Exclusão diz o que será perdido, nunca só "Tem certeza?". --}}
    @can('delete', $aluno)
        <x-ui.modal
            nome="excluir-aluno"
            titulo="Excluir {{ $aluno->nome }}?"
            descricao="O aluno sai das listas e não passa mais na catraca. O histórico financeiro é preservado, e o template biométrico é apagado de vez."
        >
            <x-slot:acoes>
                <x-ui.botao tipo="button" variante="secundario" data-fechar-modal>Cancelar</x-ui.botao>
                <x-ui.botao
                    tipo="button"
                    variante="primario"
                    class="bg-vencido-forte hover:bg-vencido-texto"
                    wire:click="excluir"
                >
                    Excluir aluno
                </x-ui.botao>
            </x-slot:acoes>
        </x-ui.modal>
    @endcan
</div>
