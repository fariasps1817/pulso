<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        :titulo="$this->editando() ? 'Editar aluno' : 'Novo aluno'"
        :subtitulo="$this->editando() ? $aluno->nome : 'Cadastro de quem vai treinar na academia.'"
        :voltar-para="['rotulo' => 'Alunos', 'url' => route('alunos.lista')]"
    />

    {{-- Um formulário, uma coluna. Duas colunas só onde os campos são curtos
         e relacionados: no celular tudo empilha do mesmo jeito. --}}
    <form wire:submit="salvar" class="flex flex-col gap-6">

        {{-- ==================== Dados pessoais ==================== --}}
        <x-ui.cartao>
            <h2 class="text-lg text-texto">Dados pessoais</h2>
            <p class="mt-1 text-sm text-texto-mudo">
                CPF, nascimento e WhatsApp são obrigatórios: sustentam o contrato,
                o controle de menor de idade e a cobrança.
            </p>

            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <x-ui.campo
                        nome="nome"
                        rotulo="Nome completo"
                        wire:model.blur="nome"
                        placeholder="Jose Maria da Silva"
                        ajuda="Gravado em caixa de título — digite como preferir."
                        autocomplete="off"
                    />
                </div>

                <x-ui.campo-mascara nome="cpf" rotulo="CPF" formato="cpf" wire:model.blur="cpf" />

                <x-ui.campo-mascara
                    nome="data_nascimento"
                    rotulo="Data de nascimento"
                    formato="data"
                    wire:model.blur="data_nascimento"
                    ajuda="Digitada, sem calendário."
                />

                <x-ui.campo-mascara nome="whatsapp" rotulo="WhatsApp" formato="celular" wire:model.blur="whatsapp" />

                <x-ui.campo-mascara
                    nome="telefone"
                    rotulo="Telefone"
                    formato="celular"
                    :obrigatorio="false"
                    wire:model.blur="telefone"
                />

                <x-ui.campo
                    nome="email"
                    rotulo="E-mail"
                    tipo="email"
                    :obrigatorio="false"
                    wire:model.blur="email"
                    placeholder="aluno@email.com"
                />

                <x-ui.selecao
                    nome="sexo"
                    rotulo="Sexo"
                    :obrigatorio="false"
                    wire:model="sexo"
                    :opcoes="['M' => 'Masculino', 'F' => 'Feminino']"
                />
            </div>
        </x-ui.cartao>

        {{-- ==================== Responsável ====================
             Aparece só quando a data digitada indica menor de 18. Mostrar
             sempre seria ruído em quase todo cadastro. --}}
        @if ($menorDeIdade)
            <x-ui.cartao :destaque="true">
                <div class="flex items-start gap-3">
                    <svg viewBox="0 0 20 20" class="mt-0.5 size-5 shrink-0 text-acao" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M10 13.5v-4M10 6.75v.01M10 2.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15Z" />
                    </svg>
                    <div>
                        <h2 class="text-lg text-texto">Responsável</h2>
                        <p class="mt-1 text-sm text-texto-2">
                            O aluno é menor de 18 anos, então os dados do responsável passam a ser obrigatórios.
                        </p>
                    </div>
                </div>

                <div class="mt-6 grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <x-ui.campo nome="responsavel_nome" rotulo="Nome do responsável" wire:model.blur="responsavel_nome" />
                    </div>

                    <x-ui.campo-mascara nome="responsavel_cpf" rotulo="CPF do responsável" formato="cpf"
                                        wire:model.blur="responsavel_cpf" />

                    <x-ui.campo-mascara nome="responsavel_telefone" rotulo="Telefone do responsável" formato="celular"
                                        wire:model.blur="responsavel_telefone" />

                    <x-ui.selecao
                        nome="responsavel_parentesco"
                        rotulo="Parentesco"
                        wire:model="responsavel_parentesco"
                        :opcoes="[
                            'Mãe' => 'Mãe',
                            'Pai' => 'Pai',
                            'Avó' => 'Avó',
                            'Avô' => 'Avô',
                            'Tio' => 'Tio',
                            'Tia' => 'Tia',
                            'Irmão' => 'Irmão',
                            'Irmã' => 'Irmã',
                            'Responsável legal' => 'Responsável legal',
                        ]"
                    />
                </div>
            </x-ui.cartao>
        @endif

        {{-- ==================== Endereço ==================== --}}
        <x-ui.cartao>
            <h2 class="text-lg text-texto">Endereço</h2>
            <p class="mt-1 text-sm text-texto-mudo">Opcional. O CEP preenche o resto.</p>

            <div class="mt-6 grid gap-5 md:grid-cols-6">
                <div class="md:col-span-2">
                    <x-ui.campo-mascara
                        nome="cep"
                        rotulo="CEP"
                        formato="cep"
                        :obrigatorio="false"
                        wire:model.live.debounce.300ms="cep"
                        :ajuda="$avisoCep ?: 'Preenche o endereço sozinho.'"
                    />

                    <div wire:loading wire:target="cep" class="mt-1 text-sm text-texto-mudo">buscando endereço…</div>
                </div>

                <div class="md:col-span-3">
                    <x-ui.campo nome="logradouro" rotulo="Rua" :obrigatorio="false" wire:model.blur="logradouro" />
                </div>

                <x-ui.campo nome="numero" rotulo="Número" :obrigatorio="false" wire:model.blur="numero" />

                <div class="md:col-span-3">
                    <x-ui.campo nome="complemento" rotulo="Complemento" :obrigatorio="false"
                                wire:model.blur="complemento" placeholder="Apto, bloco, referência" />
                </div>

                <div class="md:col-span-3">
                    <x-ui.campo nome="bairro" rotulo="Bairro" :obrigatorio="false" wire:model.blur="bairro" />
                </div>

                {{-- UF e cidade vêm do IBGE, nunca de digitação livre: texto
                     livre produz "Fortaleza", "fortaleza" e "Frotaleza" na
                     mesma base, e nenhum relatório por cidade funciona depois. --}}
                <div class="md:col-span-2">
                    <x-ui.selecao nome="uf" rotulo="Estado" :obrigatorio="false" wire:model.live="uf" :opcoes="$estados" />
                </div>

                <div class="md:col-span-4">
                    @if ($municipios === [])
                        <x-ui.campo nome="cidade" rotulo="Cidade" :obrigatorio="false" wire:model.blur="cidade"
                                    :ajuda="$uf === '' ? 'Escolha o estado primeiro.' : 'Lista do IBGE indisponível — digite à mão.'" />
                    @else
                        <x-ui.selecao nome="cidade" rotulo="Cidade" :obrigatorio="false" wire:model="cidade"
                                      :opcoes="array_combine($municipios, $municipios)" />
                    @endif
                </div>
            </div>
        </x-ui.cartao>

        {{-- ==================== Observações ==================== --}}
        <x-ui.cartao>
            <x-ui.area-texto
                nome="observacoes"
                rotulo="Observações"
                wire:model.blur="observacoes"
                placeholder="Restrição médica, preferência de horário, o que a recepção precisa lembrar."
            />
        </x-ui.cartao>

        {{-- Salvar fixo no rodapé: rolar até o fim para salvar é motivo de
             cadastro perdido em formulário longo. --}}
        <div class="sticky bottom-0 z-10 -mx-4 flex flex-col-reverse gap-3 border-t border-borda
                    bg-superficie/95 px-4 py-4 backdrop-blur sm:mx-0 sm:flex-row sm:justify-end sm:rounded-lg sm:border sm:px-5">
            <x-ui.botao :href="$this->editando() ? route('alunos.detalhes', $aluno) : route('alunos.lista')"
                        variante="secundario">
                Cancelar
            </x-ui.botao>

            <x-ui.botao tipo="submit" variante="primario" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="salvar">
                    {{ $this->editando() ? 'Salvar alterações' : 'Cadastrar aluno' }}
                </span>
                <span wire:loading wire:target="salvar">Salvando…</span>
            </x-ui.botao>
        </div>
    </form>
</div>
