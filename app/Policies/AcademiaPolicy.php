<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Academia;
use App\Models\User;

/**
 * Quem mexe nos dados e nas regras da academia.
 *
 * O super administrador NÃO aparece aqui. Ele muda a situação da academia
 * perante o Pulso — bloqueada, em aviso —, não o CNPJ nem a tolerância de
 * cobrança dela. Essas são decisões do cliente, e mexer nelas em nome dele
 * seria assumir uma responsabilidade que não é nossa.
 */
final class AcademiaPolicy
{
    public function view(User $usuario, Academia $academia): bool
    {
        return $usuario->can('academia.ver') && $usuario->academia_id === $academia->id;
    }

    /**
     * Configurar fica com o dono.
     *
     * Não é burocracia: o que se ajusta aqui muda o cabeçalho dos recibos, os
     * dias que a catraca tolera antes de bloquear e a idade mínima para
     * matricular. Erro nisso aparece no documento que o aluno leva para casa.
     */
    public function configurar(User $usuario, Academia $academia): bool
    {
        return $usuario->can('academia.configurar') && $usuario->academia_id === $academia->id;
    }
}
