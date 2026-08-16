/**
 * Pulso — abertura e fechamento dos <dialog>.
 *
 * Usa o elemento nativo, que já entrega fechar com Esc, foco preso dentro e
 * fundo inerte. Reimplementar isso à mão é onde a acessibilidade costuma se
 * perder — aqui só falta ligar os botões.
 *
 *   <button data-abrir-modal="excluir-aluno">Excluir</button>
 *   <button data-fechar-modal>Cancelar</button>
 */

export function iniciar() {
    document.addEventListener('click', (evento) => {
        const abrir = evento.target.closest('[data-abrir-modal]');

        if (abrir) {
            evento.preventDefault();

            const alvo = document.getElementById('modal-' + abrir.dataset.abrirModal);

            alvo?.showModal();

            return;
        }

        const fechar = evento.target.closest('[data-fechar-modal]');

        if (fechar) {
            evento.preventDefault();
            fechar.closest('dialog')?.close('cancelar');
        }
    });

    // Clique no fundo escuro fecha. O <dialog> entrega o clique do backdrop
    // como se fosse no próprio dialog, então comparamos o alvo.
    document.addEventListener('click', (evento) => {
        if (evento.target.matches('dialog[data-modal]')) {
            evento.target.close('cancelar');
        }
    });
}
