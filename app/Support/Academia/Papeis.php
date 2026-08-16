<?php

declare(strict_types=1);

namespace App\Support\Academia;

use App\Models\User;

/**
 * Quem pode criar quem.
 *
 * A regra que sustenta a lista abaixo: NINGUÉM CRIA ALGUÉM ACIMA DE SI. Sem
 * isso, um gerente cadastraria um dono, entraria com ele e teria a rede
 * inteira — sem invadir nada, só usando o formulário.
 *
 * Vale também para editar: mudar o papel de alguém é a mesma coisa que criar
 * naquele papel, por um caminho diferente.
 */
final class Papeis
{
    /** Do mais alto para o mais baixo. A ordem é a hierarquia. */
    public const TODOS = ['dono', 'gerente', 'recepcao', 'professor'];

    /** @var array<string, string> */
    public const ROTULOS = [
        'dono' => 'Dono',
        'gerente' => 'Gerente',
        'recepcao' => 'Recepção',
        'professor' => 'Professor',
    ];

    public static function rotulo(?string $papel): string
    {
        return self::ROTULOS[$papel] ?? '—';
    }

    /**
     * Os papéis que este usuário pode atribuir a outra pessoa.
     *
     * O dono atribui qualquer um, inclusive outro dono — uma academia com um
     * sócio só, e ele de férias, é uma academia parada. O gerente atribui
     * apenas abaixo dele.
     *
     * @return list<string>
     */
    public static function atribuiveisPor(User $usuario): array
    {
        if ($usuario->hasRole('dono')) {
            return self::TODOS;
        }

        if ($usuario->hasRole('gerente')) {
            return ['recepcao', 'professor'];
        }

        return [];
    }

    public static function podeAtribuir(User $usuario, string $papel): bool
    {
        return in_array($papel, self::atribuiveisPor($usuario), true);
    }

    /**
     * Este usuário pode mexer no cadastro daquele outro?
     *
     * Além da hierarquia, há a regra do espelho: ninguém edita a própria
     * conta por esta tela. Rebaixar-se por engano trancaria a academia, e
     * dados pessoais e senha se mudam no perfil.
     */
    public static function podeGerenciar(User $usuario, User $alvo): bool
    {
        if ($usuario->id === $alvo->id) {
            return false;
        }

        return self::podeAtribuir($usuario, (string) $alvo->getRoleNames()->first());
    }
}
