@php
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

    <x-ui.abas :abas="[
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

        {{-- Abas ainda sem tela própria: dizem o que virá, em vez de ficarem
             em branco como se estivessem quebradas. --}}
        <x-ui.painel-aba id="matricula">
            <x-ui.estado-vazio
                titulo="Matrículas entram na próxima etapa"
                descricao="Aqui vão aparecer o plano contratado, a unidade, a vigência e o dia de vencimento."
                icone="M5 3h10v14l-5-3-5 3V3Z"
            />
        </x-ui.painel-aba>

        <x-ui.painel-aba id="mensalidades">
            <x-ui.estado-vazio
                titulo="Mensalidades entram na etapa financeira"
                descricao="Histórico de cobranças, pagamentos e recibos do aluno."
                icone="M2.5 6.5h15v9h-15v-9Zm0 3.5h15M5.5 13h3"
            />
        </x-ui.painel-aba>

        <x-ui.painel-aba id="frequencia">
            <x-ui.estado-vazio
                titulo="Frequência entra com o controle de acesso"
                descricao="Passagens na catraca, com o mapa de horários mais movimentados."
                icone="M6.5 3h-3v3M13.5 3h3v3M6.5 17h-3v-3M13.5 17h3v-3M7.5 12.5s1 1 2.5 1 2.5-1 2.5-1"
            />
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
