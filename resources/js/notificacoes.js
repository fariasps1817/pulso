/**
 * Pulso — avisos rápidos ("Pagamento registrado").
 *
 * O guia de marca manda o botão dizer o que acontece e o aviso responder que
 * aconteceu: "Registrar pagamento" → "Pagamento registrado". Nunca "Sucesso!".
 */

const DURACAO_PADRAO = 4000;

/**
 * Registra o componente Alpine dos avisos. Chamado por painel.js antes de
 * Livewire.start().
 *
 * @param {object} Alpine
 */
export function registrar(Alpine) {
    let proximoId = 1;

    Alpine.data('notificacoesPulso', () => ({
        avisos: [],

        init() {
            document.addEventListener('pulso:aviso', (evento) => {
                this.adicionar(evento.detail);
            });
        },

        adicionar({ texto, tipo = 'sucesso', duracao = DURACAO_PADRAO }) {
            const id = proximoId++;

            this.avisos.push({ id, texto, tipo });

            // Erro não some sozinho: quem precisa ler uma falha costuma estar
            // olhando outra coisa quando ela aparece.
            if (tipo !== 'erro') {
                setTimeout(() => this.remover(id), duracao);
            }
        },

        remover(id) {
            this.avisos = this.avisos.filter((aviso) => aviso.id !== id);
        },
    }));

    window.pulso = window.pulso ?? {};

    window.pulso.avisar = (texto, tipo = 'sucesso', duracao = DURACAO_PADRAO) => {
        document.dispatchEvent(new CustomEvent('pulso:aviso', {
            detail: { texto, tipo, duracao },
        }));
    };
}
