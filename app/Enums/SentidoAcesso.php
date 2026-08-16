<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Entrada ou saída — deduzido, nunca lido.
 *
 * A catraca é de contato seco: gira e não conta para que lado. O aparelho
 * biométrico manda `Status=255` ("sem estado") em todo registro. Então o
 * sentido sai da alternância com a passagem anterior do aluno, e não de
 * nenhum sinal do equipamento.
 */
enum SentidoAcesso: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';

    public function rotulo(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Saida => 'Saída',
        };
    }

    public function oposto(): self
    {
        return match ($this) {
            self::Entrada => self::Saida,
            self::Saida => self::Entrada,
        };
    }
}
