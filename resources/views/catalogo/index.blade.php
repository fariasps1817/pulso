@php
    /*
     * Catálogo do design system. Só existe fora de produção — é ferramenta de
     * construção, não parte do produto.
     *
     * Serve para revisar todos os componentes de uma vez, nos dois temas e
     * nos dois tamanhos de tela, antes de montar as telas de verdade.
     */

    $secoes = [
        'cores' => 'Cores e temas',
        'tipografia' => 'Tipografia',
        'botoes' => 'Botões',
        'campos' => 'Campos de formulário',
        'estados' => 'Estados',
        'listas' => 'Listas e tabelas',
        'navegacao' => 'Navegação e sobreposição',
        'espera' => 'Espera e vazio',
    ];

    $alunosExemplo = [
        ['nome' => 'Ana Beatriz Nogueira', 'plano' => 'Mensal · musculação', 'valor' => 129.90, 'estado' => 'pago'],
        ['nome' => 'Carlos Eduardo Lima', 'plano' => 'Trimestral · completo', 'valor' => 289.00, 'estado' => 'avencer'],
        ['nome' => 'Jonas Ferreira Alves', 'plano' => 'Mensal · musculação', 'valor' => 129.90, 'estado' => 'vencido'],
        ['nome' => 'Marina Sousa Vieira', 'plano' => 'Anual · completo', 'valor' => 99.00, 'estado' => 'frequencia'],
    ];
@endphp

