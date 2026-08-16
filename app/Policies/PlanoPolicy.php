<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Plano;
use App\Models\User;

/**
 * Quem mexe no que a academia vende.
 *
 * Recepção e gerente CONSULTAM os planos — precisam para matricular —, mas
 * quem define preço é o dono. Errar o valor de um plano reajusta o caixa
 * inteiro sem ninguém perceber na hora.
 */
final class PlanoPolicy
{
    public function before(User $usuario): ?bool
    {
        return $usuario->ehSuperAdministrador() ? false : null;
    }

    public function viewAny(User $usuario): bool
    {
        return $usuario->can('plano.ver');
    }

    public function view(User $usuario, Plano $plano): bool
    {
        return $usuario->can('plano.ver') && $this->mesmaAcademia($usuario, $plano);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('plano.criar');
    }

    public function update(User $usuario, Plano $plano): bool
    {
        return $usuario->can('plano.editar') && $this->mesmaAcademia($usuario, $plano);
    }

    /**
     * Plano com matrícula não se exclui: as matrículas antigas apontam para
     * ele e o histórico precisa saber o que foi contratado. Desativa-se.
     */
    public function delete(User $usuario, Plano $plano): bool
    {
        return $usuario->can('plano.excluir')
            && $this->mesmaAcademia($usuario, $plano)
            && $plano->matriculas()->doesntExist();
    }

    private function mesmaAcademia(User $usuario, Plano $plano): bool
    {
        return $usuario->academia_id !== null
            && $usuario->academia_id === $plano->academia_id;
    }
}
