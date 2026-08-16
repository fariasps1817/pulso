<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Situação da academia perante o Pulso. Só o super administrador altera.
 */
enum SituacaoAcademia: string
{
    case Ativa = 'ativa';
    case EmAviso = 'em_aviso';
    case Bloqueada = 'bloqueada';
    case Cancelada = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Ativa => 'Ativa',
            self::EmAviso => 'Em aviso',
            self::Bloqueada => 'Bloqueada',
            self::Cancelada => 'Cancelada',
        };
    }

    /** A equipe da academia consegue entrar no sistema? */
    public function permiteAcessoAoSistema(): bool
    {
        return $this === self::Ativa || $this === self::EmAviso;
    }

    /**
     * A catraca continua liberando quem está em dia?
     *
     * SEMPRE — inclusive com a academia bloqueada. Deixar aluno na porta por
     * briga comercial entre a academia e o Pulso puniria quem não tem nada
     * com isso.
     */
    public function permiteAcessoDeAluno(): bool
    {
        return $this !== self::Cancelada;
    }
}
