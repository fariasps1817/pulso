{{--
    O tráfego cru fica visível de propósito.

    Quando o equipamento chegar, é esta conversa que vai ser comparada com a
    real — e é olhando o TAB entre os campos, o "OK" da resposta e o comando
    saindo da fila que se descobre uma diferença, não olhando uma tabela
    bonita de acessos.
--}}
<div class="flex flex-col gap-6">
    <x-ui.cabecalho-pagina
        titulo="Simulador de catraca"
        subtitulo="Um aparelho de mentira falando o protocolo de verdade."
    >
        <x-ui.botao :href="route('acesso.painel')" variante="secundario">Ver o acesso</x-ui.botao>
    </x-ui.cabecalho-pagina>

    <x-ui.aviso tipo="informativo" titulo="O que este simulador faz">
        Ele monta a mesma linha que o SenseFace 2A monta e a entrega nos mesmos endpoints
        <code>/iclock/*</code>, passando pelo mesmo middleware. Nada aqui grava direto no banco.
        Fica fora do ar em produção.
    </x-ui.aviso>

    <x-ui.cartao class="flex flex-col gap-4">
        <h2 class="font-titulo text-lg text-texto">O aparelho detectou alguém</h2>

        <x-ui.grade-formulario>
            <x-ui.selecao
                largura="50"
                nome="dispositivoId"
                rotulo="Aparelho"
                wire:model="dispositivoId"
                :vazio="null"
                :opcoes="$aparelhos->mapWithKeys(fn ($a) => [$a->id => $a->nome.' — '.$a->numero_serie])->all()"
            />

            <x-ui.selecao
                largura="50"
                nome="alunoId"
                rotulo="Aluno"
                wire:model="alunoId"
                :vazio="null"
                :opcoes="$alunos->pluck('nome', 'id')->all()"
            />

            <x-ui.selecao
                largura="50"
                nome="metodo"
                rotulo="Reconhecido por"
                wire:model="metodo"
                :vazio="null"
                :opcoes="[15 => 'Rosto', 1 => 'Digital', 4 => 'Cartão', 2 => 'Matrícula digitada']"
            />

            <x-ui.campo
                largura="50"
                nome="pinAvulso"
                rotulo="Matrícula avulsa"
                :obrigatorio="false"
                wire:model="pinAvulso"
                placeholder="vazio = usar o aluno acima"
            />
        </x-ui.grade-formulario>

        <div class="flex flex-wrap gap-2">
            <x-ui.botao wire:click="detectar">Detectar aluno</x-ui.botao>
            <x-ui.botao wire:click="reenviarUltimoLote" variante="secundario">
                Reenviar o mesmo lote
            </x-ui.botao>
            <x-ui.botao wire:click="handshake" variante="secundario">Ligar o aparelho</x-ui.botao>
            <x-ui.botao wire:click="consultarFila" variante="secundario">Consultar a fila</x-ui.botao>
        </div>
    </x-ui.cartao>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.cartao class="flex flex-col gap-3">
            <h2 class="font-titulo text-lg text-texto">Na academia agora</h2>

            @forelse ($presentes as $entrada)
                <div wire:key="dentro-{{ $entrada->id }}"
                     class="flex items-center justify-between gap-3 border-b border-borda py-2 last:border-0">
                    <span class="min-w-0 truncate text-texto">{{ $entrada->aluno->nome }}</span>
                    <span class="numeros shrink-0 text-sm text-texto-mudo">
                        desde {{ $entrada->ocorreu_em->format('H:i') }}
                    </span>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-texto-mudo">
                    Ninguém dentro. A próxima detecção será uma entrada.
                </p>
            @endforelse
        </x-ui.cartao>

        <x-ui.cartao class="flex flex-col gap-3">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-titulo text-lg text-texto">A conversa</h2>
                @if ($conversa !== [])
                    <button type="button" wire:click="limpar"
                            class="rounded-sm text-sm text-acao hover:underline
                                   focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                        Limpar
                    </button>
                @endif
            </div>

            @forelse ($conversa as $indice => $troca)
                <div wire:key="troca-{{ $indice }}" class="border-b border-borda pb-3 last:border-0 last:pb-0">
                    <p class="text-sm font-medium text-texto">{{ $troca['titulo'] }}</p>

                    {{-- O TAB aparece como espaço largo; é a fronteira entre campos. --}}
                    <pre class="mt-2 overflow-x-auto rounded-sm bg-superficie-2 p-2 text-xs text-texto-2"><code>{{ $troca['enviado'] }}</code></pre>
                    <pre class="mt-1 overflow-x-auto rounded-sm bg-superficie-2 p-2 text-xs text-texto-2"><code>← {{ $troca['recebido'] }}</code></pre>

                    <p @class([
                        'mt-2 text-sm',
                        'text-vencido-texto' => str_starts_with($troca['nota'], 'ATENÇÃO'),
                        'text-texto-mudo' => ! str_starts_with($troca['nota'], 'ATENÇÃO'),
                    ])>{{ $troca['nota'] }}</p>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-texto-mudo">
                    Nada ainda. Comece por "Ligar o aparelho".
                </p>
            @endforelse
        </x-ui.cartao>
    </div>
</div>
