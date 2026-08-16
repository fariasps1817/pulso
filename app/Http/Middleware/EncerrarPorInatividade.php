<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encerra a sessão parada.
 *
 * O caso real: a recepção fica num computador de balcão, num salão onde
 * circulam cem pessoas por dia. Sessão aberta ali é acesso à ficha de todo
 * mundo para quem passar e mexer no mouse.
 *
 * POR QUE NÃO O `SESSION_LIFETIME` DO LARAVEL
 *
 * Ele expira por tempo desde a criação da sessão, e é global. Aqui o prazo é
 * de INATIVIDADE — quem está usando não é interrompido no meio do atendimento
 * — e é por usuário: o computador do balcão pede um prazo curto, o do
 * escritório da direção não.
 *
 * O relógio fica na sessão, não no banco: gravar a cada requisição custaria
 * uma escrita por clique, e um `updated_at` que muda o tempo todo não vale
 * nada como informação.
 */
final class EncerrarPorInatividade
{
    /** Quando o usuário não define o seu. */
    private const MINUTOS_PADRAO = 30;

    private const CHAVE = 'pulso.ultima_atividade';

    public function handle(Request $requisicao, Closure $seguir): Response
    {
        $usuario = Auth::user();

        if ($usuario === null) {
            return $seguir($requisicao);
        }

        $limite = $usuario->minutos_inatividade ?? self::MINUTOS_PADRAO;

        // Zero desliga o encerramento — escolha legítima para a máquina
        // trancada da sala da direção.
        if ($limite <= 0) {
            return $seguir($requisicao);
        }

        $ultima = $requisicao->session()->get(self::CHAVE);

        if ($ultima !== null && CarbonImmutable::parse($ultima)->addMinutes($limite)->isPast()) {
            Auth::guard('web')->logout();
            $requisicao->session()->invalidate();
            $requisicao->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => "Sua sessão foi encerrada por {$limite} minutos sem uso. Entre de novo.",
            ]);
        }

        $requisicao->session()->put(self::CHAVE, CarbonImmutable::now()->toIso8601String());

        return $seguir($requisicao);
    }
}
