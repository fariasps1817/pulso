<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enquanto a senha for temporária, não se chega a lugar nenhum.
 *
 * A senha temporária existe por alguns minutos e é conhecida por duas
 * pessoas: quem cadastrou e quem vai usar. Este middleware é o que fecha essa
 * janela — sem ele, "depois eu troco" viraria uma senha que o gestor conhece
 * para sempre.
 *
 * Sair continua liberado: prender alguém numa tela sem saída é pior do que o
 * problema que se está resolvendo.
 */
final class ExigirTrocaDeSenha
{
    /** @var list<string> */
    private const LIBERADAS = ['senha', 'logout', 'livewire/*'];

    public function handle(Request $requisicao, Closure $seguir): Response
    {
        $usuario = Auth::user();

        if ($usuario === null || ! $usuario->deve_trocar_senha) {
            return $seguir($requisicao);
        }

        /*
         * `livewire/*` fica de fora porque é por ele que a própria tela de
         * troca funciona. A tela em si valida quem pode fazer o quê — o
         * middleware aqui cuida da navegação, não da autorização.
         */
        if ($requisicao->is(...self::LIBERADAS)) {
            return $seguir($requisicao);
        }

        return redirect()->route('senha.trocar');
    }
}
