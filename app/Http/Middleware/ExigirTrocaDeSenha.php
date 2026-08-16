<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Http\RequisicaoDoLivewire;
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
    private const LIBERADAS = ['senha', 'logout'];

    public function handle(Request $requisicao, Closure $seguir): Response
    {
        $usuario = Auth::user();

        if ($usuario === null || ! $usuario->deve_trocar_senha) {
            return $seguir($requisicao);
        }

        /*
         * A chamada interna do Livewire passa: é por ela que a PRÓPRIA tela de
         * troca funciona. Desviá-la impedia o formulário de concluir — ou
         * seja, nenhum usuário novo conseguia definir a senha, e portanto
         * nenhum conseguia entrar no sistema.
         *
         * Isso não abre porta: a tela de destino é que valida quem pode o quê.
         * O middleware aqui cuida de navegação, não de autorização.
         */
        if (RequisicaoDoLivewire::ehInterna($requisicao) || $requisicao->is(...self::LIBERADAS)) {
            return $seguir($requisicao);
        }

        return redirect()->route('senha.trocar');
    }
}
