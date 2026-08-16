<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\Academia\Papeis;

/**
 * Quem mexe no cadastro da equipe.
 *
 * Duas travas que não são sobre permissão nomeada, e sim sobre não deixar o
 * sistema se trancar por dentro:
 *
 *   1. Ninguém cria nem promove alguém acima de si (ver Support\Academia\Papeis).
 *   2. Ninguém se desativa nem se exclui. Uma academia com um dono só, que
 *      clica em "desativar" na própria conta por engano, fica sem ninguém que
 *      possa reverter — e a saída seria mexer no banco.
 */
final class UserPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('usuario.ver');
    }

    public function view(User $usuario, User $alvo): bool
    {
        return $usuario->can('usuario.ver') && $this->mesmaAcademia($usuario, $alvo);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('usuario.criar') && Papeis::atribuiveisPor($usuario) !== [];
    }

    public function update(User $usuario, User $alvo): bool
    {
        return $usuario->can('usuario.editar')
            && $this->mesmaAcademia($usuario, $alvo)
            && Papeis::podeGerenciar($usuario, $alvo);
    }

    /**
     * Desativar é o "excluir" desta tela.
     *
     * Usuário não some do banco: ele responde por mensalidade recebida e por
     * biometria cadastrada, e apagar a linha deixaria esse histórico órfão.
     */
    public function desativar(User $usuario, User $alvo): bool
    {
        return $this->update($usuario, $alvo);
    }

    /**
     * Gerar uma senha nova para quem esqueceu a sua.
     *
     * Fica com quem já pode editar o usuário: dá acesso à conta da pessoa até
     * ela trocar, e portanto não é operação de balcão.
     */
    public function redefinirSenha(User $usuario, User $alvo): bool
    {
        return $this->update($usuario, $alvo);
    }

    private function mesmaAcademia(User $usuario, User $alvo): bool
    {
        return $usuario->academia_id !== null
            && $usuario->academia_id === $alvo->academia_id;
    }
}
