/**
 * Pulso — troca de tema (claro / escuro / sistema).
 *
 * Sao tres estados, nao dois:
 *   claro / escuro  -> marcam data-theme no <html>
 *   sistema         -> NAO marca nada e se resolve por prefers-color-scheme
 *
 * A aplicacao inicial acontece em um script sincrono no <head> (ver o layout
 * base), antes da primeira pintura — senao a tela pisca branca em quem usa o
 * tema escuro. Este modulo cuida apenas da troca depois que a pagina carregou.
 *
 * Para visitante, a preferencia mora no localStorage. Para usuario autenticado
 * ela e gravada no perfil, porque a equipe alterna entre o balcao e o celular.
 */

export const CHAVE = 'pulso.tema';
export const OPCOES = ['claro', 'escuro', 'sistema'];

export function lerTema() {
    const salvo = localStorage.getItem(CHAVE);

    return OPCOES.includes(salvo) ? salvo : 'sistema';
}

export function aplicarTema(tema) {
    const raiz = document.documentElement;

    if (tema === 'sistema') {
        raiz.removeAttribute('data-theme');
    } else {
        raiz.setAttribute('data-theme', tema === 'escuro' ? 'dark' : 'light');
    }

    raiz.dataset.temaEscolhido = tema;
}

export function definirTema(tema) {
    if (! OPCOES.includes(tema)) {
        return;
    }

    localStorage.setItem(CHAVE, tema);
    aplicarTema(tema);

    document.dispatchEvent(new CustomEvent('pulso:tema-alterado', { detail: { tema } }));
}

/** Alterna em ciclo: claro -> escuro -> sistema -> claro. */
export function alternarTema() {
    const atual = lerTema();
    const proximo = OPCOES[(OPCOES.indexOf(atual) + 1) % OPCOES.length];

    definirTema(proximo);

    return proximo;
}

export function iniciar() {
    aplicarTema(lerTema());

    document.addEventListener('click', (evento) => {
        const gatilho = evento.target.closest('[data-tema-alternar]');

        if (! gatilho) {
            return;
        }

        evento.preventDefault();

        const tema = alternarTema();

        gatilho.setAttribute('aria-label', rotuloDe(tema));
        gatilho.setAttribute('title', rotuloDe(tema));
    });
}

export function rotuloDe(tema) {
    return {
        claro: 'Tema claro — clique para o escuro',
        escuro: 'Tema escuro — clique para acompanhar o sistema',
        sistema: 'Acompanhando o sistema — clique para o claro',
    }[tema];
}