<x-layout.base titulo="Catálogo do design system" :com-livewire="true">
    <div class="mx-auto max-w-5xl px-5 py-10">

        <header class="flex flex-col gap-4 border-b border-borda pb-8 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <x-marca.logo class="h-9 w-auto" />
                <h1 class="mt-5 text-3xl text-texto">Catálogo do design system</h1>
                <p class="prosa mt-3 text-texto-2">
                    Todos os componentes do Pulso numa página. Alterne o tema no botão ao lado e
                    reduza a janela para ver o comportamento no celular — a tabela vira lista de cartões
                    abaixo de 768&nbsp;px.
                </p>
            </div>

            <x-ui.alternador-tema />
        </header>

        <nav class="flex flex-wrap gap-2 py-6" aria-label="Seções do catálogo">
            @foreach ($secoes as $id => $rotulo)
                <a href="#{{ $id }}"
                   class="rounded-pill border border-borda px-3 py-1.5 text-sm text-texto-2 transition-colors
                          hover:bg-superficie-2 hover:text-texto
                          focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                    {{ $rotulo }}
                </a>
            @endforeach
        </nav>

        {{-- ============================ Cores ============================ --}}
        <section id="cores" class="scroll-mt-6 border-t border-borda py-12">
            <h2 class="text-2xl text-texto">Cores e temas</h2>
            <p class="prosa mt-2 text-texto-2">
                Componente nenhum escreve cor literal — tudo vem de token. Por isso o projeto
                dispensa a variante <code class="rounded-sm bg-superficie-2 px-1">dark:</code> do
                Tailwind: trocar o tema troca os tokens, e as classes seguem sozinhas.
            </p>

            <h3 class="mt-8 text-lg text-texto">Rampas da marca</h3>
            {{--
                As amostras usam `style="background: var(--token)"` em vez de classe do
                Tailwind. Não é preguiça: classe montada em tempo de execução
                (bg-{{ '{{' }} $chave {{ '}}' }}-...) nunca é vista pelo scanner do Tailwind, e a cor
                sairia em branco. Aqui, além de funcionar, mostra o token de verdade.
            --}}
            @foreach ([['Azul Maré', 'mare', [50,100,200,300,400,500,600,700,800,900,950]], ['Amarelo Sol', 'sol', [50,100,200,300,400,500,600,700,800,900]], ['Areia Fria', 'areia', [0,50,100,200,300,400,500,600,700,800,900,950]]] as [$nomeRampa, $chave, $tons])
                <div class="mt-4">
                    <p class="text-sm font-medium text-texto-2">{{ $nomeRampa }}</p>
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach ($tons as $tom)
                            <div class="flex flex-col items-center gap-1">
                                <div class="size-12 rounded-sm border border-borda"
                                     style="background: var(--{{ $chave }}-{{ $tom }})"
                                     title="--{{ $chave }}-{{ $tom }}"></div>
                                <span class="numeros text-xs text-texto-mudo">{{ $tom }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <h3 class="mt-10 text-lg text-texto">Cores semânticas — mudam com o tema</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['Fundo', 'bg-fundo'], ['Superfície', 'bg-superficie'], ['Superfície 2', 'bg-superficie-2'],
                    ['Ação', 'bg-acao'], ['Ação sutil', 'bg-acao-sutil'], ['Borda forte', 'bg-borda-forte'],
                ] as [$rotulo, $classe])
                    <div class="flex items-center gap-3 rounded-md border border-borda p-3">
                        <div class="size-10 shrink-0 rounded-sm border border-borda {{ $classe }}"></div>
                        <div>
                            <p class="text-texto">{{ $rotulo }}</p>
                            <code class="text-sm text-texto-mudo">{{ $classe }}</code>
                        </div>
                    </div>
                @endforeach
            </div>

            <h3 class="mt-10 text-lg text-texto">Série de gráficos — ordem fixa, nunca reciclada</h3>
            <div class="mt-4 flex flex-wrap gap-1">
                @foreach ([1,2,3,4,5,6] as $n)
                    <div class="flex flex-col items-center gap-1">
                        <div class="size-14 rounded-sm border border-borda"
                             style="background: var(--graf-{{ $n }})" title="--graf-{{ $n }}"></div>
                        <span class="numeros text-xs text-texto-mudo">{{ $n }}</span>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-sm text-texto-mudo">
                As cores de estado (verde, âmbar, vermelho, roxo) são reservadas e não entram como série de gráfico.
            </p>
        </section>

        {{-- ========================= Tipografia ========================= --}}
        <section id="tipografia" class="scroll-mt-6 border-t border-borda py-12">
            <h2 class="text-2xl text-texto">Tipografia</h2>
            <p class="prosa mt-2 text-texto-2">Sora nos títulos, Inter no texto. Ambas auto-hospedadas.</p>

            <div class="mt-6 flex flex-col gap-3">
                <p class="font-titulo text-4xl text-texto">O pulso da sua academia.</p>
                <p class="font-titulo text-3xl text-texto">Título de seção</p>
                <p class="font-titulo text-2xl text-texto">Título de página</p>
                <p class="text-lg text-texto">Texto grande, usado em chamadas</p>
                <p class="prosa text-base text-texto-2">
                    Texto corrido, no máximo em 65 caracteres por linha. Quem usa o sistema é
                    recepcionista, gerente e dono — não analista de TI.
                </p>
                <p class="text-sm text-texto-mudo">Texto de apoio e legenda</p>
            </div>

            <h3 class="mt-10 text-lg text-texto">Números com <code>tabular-nums</code></h3>
            <div class="mt-3 grid max-w-xs gap-1">
                @foreach ([4820.00, 129.90, 1350.00, 99.00, 11111.11] as $valor)
                    <div class="flex justify-between border-b border-borda py-1">
                        <span class="text-texto-mudo">Mensalidade</span>
                        <span class="numeros text-texto">{{ \App\Support\Formato::dinheiro($valor) }}</span>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-sm text-texto-mudo">Sem isso os algarismos têm larguras diferentes e a coluna desalinha.</p>
        </section>

        {{-- =========================== Botões =========================== --}}
        <section id="botoes" class="scroll-mt-6 border-t border-borda py-12">
            <h2 class="text-2xl text-texto">Botões</h2>
            <p class="prosa mt-2 text-texto-2">
                Altura mínima de 44&nbsp;px sempre. O texto diz o que acontece — "Registrar pagamento",
                nunca "OK".
            </p>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-ui.botao variante="primario">Registrar pagamento</x-ui.botao>
                <x-ui.botao variante="secundario">Cancelar</x-ui.botao>
                <x-ui.botao variante="fantasma">Ver detalhes</x-ui.botao>
                <x-ui.botao variante="sol">Pedir demonstração</x-ui.botao>
                <x-ui.botao variante="primario" disabled>Desabilitado</x-ui.botao>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-ui.botao variante="primario" tamanho="grande">Tamanho grande</x-ui.botao>
                <x-ui.botao variante="secundario" tamanho="grande">Grande secundário</x-ui.botao>
            </div>
        </section>

        {{-- =========================== Campos =========================== --}}
        <section id="campos" class="scroll-mt-6 border-t border-borda py-12">
            <h2 class="text-2xl text-texto">Campos de formulário</h2>
            <p class="prosa mt-2 text-texto-2">
                Todo campo com formato conhecido traz máscara, limite rígido de caracteres e teclado
                numérico. Tecla que não é dígito não entra — nem digitada, nem colada.
                <strong class="text-texto">Teste no celular:</strong> o teclado que sobe é o numérico.
            </p>

            <p class="prosa mt-4 text-texto-2">
                Os campos vivem numa <strong class="text-texto">grade de quatro colunas</strong>, e cada um
                declara se ocupa 25, 50, 75 ou 100 por cento. A largura acompanha o conteúdo esperado —
                "Sexo" não merece o mesmo espaço de um nome completo.
            </p>

            <div class="mt-8 grid gap-6 md:grid-cols-2">
                <x-ui.campo nome="nome_exemplo" rotulo="Nome completo" placeholder="Jose Maria da Silva"
                            ajuda="Gravado em caixa de título, digite como digitar." />

                <x-ui.campo-mascara nome="cpf_exemplo" rotulo="CPF" formato="cpf"
                                    ajuda="Dígitos verificadores conferidos ao sair do campo." />

                <x-ui.campo-mascara nome="nascimento_exemplo" rotulo="Data de nascimento" formato="data"
                                    ajuda="Digitada, sem calendário — no celular o teclado numérico é mais rápido." />

                <x-ui.campo-mascara nome="whatsapp_exemplo" rotulo="WhatsApp" formato="celular" />

                <x-ui.campo-mascara nome="cep_exemplo" rotulo="CEP" formato="cep"
                                    ajuda="Preenche o endereço pelo ViaCEP, mas não trava se não achar." />

                <x-ui.campo-mascara nome="cnpj_exemplo" rotulo="CNPJ" formato="cnpj" :obrigatorio="false" />

                <x-ui.campo-dinheiro nome="valor_exemplo" rotulo="Valor da mensalidade"
                                     ajuda="Digite da direita para a esquerda: 12990 vira 129,90." />

                <x-ui.selecao nome="plano_exemplo" rotulo="Plano" :opcoes="[
                    1 => 'Mensal · musculação — R$ 129,90',
                    2 => 'Trimestral · completo — R$ 289,00',
                    3 => 'Anual · completo — R$ 99,00/mês',
                ]" />

                <x-ui.campo nome="email_exemplo" rotulo="E-mail" tipo="email" :obrigatorio="false"
                            placeholder="voce@academia.com.br" />

                <x-ui.envio-imagem nome="foto_exemplo" rotulo="Foto do aluno"
                                   ajuda="Identificação da recepção. Não é template biométrico." />
            </div>

            <div class="mt-6">
                <x-ui.area-texto nome="observacoes_exemplo" rotulo="Observações"
                                 placeholder="Restrição médica, preferência de horário…" />
            </div>

            <div class="mt-8 flex max-w-md flex-col gap-5 rounded-lg border border-borda bg-superficie p-5">
                <x-ui.caixa-selecao nome="aceite_exemplo" rotulo="Aceito ceder minha biometria para controle de acesso" />

                <x-ui.interruptor nome="ativo_exemplo" rotulo="Aluno ativo" :ligado="true"
                                  descricao="Inativo não passa na catraca e não gera mensalidade." />

                <x-ui.interruptor nome="sessao_exemplo" rotulo="Sessão única"
                                  descricao="Entrar em outro aparelho derruba a sessão atual." />
            </div>

            <h3 class="mt-10 text-lg text-texto">Campo com erro</h3>
            <p class="prosa mt-2 text-texto-2">Nunca só a borda vermelha: ícone e texto, ligados ao campo por <code>aria-describedby</code>.</p>
            <div class="mt-4 max-w-md">
                <div class="flex flex-col gap-1.5">
                    <label for="cpf_erro" class="text-sm font-medium text-texto-2">CPF</label>
                    <input type="text" id="cpf_erro" value="111.111.111-11" inputmode="numeric" maxlength="14"
                           x-mask="999.999.999-99" data-somente-digitos aria-invalid="true" aria-describedby="cpf_erro_msg"
                           class="numeros min-h-toque w-full rounded-md border border-vencido-forte bg-superficie px-3.5
                                  text-base text-texto focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                    <p id="cpf_erro_msg" class="flex items-start gap-1.5 text-sm text-vencido-texto">
                        <svg viewBox="0 0 20 20" class="mt-0.5 size-4 shrink-0" fill="currentColor" aria-hidden="true">
                            <path d="M10 1.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17ZM9 5.5h2v6H9v-6Zm0 7.5h2v2H9v-2Z" />
                        </svg>
                        <span>Confira o CPF digitado.</span>
                    </p>
                </div>
            </div>
        </section>

        {{-- =========================== Estados ========================== --}}
        <section id="estados" class="scroll-mt-6 border-t border-borda py-12">
            <h2 class="text-2xl text-texto">Estados</h2>
            <p class="prosa mt-2 text-texto-2">
                Sempre ícone + texto + cor. Pontinho colorido sozinho está proibido: exclui quem não
                distingue as cores e some na impressão em preto e branco.
            </p>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-ui.pilula estado="pago" />
                <x-ui.pilula estado="avencer" />
                <x-ui.pilula estado="vencido" />
                <x-ui.pilula estado="frequencia" />
                <x-ui.pilula estado="vencido">12 alunos</x-ui.pilula>
            </div>

            <h3 class="mt-10 text-lg text-texto">Avisos</h3>
            <div class="mt-4 flex flex-col gap-3">
                <x-ui.aviso tipo="informativo" titulo="Período de experiência">
                    O aluno tem 7 dias ou 3 sessões, o que terminar primeiro.
                </x-ui.aviso>
                <x-ui.aviso tipo="atencao" titulo="Assinatura vence em 5 dias" dispensavel>
                    Regularize para não perder o acesso ao sistema.
                </x-ui.aviso>
                <x-ui.aviso tipo="erro" titulo="Não foi possível ler a digital">
                    Tente de novo ou use o cartão.
                </x-ui.aviso>
                <x-ui.aviso tipo="sucesso">Pagamento registrado.</x-ui.aviso>
            </div>

            <h3 class="mt-10 text-lg text-texto">Aviso rápido</h3>
            <div class="mt-4 flex flex-wrap gap-3">
                <x-ui.botao variante="secundario" onclick="window.pulso.avisar('Pagamento registrado')">Sucesso</x-ui.botao>
                <x-ui.botao variante="secundario" onclick="window.pulso.avisar('Não foi possível salvar. Tente de novo.', 'erro')">Erro</x-ui.botao>
                <x-ui.botao variante="secundario" onclick="window.pulso.avisar('Sincronizando com a catraca…', 'informativo')">Informativo</x-ui.botao>
            </div>
            <p class="mt-3 text-sm text-texto-mudo">Erro não some sozinho — quem precisa ler uma falha costuma estar olhando outra coisa.</p>
        </section>

        {{-- ====================== Listas e tabelas ====================== --}}
        <section id="listas" class="scroll-mt-6 border-t border-borda py-12">
            <h2 class="text-2xl text-texto">Listas e tabelas</h2>
            <p class="prosa mt-2 text-texto-2">
                <strong class="text-texto">Reduza a janela abaixo de 768&nbsp;px:</strong> o cabeçalho some,
                cada linha vira um cartão e o rótulo da coluna aparece à esquerda de cada valor.
            </p>

            <div class="mt-6">
                <x-ui.busca placeholder="Buscar aluno por nome ou CPF…" class="max-w-md" />
            </div>

            <div class="mt-4">
                <x-ui.tabela :colunas="['Aluno', 'Plano', ['rotulo' => 'Valor', 'alinhamento' => 'direita'], ['rotulo' => 'Situação', 'alinhamento' => 'direita']]">
                    @foreach ($alunosExemplo as $linha)
                        <tr class="transition-colors hover:bg-superficie-2">
                            <td data-rotulo="Aluno" data-principal class="px-4 py-3">
                                <a href="#" class="text-acao hover:underline
                                          focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                                    {{ $linha['nome'] }}
                                </a>
                            </td>
                            <td data-rotulo="Plano" class="px-4 py-3 text-texto-2">{{ $linha['plano'] }}</td>
                            <td data-rotulo="Valor" class="numeros px-4 py-3 text-right text-texto">
                                {{ \App\Support\Formato::dinheiro($linha['valor']) }}
                            </td>
                            <td data-rotulo="Situação" class="px-4 py-3 text-right">
                                <x-ui.pilula :estado="$linha['estado']" />
                            </td>
                        </tr>
                    @endforeach
                </x-ui.tabela>
            </div>

            <div class="mt-4">
                <x-ui.paginacao rotulo="alunos" :paginador="new Illuminate\Pagination\LengthAwarePaginator(
                    $alunosExemplo, 312, 25, 3, ['path' => url()->current()]
                )" />
            </div>

            <h3 class="mt-10 text-lg text-texto">Cartões</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <x-ui.cartao>
                    <p class="text-sm text-texto-mudo">Vencidas</p>
                    <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ \App\Support\Formato::dinheiro(4820) }}</p>
                    <x-ui.pilula estado="vencido" class="mt-3">12 alunos</x-ui.pilula>
                </x-ui.cartao>
                <x-ui.cartao>
                    <p class="text-sm text-texto-mudo">Vencem hoje</p>
                    <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ \App\Support\Formato::dinheiro(1350) }}</p>
                    <x-ui.pilula estado="avencer" class="mt-3">7 alunos</x-ui.pilula>
                </x-ui.cartao>
                <x-ui.cartao :destaque="true">
                    <p class="text-sm text-texto-mudo">Sumiram há 15 dias</p>
                    <p class="numeros mt-1 font-titulo text-3xl text-texto">23</p>
                    <x-ui.pilula estado="frequencia" class="mt-3">risco de evasão</x-ui.pilula>
                </x-ui.cartao>
            </div>
        </section>

        {{-- ==================== Navegação e sobreposição ==================== --}}
        <section id="navegacao" class="scroll-mt-6 border-t border-borda py-12">
            <h2 class="text-2xl text-texto">Navegação e sobreposição</h2>

            <h3 class="mt-6 text-lg text-texto">Cabeçalho de página</h3>
            <div class="mt-4 rounded-lg border border-borda bg-superficie p-5">
                <x-ui.cabecalho-pagina
                    titulo="Jose Maria da Silva"
                    subtitulo="Matrícula ativa desde 12/03/2026 · Unidade Centro"
                    :voltar-para="['rotulo' => 'Alunos', 'url' => '#']"
                >
                    <x-ui.botao variante="primario">Editar</x-ui.botao>
                    <x-ui.menu>
                        <x-ui.menu-item icone="M5 3h10v14l-5-3-5 3V3Z">Suspender matrícula</x-ui.menu-item>
                        <x-ui.menu-item icone="M3.5 5.5h13M8 5.5V4h4v1.5M5.5 5.5l.8 11h7.4l.8-11" :destrutivo="true">
                            Excluir aluno
                        </x-ui.menu-item>
                    </x-ui.menu>
                </x-ui.cabecalho-pagina>
            </div>

            <h3 class="mt-10 text-lg text-texto">Abas</h3>
            <div class="mt-4 rounded-lg border border-borda bg-superficie p-5">
                <x-ui.abas :abas="[
                    ['id' => 'dados', 'rotulo' => 'Dados pessoais'],
                    ['id' => 'matricula', 'rotulo' => 'Matrícula'],
                    ['id' => 'mensalidades', 'rotulo' => 'Mensalidades'],
                    ['id' => 'frequencia', 'rotulo' => 'Frequência'],
                ]">
                    <x-ui.painel-aba id="dados"><p class="text-texto-2">CPF, nascimento, contato e endereço do aluno.</p></x-ui.painel-aba>
                    <x-ui.painel-aba id="matricula"><p class="text-texto-2">Plano contratado, unidade, vigência e dia de vencimento.</p></x-ui.painel-aba>
                    <x-ui.painel-aba id="mensalidades"><p class="text-texto-2">Histórico de cobranças e pagamentos.</p></x-ui.painel-aba>
                    <x-ui.painel-aba id="frequencia"><p class="text-texto-2">Passagens na catraca nos últimos meses.</p></x-ui.painel-aba>
                </x-ui.abas>
            </div>

            <h3 class="mt-10 text-lg text-texto">Confirmação</h3>
            <p class="prosa mt-2 text-texto-2">
                Usa o <code>&lt;dialog&gt;</code> nativo: Esc fecha, o foco fica preso dentro e o fundo
                vira inerte. Ação irreversível diz o que será perdido, nunca só "Tem certeza?".
            </p>
            <div class="mt-4">
                <x-ui.botao variante="secundario" data-abrir-modal="excluir-exemplo">Excluir aluno</x-ui.botao>

                <x-ui.modal nome="excluir-exemplo" titulo="Excluir Jose Maria da Silva?"
                            descricao="O aluno sai das listas e não passa mais na catraca. O histórico financeiro é preservado, e o template biométrico é apagado de vez.">
                    <x-slot:acoes>
                        <x-ui.botao variante="secundario" data-fechar-modal>Cancelar</x-ui.botao>
                        <x-ui.botao variante="primario" class="bg-vencido-forte hover:bg-vencido-texto">
                            Excluir aluno
                        </x-ui.botao>
                    </x-slot:acoes>
                </x-ui.modal>
            </div>

            <h3 class="mt-10 text-lg text-texto">Layout do painel</h3>
            <p class="prosa mt-2 text-texto-2">
                Barra lateral recolhível, seletor de unidade e barra inferior no celular.
                <a href="{{ route('painel.inicio') }}" class="text-acao hover:underline">Ver em uso →</a>
            </p>
        </section>

        {{-- ======================== Espera e vazio ======================== --}}
        <section id="espera" class="scroll-mt-6 border-t border-borda py-12">
            <h2 class="text-2xl text-texto">Espera e vazio</h2>
            <p class="prosa mt-2 text-texto-2">
                Espera com o desenho do que vem, não com roda girando: a tela não pula quando o
                conteúdo chega, porque o espaço já estava reservado.
            </p>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <div>
                    <p class="mb-3 text-sm font-medium text-texto-2">Tabela carregando</p>
                    <x-ui.esqueleto formato="tabela" :linhas="4" />
                </div>
                <div>
                    <p class="mb-3 text-sm font-medium text-texto-2">Cartão carregando</p>
                    <x-ui.esqueleto formato="cartao" />
                    <p class="mt-6 mb-3 text-sm font-medium text-texto-2">Texto carregando</p>
                    <x-ui.esqueleto formato="texto" :linhas="3" />
                </div>
            </div>

            <div class="mt-8">
                <x-ui.estado-vazio
                    titulo="Nenhum aluno cadastrado ainda"
                    descricao="Cadastre o primeiro aluno para começar a controlar matrículas, mensalidades e acesso."
                    icone="M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm-6 7a6 6 0 0 1 12 0"
                >
                    <x-ui.botao variante="primario">Cadastrar aluno</x-ui.botao>
                </x-ui.estado-vazio>
            </div>
        </section>

        <footer class="border-t border-borda py-8 text-sm text-texto-mudo">
            Catálogo disponível apenas fora de produção. Base editorial em
            <code>docs/interface/README.md</code> e <code>docs/marca/README.md</code>.
        </footer>
    </div>

    <x-ui.notificacoes />
</x-layout.base>
