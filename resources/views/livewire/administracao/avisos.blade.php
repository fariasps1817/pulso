@php
    use Carbon\CarbonImmutable;
@endphp

<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        titulo="Avisos"
        subtitulo="O que o Pulso mostra no topo da tela das academias."
    />

    <x-ui.cartao class="flex flex-col gap-5">
        <h2 class="font-titulo text-lg text-texto">Novo aviso</h2>

        <form wire:submit="publicar" class="flex flex-col gap-5">
            <x-ui.grade-formulario>
                <x-ui.selecao
                    largura="50"
                    nome="academia_id"
                    rotulo="Para quem"
                    :obrigatorio="false"
                    wire:model="academia_id"
                    vazio="Todas as academias"
                    :opcoes="$academias"
                />

                <x-ui.selecao
                    largura="25"
                    nome="tipo"
                    rotulo="Tom"
                    wire:model="tipo"
                    :vazio="null"
                    :opcoes="[
                        'informativo' => 'Informativo',
                        'atencao' => 'Atenção',
                        'erro' => 'Urgente',
                    ]"
                />

                <x-ui.campo largura="25" nome="titulo" rotulo="Título" wire:model.blur="titulo" />

                <x-ui.area-texto
                    nome="mensagem"
                    rotulo="Recado"
                    obrigatorio
                    wire:model.blur="mensagem"
                />

                <x-ui.campo-mascara largura="25" nome="exibir_de" rotulo="Aparece a partir de"
                                    formato="data" wire:model.blur="exibir_de" />

                <x-ui.campo-mascara largura="25" nome="exibir_ate" rotulo="Some depois de"
                                    formato="data" wire:model.blur="exibir_ate" />
            </x-ui.grade-formulario>

            <x-ui.interruptor
                nome="dispensavel"
                rotulo="A academia pode fechar o aviso"
                :ligado="$dispensavel"
                wire:model="dispensavel"
                descricao="Desligue para alerta de bloqueio: aviso que some ao ser fechado deixa o dono descobrir na segunda-feira, com a academia parada."
            />

            <div>
                <x-ui.botao tipo="submit">Publicar</x-ui.botao>
            </div>
        </form>
    </x-ui.cartao>

    <x-ui.cartao class="flex flex-col gap-3">
        <h2 class="font-titulo text-lg text-texto">Publicados</h2>

        @forelse ($avisos as $aviso)
            @php
                $noAr = $aviso->exibir_de->toDateString() <= $hoje && $aviso->exibir_ate->toDateString() >= $hoje;
                $futuro = $aviso->exibir_de->toDateString() > $hoje;
            @endphp

            <div wire:key="aviso-{{ $aviso->id }}"
                 class="flex flex-col gap-3 border-b border-borda pb-4 last:border-0 last:pb-0
                        sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-texto">{{ $aviso->titulo }}</p>
                    <p class="mt-0.5 text-sm text-texto-2">{{ $aviso->mensagem }}</p>
                    <p class="numeros mt-1 text-sm text-texto-mudo">
                        {{ $aviso->academia?->nome ?? 'Todas as academias' }}
                        · {{ $aviso->exibir_de->format('d/m/Y') }} a {{ $aviso->exibir_ate->format('d/m/Y') }}
                        @unless ($aviso->dispensavel)
                            · não pode ser fechado
                        @endunless
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    @if ($noAr)
                        <x-ui.pilula estado="pago">No ar</x-ui.pilula>
                    @elseif ($futuro)
                        <x-ui.pilula estado="avencer">Agendado</x-ui.pilula>
                    @else
                        <x-ui.pilula estado="frequencia">Encerrado</x-ui.pilula>
                    @endif

                    <button type="button" wire:click="remover({{ $aviso->id }})"
                            wire:confirm="Remover este aviso? Ele some da tela das academias na hora."
                            class="min-h-toque rounded-md px-2 text-sm text-vencido-texto hover:underline
                                   focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                        Remover
                    </button>
                </div>
            </div>
        @empty
            <x-ui.estado-vazio
                titulo="Nenhum aviso publicado"
                descricao="Use para avisar de vencimento de assinatura ou de manutenção programada."
                icone="M10 13.5v-4M10 6.75v.01M10 2.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15Z"
            />
        @endforelse
    </x-ui.cartao>
</div>
