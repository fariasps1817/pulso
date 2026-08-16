<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Duas áreas, dois mundos, e ninguém atravessa por engano.
 *
 * O super administrador (`academia_id` nulo) opera o SaaS: cadastra academias,
 * bloqueia quem não pagou, publica avisos. Ele NÃO tem academia, então o
 * painel comum não faz sentido para ele — a barra de unidade não teria o que
 * mostrar e toda consulta voltaria vazia, porque as políticas de Row Level
 * Security não casam com contexto nenhum.
 *
 * Na direção contrária, a academia não entra na administração do SaaS de
 * jeito nenhum. E a checagem aqui é por `academia_id` nulo, não por um papel:
 * papel se atribui pela tela de usuários, e nenhum gestor pode se promover a
 * dono do Pulso por lá.
 */
final class SepararSuperAdministrador
{
    /** Telas que servem aos dois. */
    private const COMUNS = ['senha', 'logout', 'livewire/*', 'preferencias'];

    public function handle(Request $requisicao, Closure $seguir): Response
    {
        $usuario = Auth::user();

        if ($usuario === null || $requisicao->is(...self::COMUNS)) {
            return $seguir($requisicao);
        }

        $ehSuperAdministrador = $usuario->academia_id === null;
        $areaDoSaas = $requisicao->is('administracao', 'administracao/*');

        if ($ehSuperAdministrador && ! $areaDoSaas) {
            return redirect()->route('administracao.academias.lista');
        }

        abort_if(! $ehSuperAdministrador && $areaDoSaas, 403);

        return $seguir($requisicao);
    }
}
