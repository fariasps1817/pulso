@props([
    /* Quanto o campo ocupa na grade: 25, 50, 75 ou 100. */
    'largura' => '100',
    'nome',
    'rotulo',
    'ajuda' => 'JPG ou PNG, até 2 MB.',
    'obrigatorio' => false,
    'atual' => null,
    /* quadrado | retangulo */
    'formato' => 'quadrado',
])

{{--
    Envio de imagem: foto do aluno, logo da academia.

    Mostra a prévia antes de salvar — na recepção, a foto é tirada na hora e
    quem cadastra precisa ver se ficou de olho fechado antes de gravar.

    A foto do aluno é de identificação da recepção. **Não** é template
    biométrico: aquele é cifrado, fica em credenciais_acesso e nunca guarda a
    imagem do rosto.
--}}

@php
    $id = $attributes->get('id', $nome);
    $proporcao = $formato === 'quadrado' ? 'aspect-square w-32' : 'aspect-[3/1] w-full max-w-sm';
@endphp

<x-ui.grupo-campo :nome="$nome" :rotulo="$rotulo" :ajuda="$ajuda" :obrigatorio="$obrigatorio" :campo-id="$id" :largura="$largura">
    <div x-data="{
        previa: @js($atual),
        escolher(evento) {
            const arquivo = evento.target.files[0];
            if (! arquivo) return;
            this.previa = URL.createObjectURL(arquivo);
        },
        limpar() {
            this.previa = null;
            this.$refs.campo.value = '';
        },
    }" class="flex items-start gap-4">
        <div class="{{ $proporcao }} flex shrink-0 items-center justify-center overflow-hidden rounded-lg
                    border border-dashed border-borda-forte bg-superficie-2">
            <template x-if="previa">
                <img :src="previa" alt="" class="size-full object-cover">
            </template>

            <template x-if="! previa">
                <svg viewBox="0 0 20 20" class="size-8 text-texto-mudo" fill="none" stroke="currentColor"
                     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 15.5 7 11l3 3 3-2.5 4 4M3 4.5h14v11H3v-11ZM7.5 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" />
                </svg>
            </template>
        </div>

        <div class="flex flex-col gap-2">
            <input
                type="file"
                id="{{ $id }}"
                name="{{ $nome }}"
                accept="image/jpeg,image/png,image/webp"
                x-ref="campo"
                @change="escolher"
                class="sr-only"
                @if ($obrigatorio) required @endif
            >

            <label for="{{ $id }}"
                   class="inline-flex min-h-toque cursor-pointer items-center justify-center rounded-md border
                          border-borda-forte bg-superficie px-4 font-medium text-texto transition-colors
                          hover:bg-superficie-2
                          focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-foco">
                Escolher imagem
            </label>

            <button type="button" x-show="previa" x-cloak @click="limpar"
                    class="min-h-toque rounded-md px-4 text-vencido-texto transition-colors hover:bg-vencido-fundo
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco">
                Remover
            </button>
        </div>
    </div>
</x-ui.grupo-campo>
