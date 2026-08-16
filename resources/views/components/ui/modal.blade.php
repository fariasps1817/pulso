@props([
    'nome',
    'titulo',
    'descricao' => null,
])

{{--
    Diálogo de confirmação. Usa o <dialog> nativo: o navegador já entrega
    fechar com Esc, foco preso dentro e fundo inerte — reimplementar isso à
    mão é onde a acessibilidade costuma se perder.

    Abrir de qualquer lugar:  <button data-abrir-modal="excluir-aluno">
    Fechar:                   <button data-fechar-modal>

    Para ação irreversível, a confirmação diz o que será perdido — nunca só
    "Tem certeza?".
--}}

<dialog
    id="modal-{{ $nome }}"
    data-modal
    aria-labelledby="modal-{{ $nome }}-titulo"
    class="m-auto w-[calc(100%-2rem)] max-w-md rounded-lg border border-borda bg-superficie
           p-0 text-texto shadow-3 backdrop:bg-areia-950/50"
>
    <form method="dialog" class="flex flex-col">
        <div class="flex items-start justify-between gap-4 p-6 pb-0">
            <div>
                <h2 id="modal-{{ $nome }}-titulo" class="text-lg text-texto">{{ $titulo }}</h2>

                @if ($descricao)
                    <p class="mt-2 text-texto-2">{{ $descricao }}</p>
                @endif
            </div>

            <button type="submit" value="cancelar"
                    class="-m-1 shrink-0 rounded-md p-1 text-texto-mudo transition-colors hover:text-texto
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
                    aria-label="Fechar">
                <svg viewBox="0 0 20 20" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" aria-hidden="true" focusable="false">
                    <path d="m5 5 10 10M15 5 5 15" />
                </svg>
            </button>
        </div>

        @if ($slot->isNotEmpty())
            <div class="px-6 pt-4">{{ $slot }}</div>
        @endif

        <div class="mt-6 flex flex-col-reverse gap-2 border-t border-borda p-4 sm:flex-row sm:justify-end">
            {{ $acoes ?? '' }}
        </div>
    </form>
</dialog>
