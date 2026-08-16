<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formatacao de valores para leitura humana, no padrao brasileiro.
 *
 * Fica em Support (e nao em helpers soltos) para poder ser testado e para que
 * a regra de exibicao viva num lugar so — o guia de marca exige numero,
 * dinheiro e data sempre no mesmo formato em todo o sistema.
 */
final class Formato
{
    /**
     * Telefone brasileiro para leitura: +55 (85) 99608-5960.
     *
     * Aceita a entrada com ou sem codigo do pais. Se o formato nao for
     * reconhecido, devolve o que entrou — exibir algo cru e melhor do que
     * exibir errado.
     */
    public static function telefone(string $numero): string
    {
        $digitos = preg_replace('/\D/', '', $numero) ?? '';

        // Com codigo do pais (55) + DDD + 9 digitos.
        if (preg_match('/^55(\d{2})(\d{5})(\d{4})$/', $digitos, $partes)) {
            return "+55 ({$partes[1]}) {$partes[2]}-{$partes[3]}";
        }

        // Com codigo do pais (55) + DDD + 8 digitos (fixo).
        if (preg_match('/^55(\d{2})(\d{4})(\d{4})$/', $digitos, $partes)) {
            return "+55 ({$partes[1]}) {$partes[2]}-{$partes[3]}";
        }

        // Sem codigo do pais.
        if (preg_match('/^(\d{2})(\d{4,5})(\d{4})$/', $digitos, $partes)) {
            return "({$partes[1]}) {$partes[2]}-{$partes[3]}";
        }

        return $numero;
    }

    /** Valor monetario: 1234.5 -> "R$ 1.234,50". */
    public static function dinheiro(int|float|string $valor): string
    {
        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }

    /** Valor sem simbolo, para preencher campo de formulario: 1234.5 -> "1.234,50". */
    public static function numeroDecimal(int|float|string|null $valor): string
    {
        return $valor === null ? '' : number_format((float) $valor, 2, ',', '.');
    }

    /**
     * Caminho inverso: "1.234,56" -> "1234.56".
     *
     * O campo de dinheiro digita da direita para a esquerda e entrega texto no
     * formato brasileiro. Mandar isso direto para uma coluna numeric daria
     * erro de conversao — ou, pior, gravaria 1.23 no lugar de 1234.56.
     */
    public static function decimalDoFormulario(?string $valor): ?string
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $valor) ?? '';

        if ($digitos === '') {
            return null;
        }

        // O campo sempre entrega duas casas decimais.
        return bcdiv($digitos, '100', 2);
    }
}
