@php
    $contato = config('pulso.contato');
    $whatsapp = 'https://wa.me/'.$contato['whatsapp'].'?text='.rawurlencode('Olá! Quero conhecer o Pulso para a minha academia.');

    $recursos = [
        [
            'titulo' => 'Cadastros',
            'texto' => 'Alunos, profissionais, planos, matrículas e o tempo de experiência a que o aluno tem direito.',
            'icone' => 'M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm-6 7a6 6 0 0 1 12 0',
        ],
        [
            'titulo' => 'Financeiro',
            'texto' => 'Mensalidades pagas, a vencer e vencidas, com cobrança por Pix e cartão no mesmo lugar.',
            'icone' => 'M2.5 6.5h15v9h-15v-9Zm0 3.5h15M5.5 13h3',
        ],
        [
            'titulo' => 'Controle de acesso',
            'texto' => 'Reconhecimento facial e biometria ligados à catraca, liberando ou bloqueando a passagem na hora.',
            'icone' => 'M6.5 3h-3v3M13.5 3h3v3M6.5 17h-3v-3M13.5 17h3v-3M7.5 8v1M12.5 8v1M7.5 12.5s1 1 2.5 1 2.5-1 2.5-1',
        ],
        [
            'titulo' => 'Radar',
            'texto' => 'Inadimplência, baixa frequência e risco de evasão reunidos na tela que a gestão abre primeiro.',
            'icone' => 'M10 2.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15Zm0 4a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Zm0 3v.01',
        ],
        [
            'titulo' => 'Notificações',
            'texto' => 'Aviso pelo WhatsApp antes do vencimento, com link de Pix — sem constranger o aluno.',
            'icone' => 'M3.5 16.5 4.7 13A6.6 6.6 0 1 1 7 15.3l-3.5 1.2Z',
        ],
        [
            'titulo' => 'No celular',
            'texto' => 'Feito para ser usado em pé, no balcão e na mão. Toque grande, resumo antes do detalhe.',
            'icone' => 'M6.5 2.5h7v15h-7v-15Zm2.5 12.5h2',
        ],
    ];
@endphp

<x-layout.publico
    descricao="Pulso é o sistema de gestão para academias: matrículas, mensalidades, catraca com biometria e alertas de inadimplência e evasão. Feito no Nordeste."
