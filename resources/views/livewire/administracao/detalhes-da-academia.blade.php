@php
    use App\Enums\SituacaoAcademia;
    use App\Support\Academia\Papeis;
@endphp

<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        :titulo="$academia->nome"
        :subtitulo="trim(($academia->cidade ?? '').($academia->uf ? '/'.$academia->uf : ''), '/')"
        :voltar-para="['rotulo' => 'Academias', 'url' => route('administracao.academias.lista')]"
    />

    @if (! $academia->situacao->permiteAcessoAoSistema())
        <x-ui.aviso tipo="atencao" titulo="Esta academia está sem acesso ao sistema">
            A equipe dela não consegue entrar desde
            {{ $academia->bloqueada_em?->format('d/m/Y') ?? 'a mudança de situação' }}.
            {{-- A catraca é a exceção deliberada. --}}
            @if ($academia->situacao->permiteAcessoDeAluno())
                A catraca continua liberando os alunos em dia — briga comercial entre a academia
                e o Pulso não pode punir quem não tem nada com isso.
            @endif
        </x-ui.aviso>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.cartao>
            <p class="text-sm text-texto-mudo">Alunos ativos</p>
            <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ $academia->total_alunos_ativos }}</p>
            @if ($academia->contagem_atualizada_em)
                <p class="mt-2 text-sm text-texto-mudo">
                    conferido {{ $academia->contagem_atualizada_em->diffForHumans() }}
                </p>
            @endif
        </x-ui.cartao>

        <x-ui.cartao>
            <p class="text-sm text-texto-mudo">Unidades</p>
            <p class="numeros mt-1 font-titulo text-3xl text-texto">{{ $unidades->count() }}</p>
            @if ($unidades->count() > 1)
                <x-ui.pilula estado="frequencia" class="mt-3">rede com filial</x-ui.pilula>
            @endif
        </x-ui.cartao>

        <x-ui.cartao>
            <p class="text-sm text-texto-mudo">Assinatura vence em</p>
            <p class="numeros mt-1 font-titulo text-3xl text-texto">
                {{ $academia->assinatura_vence_em?->format('d/m/Y') ?? '—' }}
            </p>
        </x-ui.cartao>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- O controle do SaaS. --}}
        <x-ui.cartao class="flex flex-col gap-5">
            <h2 class="font-titulo text-lg text-texto">Situação no Pulso</h2>

            <form wire:submit="alterarSituacao" class="flex flex-col gap-4">
                <x-ui.selecao
                    largura="100"
                    nome="situacao"
                    rotulo="Situação"
                    wire:model.live="situacao"
                    :vazio="null"
                    :opcoes="$situacoes"
                />

                @if (! SituacaoAcademia::from($situacao)->permiteAcessoAoSistema())
                    <x-ui.area-texto
                        nome="motivo_bloqueio"
                        rotulo="Motivo"
                        ajuda="Uso interno do Pulso. A academia vê que está suspensa, não este texto."
                        wire:model="motivo_bloqueio"
                    />
                @endif

                <x-ui.campo-mascara
                    largura="100"
                    nome="assinatura_vence_em"
                    rotulo="Assinatura vence em"
                    formato="data"
                    :obrigatorio="false"
                    wire:model="assinatura_vence_em"
                />

                <x-ui.botao tipo="submit">Aplicar</x-ui.botao>
            </form>

            <dl class="border-t border-borda pt-4 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-texto-mudo">Entrar no sistema</dt>
                    <dd class="text-texto">{{ $academia->situacao->permiteAcessoAoSistema() ? 'Liberado' : 'Bloqueado' }}</dd>
                </div>
                <div class="mt-1 flex justify-between gap-3">
                    <dt class="text-texto-mudo">Catraca dos alunos</dt>
                    <dd class="text-texto">{{ $academia->situacao->permiteAcessoDeAluno() ? 'Liberada' : 'Bloqueada' }}</dd>
                </div>
            </dl>
        </x-ui.cartao>

        <div class="flex flex-col gap-4">
            <x-ui.cartao class="flex flex-col gap-3">
                <h2 class="font-titulo text-lg text-texto">Unidades</h2>

                @foreach ($unidades as $unidade)
                    <div wire:key="unidade-{{ $unidade->id }}"
                         class="flex items-center justify-between gap-3 border-b border-borda py-2 last:border-0">
                        <span class="min-w-0 truncate text-texto">{{ $unidade->nome }}</span>
                        @unless ($unidade->ativa)
                            <x-ui.pilula estado="vencido">Inativa</x-ui.pilula>
                        @endunless
                    </div>
                @endforeach
            </x-ui.cartao>

            <x-ui.cartao class="flex flex-col gap-3">
                <h2 class="font-titulo text-lg text-texto">Equipe</h2>

                @foreach ($equipe as $pessoa)
                    <div wire:key="pessoa-{{ $pessoa->id }}"
                         class="flex items-center justify-between gap-3 border-b border-borda py-2 last:border-0">
                        <span class="min-w-0">
                            <span class="block truncate text-texto">{{ $pessoa->name }}</span>
                            <span class="block truncate text-sm text-texto-mudo">{{ $pessoa->email }}</span>
                        </span>
                        <span class="shrink-0 text-right text-sm text-texto-2">
                            {{ Papeis::rotulo($pessoa->getRoleNames()->first()) }}
                            @unless ($pessoa->ativo)
                                <span class="block text-texto-mudo">inativo</span>
                            @endunless
                        </span>
                    </div>
                @endforeach
            </x-ui.cartao>

            {{-- Dito na tela, e não só na documentação: quem usa precisa saber
                 por que não há um botão para "ver os alunos". --}}
            <p class="text-sm text-texto-mudo">
                Aluno, mensalidade e biometria desta academia não são acessíveis por aqui.
                O isolamento do banco não abre exceção para a equipe do Pulso — nem por esta tela,
                nem por uma conta comprometida.
            </p>
        </div>
    </div>
</div>
