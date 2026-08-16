<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Academia bloqueada: a equipe não entra.
 *
 * É o que dá efeito ao bloqueio do super administrador — sem isto, mudar a
 * situação seria trocar uma palavra numa tabela.
 *
 * MAS A CATRACA CONTINUA GIRANDO. As rotas do aparelho biométrico não passam
 * por aqui, e é deliberado: deixar aluno na porta por briga comercial entre a
 * academia e o Pulso puniria quem não tem nada com isso. Quem pagou a
 * mensalidade dele treina.
 *
 * O usuário é desconectado, e não só barrado: manter a sessão viva faria toda
 * navegação bater neste muro sem explicação.
 */
final class ExigirAcademiaAtiva
{
    public function handle(Request $requisicao, Closure $seguir): Response
    {
        $usuario = Auth::user();

        if ($usuario === null || $usuario->academia_id === null) {
            return $seguir($requisicao);
        }

        // A própria conta desativada pelo gestor cai na mesma porta.
        $contaAtiva = (bool) $usuario->ativo;
        $academiaAtiva = $usuario->academia?->situacao->permiteAcessoAoSistema() ?? false;

        if ($contaAtiva && $academiaAtiva) {
            return $seguir($requisicao);
        }

        Auth::guard('web')->logout();
        $requisicao->session()->invalidate();
        $requisicao->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => $contaAtiva
                ? 'O acesso desta academia está suspenso. Fale com o Pulso.'
                : 'Seu acesso foi desativado. Fale com a gerência da academia.',
        ]);
    }
}
