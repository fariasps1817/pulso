<?php

declare(strict_types=1);

namespace App\Enums;

enum SituacaoMatricula: string
{
    case Experiencia = 'experiencia';
    case Ativa = 'ativa';
    case Suspensa = 'suspensa';
    case Encerrada = 'encerrada';
    case Cancelada = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Experiencia => 'Em experiência',
            self::Ativa => 'Ativa',
            self::Suspensa => 'Trancada',
            self::Encerrada => 'Encerrada',
            self::Cancelada => 'Cancelada',
        };
    }

    /** Gera mensalidade todo mês? Trancada e encerrada não geram. */
    public function geraMensalidade(): bool
    {
        return $this === self::Ativa;
    }

    /** Deixa o aluno passar na catraca, se estiver em dia? */
    public function permiteAcesso(): bool
    {
        return $this === self::Ativa || $this === self::Experiencia;
    }
}
