<?php

declare(strict_types=1);

namespace App\Enums;

enum ResultadoAcesso: string
{
    case Liberado = 'liberado';
    case Bloqueado = 'bloqueado';
}
