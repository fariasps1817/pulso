<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Sessão única: entrar num aparelho derruba os outros.
 *
 * Ligado por padrão, e o motivo é concreto: a senha da recepção circula. Sem
 * isto, uma senha emprestada "só para ver uma coisa" vira um segundo acesso
 * permanente, e ninguém percebe porque nada deixa de funcionar. Com isto, a
 * pessoa é derrubada e pergunta o porquê — o problema aparece.
 *
 * DEPENDE DE `SESSION_DRIVER=database`. Com sessão em arquivo ou cookie não há
 * tabela para varrer, e a opção simplesmente não teria efeito — silenciosamente,
 * que é o pior jeito de uma trava de segurança falhar. Por isso a verificação
 * explícita abaixo.
 */
final class EncerrarOutrasSessoes
{
    public function handle(Login $evento): void
    {
        $usuario = $evento->user;

        if (! $usuario instanceof User || ! $usuario->sessao_unica) {
            return;
        }

        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $usuario->id)
            ->where('id', '!=', Session::getId())
            ->delete();
    }
}
