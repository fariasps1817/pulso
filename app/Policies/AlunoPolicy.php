<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Aluno;
use App\Models\User;

/**
 * Quem pode o quê com o cadastro de aluno.
 *
 * Confere a permissão E a academia. A checagem de academia é redundante em
 * relação ao Row Level Security — que já impediria a leitura — mas devolve
 * 403 em vez de 404, o que é a resposta correta e não deixa a tela dar erro
 * estranho quando alguém guarda um link antigo.
 *
 * Matriz completa em docs/dominio/README.md §4.2.1.
 */
final class AlunoPolicy
{
    /**
     * O super administrador não passa daqui.
     *
     * Ele opera só o plano de controle — academias, unidades, avisos e
     * usuários. Aluno, mensalidade e biometria estão fora do seu alcance por
     * decisão de projeto, e o RLS não abre exceção nem se esta política
     * abrisse.
     */
    public function before(User $usuario): ?bool
    {
        return $usuario->ehSuperAdministrador() ? false : null;
    }

    public function viewAny(User $usuario): bool
    {
        return $usuario->can('aluno.ver');
    }

    public function view(User $usuario, Aluno $aluno): bool
    {
        return $usuario->can('aluno.ver') && $this->mesmaAcademia($usuario, $aluno);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('aluno.criar');
    }

    public function update(User $usuario, Aluno $aluno): bool
    {
        return $usuario->can('aluno.editar') && $this->mesmaAcademia($usuario, $aluno);
    }

    public function delete(User $usuario, Aluno $aluno): bool
    {
        return $usuario->can('aluno.excluir') && $this->mesmaAcademia($usuario, $aluno);
    }

    private function mesmaAcademia(User $usuario, Aluno $aluno): bool
    {
        return $usuario->academia_id !== null
            && $usuario->academia_id === $aluno->academia_id;
    }
}
