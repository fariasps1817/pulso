<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoCredencial: string
{
    case Facial = 'facial';
    case Digital = 'digital';
    case Cartao = 'cartao';

    public function rotulo(): string
    {
        return match ($this) {
            self::Facial => 'Reconhecimento facial',
            self::Digital => 'Digital',
            self::Cartao => 'Cartão',
        };
    }

    /**
     * É dado sensível pela LGPD (art. 11)?
     *
     * Se sim: exige consentimento específico e separado do contrato, o
     * template é guardado cifrado, a imagem nunca é armazenada, e tudo é
     * apagado ao cancelar a matrícula.
     */
    public function ehBiometrico(): bool
    {
        return $this !== self::Cartao;
    }
}
