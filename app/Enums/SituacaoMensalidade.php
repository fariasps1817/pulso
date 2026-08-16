<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Situação da mensalidade no banco.
 *
 * "Vencida" NÃO está aqui de propósito: é derivada de `aberta` com vencimento
 * no passado. Guardar como estado exigiria uma rotina diária virando a chave,
 * e no dia em que ela falhasse o Radar mentiria para o dono.
 */
enum SituacaoMensalidade: string
{
    case Aberta = 'aberta';
    case Paga = 'paga';
    case Cancelada = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Aberta => 'Em aberto',
            self::Paga => 'Paga',
            self::Cancelada => 'Cancelada',
        };
    }
}
