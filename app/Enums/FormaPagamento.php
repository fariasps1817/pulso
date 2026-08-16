<?php

declare(strict_types=1);

namespace App\Enums;

enum FormaPagamento: string
{
    case Dinheiro = 'dinheiro';
    case Pix = 'pix';
    case CartaoCredito = 'cartao_credito';
    case CartaoDebito = 'cartao_debito';
    case Transferencia = 'transferencia';

    public function rotulo(): string
    {
        return match ($this) {
            self::Dinheiro => 'Dinheiro',
            self::Pix => 'Pix',
            self::CartaoCredito => 'Cartão de crédito',
            self::CartaoDebito => 'Cartão de débito',
            self::Transferencia => 'Transferência',
        };
    }
}
