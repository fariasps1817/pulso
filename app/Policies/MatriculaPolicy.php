<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Matricula;
use App\Models\User;

/**
 * Quem mexe no vínculo entre aluno e plano.
 *
 * Recepção matricula e converte experiência — é a rotina do balcão. Encerrar
 * fica com gerente e dono: é a decisão que faz o aluno parar de gerar receita,
 * e desfazer depois exige recriar a matrícula com outra vigência.
 */
final class MatriculaPolicy
{
    public function before(User $usuario): ?bool
    {
        return $usuario->ehSuperAdministrador() ? false : null;
    }

    public function viewAny(User $usuario): bool
    {
        return $usuario->can('matricula.ver');
    }

    public function view(User $usuario, Matricula $matricula): bool
    {
        return $usuario->can('matricula.ver') && $this->alcanca($usuario, $matricula);
    }

    public function create(User $usuario): bool
    {
        return $usuario->can('matricula.criar');
    }

    public function update(User $usuario, Matricula $matricula): bool
    {
        return $usuario->can('matricula.editar') && $this->alcanca($usuario, $matricula);
    }

    public function encerrar(User $usuario, Matricula $matricula): bool
    {
        return $usuario->can('matricula.encerrar') && $this->alcanca($usuario, $matricula);
    }

    /**
     * Quem vê valor.
     *
     * Amarrado a `mensalidade.ver`, e não ao acesso à matrícula: o professor
     * precisa saber que o aluno está matriculado e em qual plano, mas não
     * quanto ele paga. A regra do documento de domínio é literal — professor
     * não vê dinheiro.
     */
    public function verValores(User $usuario): bool
    {
        return $usuario->can('mensalidade.ver');
    }

    /**
     * Alcança a matrícula? Academia e, quando o usuário é limitado a
     * unidades, também a unidade.
     */
    private function alcanca(User $usuario, Matricula $matricula): bool
    {
        if ($usuario->academia_id === null || $usuario->academia_id !== $matricula->academia_id) {
            return false;
        }

        if ($usuario->acessa_todas_unidades) {
            return true;
        }

        return $usuario->unidadesAcessiveis()->contains('id', $matricula->unidade_id);
    }
}
