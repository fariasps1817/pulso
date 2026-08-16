<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoMatricula: string
{
    case Experiencia = 'experiencia';
    case Regular = 'regular';

    public function rotulo(): string
    {
        return match ($this) {
            self::Experiencia => 'Experiência',
            self::Regular => 'Regular',
        };
    }
}
