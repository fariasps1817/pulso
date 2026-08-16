<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        titulo="Regras da academia"
        subtitulo="Três números que mudam o comportamento do sistema todo dia."
        :voltar-para="['rotulo' => 'Configurações', 'url' => route('configuracoes.painel')]"
    />

    <form wire:submit="salvar" class="flex flex-col gap-6">
        <x-ui.cartao class="flex flex-col gap-5">
            <div>
                <h2 class="font-titulo text-lg text-texto">Cobrança e catraca</h2>
                <p class="mt-1 text-sm text-texto-mudo">
                    Quantos dias depois do vencimento a catraca para de liberar o aluno.
                    Bloquear no dia seguinte gera briga no balcão; nunca bloquear torna a
                    catraca inútil como cobrança.
                </p>
            </div>

            <x-ui.grade-formulario>
                <x-ui.campo largura="25" nome="dias_tolerancia_bloqueio" rotulo="Dias de tolerância"
                            tipo="number" wire:model="dias_tolerancia_bloqueio" />
            </x-ui.grade-formulario>

            <p class="text-sm text-texto-mudo">
                Hoje: o aluno que vence dia 5 passa livre até dia
                <span class="numeros">{{ 5 + (int) $dias_tolerancia_bloqueio }}</span>.
                O que ele lê na catraca é sempre "Procure a recepção" — a dívida nunca aparece
                para quem está na fila atrás.
            </p>
        </x-ui.cartao>

        <x-ui.cartao class="flex flex-col gap-5">
            <div>
                <h2 class="font-titulo text-lg text-texto">Baixa frequência</h2>
                <p class="mt-1 text-sm text-texto-mudo">
                    A partir de quantos dias sem treinar o aluno aparece no Radar como sumido.
                    É o alerta que existe para evitar a evasão antes de ela acontecer.
                </p>
            </div>

            <x-ui.grade-formulario>
                <x-ui.campo largura="25" nome="dias_baixa_frequencia" rotulo="Dias sem treinar"
                            tipo="number" wire:model="dias_baixa_frequencia" />
            </x-ui.grade-formulario>
        </x-ui.cartao>

        <x-ui.cartao class="flex flex-col gap-5">
            <div>
                <h2 class="font-titulo text-lg text-texto">Idade mínima</h2>
                <p class="mt-1 text-sm text-texto-mudo">
                    Abaixo dessa idade o balcão não conclui a matrícula. Menor de 18 continua
                    exigindo os dados do responsável, independente deste número.
                </p>
            </div>

            <x-ui.grade-formulario>
                <x-ui.campo largura="25" nome="idade_minima" rotulo="Idade mínima"
                            tipo="number" wire:model="idade_minima" />
            </x-ui.grade-formulario>
        </x-ui.cartao>

        <div class="flex flex-wrap gap-2">
            <x-ui.botao tipo="submit">Salvar</x-ui.botao>
            <x-ui.botao :href="route('configuracoes.painel')" variante="secundario">Voltar</x-ui.botao>
        </div>
    </form>
</div>
