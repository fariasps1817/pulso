{{--
    Área de avisos rápidos ("Pagamento registrado"). Fica uma vez no layout do
    painel; as telas só disparam o evento.

    Disparar do servidor:  session()->flash('pulso.aviso', ['tipo' => 'sucesso', 'texto' => '...'])
    Disparar do navegador: window.pulso.avisar('Pagamento registrado')

    No celular aparece embaixo, onde o polegar está e onde não cobre o
    cabeçalho; no desktop, no canto inferior direito.

    aria-live="polite" faz o leitor de tela anunciar sem interromper o que a
    pessoa está fazendo — o botão já disse o que ia acontecer.
--}}

<div
    x-data="notificacoesPulso()"
    class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 p-4
           sm:inset-x-auto sm:right-0 sm:items-end"
    role="region"
    aria-live="polite"
    aria-label="Avisos do sistema"
>
    <template x-for="aviso in avisos" :key="aviso.id">
        <div
            x-transition.opacity.duration.200ms
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-md border p-4 shadow-2"
            :class="{
                'border-pago-borda bg-pago-fundo text-pago-texto': aviso.tipo === 'sucesso',
                'border-vencido-borda bg-vencido-fundo text-vencido-texto': aviso.tipo === 'erro',
                'border-borda bg-superficie text-texto': aviso.tipo === 'informativo',
            }"
        >
            <svg viewBox="0 0 20 20" class="mt-0.5 size-5 shrink-0" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path x-show="aviso.tipo === 'sucesso'" d="M4 10.5 8 14.5 16 6" />
                <path x-show="aviso.tipo === 'erro'" d="M10 5.5v6M10 14.5v.5M10 2.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15Z" />
                <path x-show="aviso.tipo === 'informativo'" d="M10 13.5v-4M10 6.75v.01M10 2.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15Z" />
            </svg>

            <p class="flex-1 text-sm" x-text="aviso.texto"></p>

            <button type="button" @click="remover(aviso.id)"
                    class="-m-1 shrink-0 rounded-md p-1 transition-opacity hover:opacity-70
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
                    aria-label="Fechar aviso">
                <svg viewBox="0 0 20 20" class="size-4" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" aria-hidden="true">
                    <path d="m5 5 10 10M15 5 5 15" />
                </svg>
            </button>
        </div>
    </template>
</div>

@if (session()->has('pulso.aviso'))
    @php $aviso = session('pulso.aviso'); @endphp
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.pulso.avisar(@json($aviso['texto']), @json($aviso['tipo'] ?? 'sucesso'));
        });
    </script>
@endif
