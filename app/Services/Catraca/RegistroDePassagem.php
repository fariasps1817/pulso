<?php

declare(strict_types=1);

namespace App\Services\Catraca;

use Carbon\CarbonImmutable;

/**
 * Uma passagem como o aparelho a descreve — antes de o Pulso interpretar.
 *
 * O `status` chega 255 ("sem estado"): o equipamento não sabe se a pessoa
 * entrou ou saiu. Quem decide isso é o MotorDeAcesso, pela alternância.
 */
final readonly class RegistroDePassagem
{
    public function __construct(
        public string $pin,
        public CarbonImmutable $ocorreuEm,
        public int $status = 255,
        public int $metodo = 0,
    ) {}

    /**
     * Como a pessoa foi reconhecida.
     *
     * Os códigos vêm do protocolo. O 0 é a armadilha: parece "senha" e é
     * "identificação automática".
     */
    public function credencial(): string
    {
        return match ($this->metodo) {
            1 => 'digital',
            2 => 'matricula',
            3 => 'senha',
            4 => 'cartao',
            15 => 'facial',
            25 => 'palma',
            default => 'automatico',
        };
    }
}
