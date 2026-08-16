/**
 * Pulso — máscaras de campo.
 *
 * A equipe opera no balcão, muitas vezes no celular, com o aluno esperando.
 * Toda máscara vem acompanhada de duas coisas que não são enfeite:
 *
 *   1. `inputmode="numeric"` — faz subir o teclado numérico no celular, em vez
 *      do teclado completo, onde acertar um dígito exige mira.
 *   2. filtro de tecla — caractere que não é dígito simplesmente não entra,
 *      nem digitado nem colado. Aceitar e limpar depois faz o campo "piscar".
 *
 * O que vai para o banco são só os dígitos. A máscara é da tela: gravar
 * "085.996.085-96" obrigaria a limpar pontuação em toda busca, todo relatório
 * e toda integração.
 */

export const PADROES = {
    cpf: '999.999.999-99',
    cnpj: '99.999.999/9999-99',
    celular: '(99) 99999-9999',
    telefone: '(99) 9999-9999',
    cep: '99999-999',
    data: '99/99/9999',
};

/** Quantos dígitos cada formato aceita — vira o `maxlength` do campo. */
export const TAMANHOS = {
    cpf: 14,
    cnpj: 18,
    celular: 15,
    telefone: 14,
    cep: 9,
    data: 10,
};

/** Deixa só os dígitos: é isso que segue para o servidor. */
export function apenasDigitos(valor) {
    return String(valor ?? '').replace(/\D+/g, '');
}

/**
 * Dinheiro no padrão brasileiro, digitado da direita para a esquerda —
 * o jeito que quem opera caixa espera. Digitar "12990" mostra "129,90".
 */
export function formatarDinheiro(valor) {
    const centavos = apenasDigitos(valor);

    if (centavos === '') {
        return '';
    }

    const numero = (parseInt(centavos, 10) / 100).toFixed(2);
    const [inteiro, decimal] = numero.split('.');

    return inteiro.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + decimal;
}

/**
 * Bloqueia tecla que não seja dígito, inclusive no colar.
 *
 * Não interfere em Tab, setas, Backspace nem em atalhos com Ctrl/Cmd — travar
 * esses transformaria o campo numa armadilha para quem navega pelo teclado.
 */
function somenteDigitos(campo) {
    campo.addEventListener('keydown', (evento) => {
        if (evento.ctrlKey || evento.metaKey || evento.altKey) {
            return;
        }

        if (evento.key.length > 1) {
            return; // Tab, Backspace, setas, Delete...
        }

        if (! /^\d$/.test(evento.key)) {
            evento.preventDefault();
        }
    });

    campo.addEventListener('paste', (evento) => {
        const colado = (evento.clipboardData ?? window.clipboardData).getData('text');

        if (/\D/.test(colado)) {
            evento.preventDefault();

            const digitos = apenasDigitos(colado);

            if (digitos !== '') {
                document.execCommand('insertText', false, digitos);
            }
        }
    });
}

/**
 * Registra o que depende do Alpine. Chamado por painel.js ANTES de
 * Livewire.start() — é essa ordem que faz as diretivas existirem quando o
 * Alpine varre a página.
 *
 * @param {object} Alpine
 */
export function registrar(Alpine) {
    /*
     * Campo de dinheiro. Separado do x-mask porque dinheiro se digita da
     * direita para a esquerda ("12990" vira "129,90"), e a máscara posicional
     * do Alpine assume o contrário.
     */
    Alpine.directive('dinheiro', (campo) => {
        somenteDigitos(campo);

        const aplicar = () => {
            campo.value = formatarDinheiro(campo.value);
        };

        campo.addEventListener('input', aplicar);
        aplicar();
    });

    // Campos marcados como numéricos ganham o filtro de tecla, inclusive os
    // que aparecerem depois (Livewire trocando pedaço da tela).
    const aplicarFiltro = (raiz) => {
        raiz.querySelectorAll('[data-somente-digitos]:not([data-digitos-ok])').forEach((campo) => {
            campo.dataset.digitosOk = '1';
            somenteDigitos(campo);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => aplicarFiltro(document));
    } else {
        aplicarFiltro(document);
    }

    document.addEventListener('livewire:navigated', () => aplicarFiltro(document));
    document.addEventListener('livewire:update', () => aplicarFiltro(document));
}
