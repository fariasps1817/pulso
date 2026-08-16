<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SituacaoMensalidade;
use App\Models\Mensalidade;
use App\Models\User;

/**
 * Quem mexe no dinheiro.
 *
 * Recepção recebe — é a rotina do balcão. Estornar fica com gerente e dono:
 * é a operação que faz dinheiro sumir do caixa, e quem recebeu por engano
 * chama quem tem essa alçada.
 */
final class MensalidadePolicy
{
    public function before(User $usuario): ?bool
    {
        return $usuario->ehSuperAdministrador() ? false : null;
    }

    public function viewAny(User $usuario): bool
    {
        return $usuario->can('mensalidade.ver');
    }

    public function view(User $usuario, Mensalidade $mensalidade): bool
    {
        return $usuario->can('mensalidade.ver') && $this->alcanca($usuario, $mensalidade);
    }

    /** Mensalidade cancelada ou já quitada não recebe pagamento novo. */
    public function receber(User $usuario, Mensalidade $mensalidade): bool
    {
        return $usuario->can('mensalidade.receber')
            && $this->alcanca($usuario, $mensalidade)
            && $mensalidade->situacao === SituacaoMensalidade::Aberta;
    }

    public function estornar(User $usuario, Mensalidade $mensalidade): bool
    {
        return $usuario->can('mensalidade.estornar') && $this->alcanca($usuario, $mensalidade);
    }

    private function alcanca(User $usuario, Mensalidade $mensalidade): bool
    {
        if ($usuario->academia_id === null || $usuario->academia_id !== $mensalidade->academia_id) {
            return false;
        }

        if ($usuario->acessa_todas_unidades) {
            return true;
        }

        return $usuario->unidadesAcessiveis()->contains('id', $mensalidade->unidade_id);
    }
}
