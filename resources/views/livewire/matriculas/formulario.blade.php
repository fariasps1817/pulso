@php
    use App\Enums\TipoMatricula;
    use App\Support\Formato;
@endphp

<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        titulo="Nova matrícula"
        subtitulo="Liga o aluno a um plano e a uma unidade."
        :voltar-para="['rotulo' => 'Matrículas', 'url' => route('matriculas.lista')]"
    />

    <form wire:submit="salvar" class="flex flex-col gap-6">

        <x-ui.cartao>
            <h2 class="text-lg text-texto">Quem e o quê</h2>

            <x-ui.grade-formulario class="mt-6">
                <x-ui.selecao largura="50" nome="aluno_id" rotulo="Aluno" wire:model="aluno_id"
                              :opcoes="$alunos->all()" vazio="Escolha o aluno…" />

                <x-ui.selecao largura="25" nome="plano_id" rotulo="Plano" wire:model.live="plano_id"
                              :opcoes="$planos->pluck('nome', 'id')->all()" vazio="Escolha o plano…" />

                <x-ui.selecao largura="25" nome="unidade_id" rotulo="Unidade" wire:model="unidade_id"
                              :opcoes="$unidades->all()" vazio="Escolha a unidade…" />
            </x-ui.grade-formulario>

            @if ($plano)
                <div class="mt-5 rounded-md border border-borda bg-superficie-2 p-4 text-sm text-texto-2">
                    <strong class="text-texto">{{ $plano->nome }}</strong> ·
                    {{ $plano->duracao_meses === 1 ? 'mensal' : $plano->duracao_meses.' meses' }} ·
                    <span class="numeros">{{ Formato::dinheiro($plano->valor_mensal) }}</span>
                    @if ($plano->acesso_todas_unidades)
                        · acesso a todas as unidades
                    @endif
                    @if ($plano->taxa_matricula > 0)
                        · taxa de <span class="numeros">{{ Formato::dinheiro($plano->taxa_matricula) }}</span>
                    @endif
                </div>
            @endif
        </x-ui.cartao>

        <x-ui.cartao>
            <h2 class="text-lg text-texto">Tipo</h2>

            <div class="mt-4 flex flex-col gap-3">
                {{-- A experiência só aparece quando o plano escolhido a oferece:
                     oferecer teste num plano que não tem seria promessa falsa
                     ao aluno, no balcão. --}}
                @if ($planoTemExperiencia)
                    <label class="flex min-h-toque cursor-pointer items-start gap-3 rounded-md border border-borda p-3
                                  transition-colors hover:bg-superficie-2">
                        <input type="radio" wire:model.live="tipo" value="{{ TipoMatricula::Experiencia->value }}"
                               class="mt-1 size-5 shrink-0 border-borda-forte text-acao">
                        <span>
                            <span class="block font-medium text-texto">Experiência</span>
                            <span class="block text-sm text-texto-mudo">
                                Período de teste, sem contrato e sem mensalidade. Acaba pelo que vier primeiro:
                                @if ($plano->dias_experiencia > 0) {{ $plano->dias_experiencia }} dias @endif
                                @if ($plano->dias_experiencia > 0 && $plano->sessoes_experiencia > 0) ou @endif
                                @if ($plano->sessoes_experiencia > 0) {{ $plano->sessoes_experiencia }} sessões @endif
                            </span>
                        </span>
                    </label>
                @endif

                <label class="flex min-h-toque cursor-pointer items-start gap-3 rounded-md border border-borda p-3
                              transition-colors hover:bg-superficie-2">
                    <input type="radio" wire:model.live="tipo" value="{{ TipoMatricula::Regular->value }}"
                           class="mt-1 size-5 shrink-0 border-borda-forte text-acao">
                    <span>
                        <span class="block font-medium text-texto">Matrícula</span>
                        <span class="block text-sm text-texto-mudo">
                            Gera mensalidade todo mês. Exige contrato assinado.
                        </span>
                    </span>
                </label>
            </div>

            @error('tipo')
                <p class="mt-2 text-sm text-vencido-texto">{{ $message }}</p>
            @enderror
        </x-ui.cartao>

        <x-ui.cartao>
            <h2 class="text-lg text-texto">Vigência e cobrança</h2>

            <x-ui.grade-formulario class="mt-6">
                <x-ui.campo-mascara largura="25" nome="inicio_em" rotulo="Início" formato="data"
                                    wire:model.blur="inicio_em" />

                @unless ($this->ehExperiencia())
                    <x-ui.campo-mascara largura="25" nome="contrato_assinado_em" rotulo="Contrato assinado em"
                                        formato="data" wire:model.blur="contrato_assinado_em" />

                    <x-ui.selecao largura="25" nome="dia_vencimento" rotulo="Vence todo dia"
                                  wire:model="dia_vencimento" :vazio="false"
                                  :opcoes="collect(range(1, 28))->mapWithKeys(fn ($d) => [$d => 'Dia '.$d])->all()" />

                    <x-ui.campo-dinheiro largura="25" nome="valor_mensal" rotulo="Valor mensal"
                                         wire:model.blur="valor_mensal" />
                @endunless
            </x-ui.grade-formulario>

            @if ($this->ehExperiencia())
                <x-ui.aviso tipo="informativo" class="mt-5">
                    A experiência não gera mensalidade nem exige contrato. Quando o aluno decidir ficar,
                    a ficha da matrícula tem o botão para converter — e é ali que entram o contrato e o
                    dia de vencimento.
                </x-ui.aviso>

                {{-- O valor fica registrado desde já: ele é o que valerá na
                     conversão, e serve de referência se houver desconto. --}}
                <div class="mt-5 max-w-xs">
                    <x-ui.campo-dinheiro nome="valor_mensal" rotulo="Valor mensal na conversão"
                                         wire:model.blur="valor_mensal" />
                </div>
            @endif
        </x-ui.cartao>

        <x-ui.cartao>
            <x-ui.area-texto nome="observacoes" rotulo="Observações" :obrigatorio="false"
                             wire:model.blur="observacoes"
                             placeholder="Desconto combinado, condição especial, o que a recepção precisa lembrar." />
        </x-ui.cartao>

        <div class="sticky bottom-0 z-10 -mx-4 flex flex-col-reverse gap-3 border-t border-borda
                    bg-superficie/95 px-4 py-4 backdrop-blur sm:mx-0 sm:flex-row sm:justify-end sm:rounded-lg sm:border sm:px-5">
            <x-ui.botao :href="route('matriculas.lista')" variante="secundario">Cancelar</x-ui.botao>

            <x-ui.botao tipo="submit" variante="primario" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="salvar">Criar matrícula</span>
                <span wire:loading wire:target="salvar">Salvando…</span>
            </x-ui.botao>
        </div>
    </form>
</div>
