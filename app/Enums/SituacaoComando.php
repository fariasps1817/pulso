<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * O ciclo de um comando na fila do aparelho.
 *
 * `Entregue` é o estado que evita a perda silenciosa: o comando saiu na
 * resposta de um polling, mas ainda não se sabe se foi aplicado. Passado o
 * prazo sem confirmação, ele volta para `Pendente`.
 */
enum SituacaoComando: string
{
    case Pendente = 'pendente';
    case Entregue = 'entregue';
    case Confirmado = 'confirmado';
    case Falhou = 'falhou';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Na fila',
            self::Entregue => 'Enviado, aguardando o aparelho',
            self::Confirmado => 'Aplicado',
            self::Falhou => 'Recusado pelo aparelho',
        };
    }
}