>
    {{-- ================= Hero ================= --}}
    <section class="relative overflow-hidden border-b border-borda">
        <div aria-hidden="true"
             class="pointer-events-none absolute inset-0 bg-[radial-gradient(70%_60%_at_15%_0%,var(--cor-acao-sutil),transparent_70%)]">
        </div>

        <div class="relative mx-auto max-w-6xl px-5 py-20 md:py-28">
            <p class="inline-flex items-center gap-2 rounded-pill border border-borda bg-superficie px-3 py-1 text-sm text-texto-2">
                <span class="size-2 rounded-pill bg-sol-400"></span>
                {{ config('pulso.slogans.origem') }}
            </p>

            <h1 class="mt-6 max-w-3xl text-4xl leading-[1.1] text-texto">
                {{ config('pulso.slogans.principal') }}
            </h1>

            <p class="prosa mt-5 text-lg text-texto-2">
                Toda academia tem um pulso: gente entrando, treino acontecendo, dinheiro circulando.
                Quando esse pulso some do radar, o prejuízo aparece antes do dono perceber.
                O Pulso mede a batida — acesso por acesso, mensalidade por mensalidade — e avisa a tempo.
            </p>

            <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                <x-ui.botao :href="$whatsapp" variante="primario" tamanho="grande" rel="noopener" target="_blank">
                    <svg viewBox="0 0 20 20" class="size-5" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M3.5 16.5 4.7 13A6.6 6.6 0 1 1 7 15.3l-3.5 1.2Z" />
                    </svg>
                    Falar no WhatsApp
                </x-ui.botao>

                <x-ui.botao :href="route('login')" variante="secundario" tamanho="grande">
                    Entrar no sistema
                </x-ui.botao>
            </div>

            {{-- Amostra do Radar: mostra o produto em vez de descrevê-lo. --}}
            <div class="mt-16 grid gap-4 sm:grid-cols-3">
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

                <x-ui.cartao>
                    <p class="text-sm text-texto-mudo">Sumiram há 15 dias</p>
                    <p class="numeros mt-1 font-titulo text-3xl text-texto">23</p>
                    <x-ui.pilula estado="frequencia" class="mt-3">risco de evasão</x-ui.pilula>
                </x-ui.cartao>
            </div>

            <p class="mt-3 text-sm text-texto-mudo">Números ilustrativos — é assim que o Radar abre.</p>
        </div>
    </section>

    {{-- ================= Recursos ================= --}}
    <section id="recursos" class="scroll-mt-20 border-b border-borda">
        <div class="mx-auto max-w-6xl px-5 py-20">
            <h2 class="text-2xl text-texto">O que o Pulso faz</h2>
            <p class="prosa mt-3 text-texto-2">
                Tudo em português direto. Quem usa é recepcionista, gerente e dono — não analista de TI.
            </p>

            <ul class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($recursos as $recurso)
                    <li>
                        <x-ui.cartao class="h-full">
                            <span class="inline-flex size-11 items-center justify-center rounded-md bg-acao-sutil text-acao">
                                <svg viewBox="0 0 20 20" class="size-6" fill="none" stroke="currentColor" stroke-width="1.6"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <path d="{{ $recurso['icone'] }}" />
                                </svg>
                            </span>
                            <h3 class="mt-4 text-lg text-texto">{{ $recurso['titulo'] }}</h3>
                            <p class="mt-2 text-texto-2">{{ $recurso['texto'] }}</p>
                        </x-ui.cartao>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ================= Radar ================= --}}
    <section id="radar" class="scroll-mt-20 border-b border-borda bg-superficie-2">
        <div class="mx-auto grid max-w-6xl gap-12 px-5 py-20 lg:grid-cols-2 lg:items-center">
            <div>
                <p class="font-medium text-acao">Radar</p>
                <h2 class="mt-2 text-2xl text-texto">A tela que a gestão abre primeiro</h2>
                <p class="prosa mt-4 text-texto-2">
                    Aluno não avisa que vai sair: ele para de aparecer. Quando a mensalidade vence, já faz semanas
                    que ele sumiu. O Radar junta as duas leituras — o caixa e a frequência — e mostra quem está
                    prestes a ir embora enquanto ainda dá para ligar e trazer de volta.
                </p>

                <ul class="mt-6 flex flex-col gap-3 text-texto-2">
                    @foreach ([
                        'Total vencido, quanto vence hoje e quem sumiu, no mesmo painel.',
                        'Inadimplência e evasão em cores diferentes — são problemas diferentes.',
                        'Resumo primeiro, detalhe depois. No celular, a lista cabe no polegar.',
                    ] as $item)
                        <li class="flex items-start gap-3">
                            <svg viewBox="0 0 20 20" class="mt-1 size-5 shrink-0 text-acao" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M4 10.5 8 14.5 16 6" />
                            </svg>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <x-ui.cartao class="shadow-2">
                <div class="flex items-center justify-between border-b border-borda pb-4">
                    <h3 class="text-lg text-texto">Mensalidades</h3>
                    <span class="text-sm text-texto-mudo">Agosto</span>
                </div>

                {{-- No celular a tabela financeira vira lista de cartões, com o valor à direita. --}}
                <ul class="divide-y divide-borda">
                    @foreach ([
                        ['nome' => 'Ana Beatriz Nogueira', 'plano' => 'Mensal · musculação', 'valor' => 129.90, 'estado' => 'pago'],
                        ['nome' => 'Carlos Eduardo Lima', 'plano' => 'Trimestral · completo', 'valor' => 289.00, 'estado' => 'avencer'],
                        ['nome' => 'Jonas Ferreira Alves', 'plano' => 'Mensal · musculação', 'valor' => 129.90, 'estado' => 'vencido'],
                        ['nome' => 'Marina Sousa Vieira', 'plano' => 'Anual · completo', 'valor' => 99.00, 'estado' => 'frequencia'],
                    ] as $linha)
                        <li class="flex items-center justify-between gap-4 py-3.5">
                            <div class="min-w-0">
                                <p class="truncate text-texto">{{ $linha['nome'] }}</p>
                                <p class="truncate text-sm text-texto-mudo">{{ $linha['plano'] }}</p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                <span class="numeros text-texto">{{ \App\Support\Formato::dinheiro($linha['valor']) }}</span>
                                <x-ui.pilula :estado="$linha['estado']" />
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.cartao>
        </div>
    </section>

    {{-- ================= Segurança e LGPD ================= --}}
    <section id="seguranca" class="scroll-mt-20 border-b border-borda">
        <div class="mx-auto max-w-6xl px-5 py-20">
            <h2 class="text-2xl text-texto">Biometria é dado sensível — e a gente trata como tal</h2>
            <p class="prosa mt-3 text-texto-2">
                O sistema pede o rosto e a digital do aluno. Isso é dado sensível pela LGPD (art. 11),
                então precisa ser transparente de verdade, não em letra miúda.
            </p>

            <div class="mt-10 grid gap-5 md:grid-cols-2">
                @foreach ([
                    ['Consentimento separado', 'O aceite da biometria não vem embutido no contrato de matrícula, e a finalidade está escrita: controle de acesso e frequência.'],
                    ['Alternativa sempre disponível', 'Quem não quiser dar biometria entra por cartão, QR ou PIN. Isso funciona desde o primeiro dia — não é item de backlog.'],
                    ['Template, nunca a foto', 'O que fica guardado é o template biométrico cifrado, com acesso auditado. A imagem do rosto não é armazenada.'],
                    ['Exclusão ao cancelar', 'Cancelou a matrícula, a biometria é excluída — e fica o registro de que foi excluída.'],
                ] as [$titulo, $texto])
                    <x-ui.cartao class="h-full">
                        <h3 class="text-lg text-texto">{{ $titulo }}</h3>
                        <p class="mt-2 text-texto-2">{{ $texto }}</p>
                    </x-ui.cartao>
                @endforeach
            </div>

            <x-ui.cartao class="mt-5" :destaque="true">
                <h3 class="text-lg text-texto">O aluno inadimplente não é exposto</h3>
                <p class="prosa mt-2 text-texto-2">
                    Na catraca, acesso negado por inadimplência mostra <strong class="text-texto">“Procure a recepção”</strong> —
                    nunca “mensalidade vencida” com a fila inteira olhando. É exigência do Código de Defesa do
                    Consumidor (art. 42) e é o jeito certo de tratar quem treina ali.
                </p>
            </x-ui.cartao>
        </div>
    </section>

    {{-- ================= Contato ================= --}}
    <section id="contato" class="scroll-mt-20">
        <div class="mx-auto max-w-6xl px-5 py-20">
            <div class="rounded-xl border border-borda bg-superficie p-8 shadow-2 md:p-12">
                <div class="flex flex-col gap-8 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-2xl text-texto">{{ config('pulso.slogans.comercial') }}</h2>
                        <p class="prosa mt-3 text-texto-2">
                            Conte como sua academia funciona hoje que a gente mostra o Pulso rodando —
                            sem compromisso e sem enrolação.
                        </p>

                        <dl class="mt-6 flex flex-col gap-2 text-texto-2">
                            <div class="flex gap-2">
                                <dt class="text-texto-mudo">WhatsApp:</dt>
                                <dd>
                                    <a href="{{ $whatsapp }}" rel="noopener" target="_blank"
                                       class="numeros rounded-sm text-acao hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                                        {{ \App\Support\Formato::telefone($contato['whatsapp']) }}
                                    </a>
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-texto-mudo">E-mail:</dt>
                                <dd>
                                    <a href="mailto:{{ $contato['email'] }}"
                                       class="rounded-sm text-acao hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                                        {{ $contato['email'] }}
                                    </a>
                                </dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-texto-mudo">Onde estamos:</dt>
                                <dd>{{ $contato['cidade'] }} — {{ $contato['uf'] }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="flex shrink-0 flex-col gap-3">
                        <x-ui.botao :href="$whatsapp" variante="sol" tamanho="grande" rel="noopener" target="_blank">
                            Pedir uma demonstração
                        </x-ui.botao>
                        <x-ui.botao :href="'mailto:'.$contato['email']" variante="secundario" tamanho="grande">
                            Mandar um e-mail
                        </x-ui.botao>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layout.publico>
