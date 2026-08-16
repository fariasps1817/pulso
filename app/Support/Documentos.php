<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validação matemática de CPF e CNPJ.
 *
 * Confere os dígitos verificadores localmente: de graça, instantâneo e sem que
 * nenhum dado pessoal saia do sistema. Pega todo erro de digitação.
 *
 * O que NÃO faz: dizer se o CPF existe de fato ou a quem pertence — isso
 * exigiria serviço pago (Serpro ou revenda), custo por consulta, e a consulta
 * em si seria tratamento de dado pessoal a declarar no inventário de LGPD.
 * Decisão registrada em docs/dominio/README.md.
 */
final class Documentos
{
    public static function apenasDigitos(?string $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor) ?? '';
    }

    public static function cpfValido(?string $cpf): bool
    {
        $digitos = self::apenasDigitos($cpf);

        if (strlen($digitos) !== 11) {
            return false;
        }

        // 111.111.111-11 e afins passam no cálculo dos verificadores, mas não
        // existem. Recusar aqui evita o cadastro-lixo mais comum do balcão.
        if (preg_match('/^(\d)\1{10}$/', $digitos) === 1) {
            return false;
        }

        return self::digitoVerificadorCpf($digitos, 9) === (int) $digitos[9]
            && self::digitoVerificadorCpf($digitos, 10) === (int) $digitos[10];
    }

    public static function cnpjValido(?string $cnpj): bool
    {
        $digitos = self::apenasDigitos($cnpj);

        if (strlen($digitos) !== 14) {
            return false;
        }

        if (preg_match('/^(\d)\1{13}$/', $digitos) === 1) {
            return false;
        }

        return self::digitoVerificadorCnpj($digitos, 12) === (int) $digitos[12]
            && self::digitoVerificadorCnpj($digitos, 13) === (int) $digitos[13];
    }

    /** Formata para leitura: 08599608596 -> 085.996.085-96. */
    public static function formatarCpf(?string $cpf): string
    {
        $digitos = self::apenasDigitos($cpf);

        if (strlen($digitos) !== 11) {
            return (string) $cpf;
        }

        return substr($digitos, 0, 3).'.'.substr($digitos, 3, 3).'.'
            .substr($digitos, 6, 3).'-'.substr($digitos, 9, 2);
    }

    /** Formata para leitura: 12345678000199 -> 12.345.678/0001-99. */
    public static function formatarCnpj(?string $cnpj): string
    {
        $digitos = self::apenasDigitos($cnpj);

        if (strlen($digitos) !== 14) {
            return (string) $cnpj;
        }

        return substr($digitos, 0, 2).'.'.substr($digitos, 2, 3).'.'.substr($digitos, 5, 3)
            .'/'.substr($digitos, 8, 4).'-'.substr($digitos, 12, 2);
    }

    private static function digitoVerificadorCpf(string $digitos, int $ate): int
    {
        $soma = 0;
        $peso = $ate + 1;

        for ($i = 0; $i < $ate; $i++) {
            $soma += (int) $digitos[$i] * $peso--;
        }

        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }

    private static function digitoVerificadorCnpj(string $digitos, int $ate): int
    {
        // Pesos do CNPJ: começam em 5 (ou 6 para o segundo dígito) e voltam
        // a 9 depois de chegar a 2.
        $peso = $ate === 12 ? 5 : 6;
        $soma = 0;

        for ($i = 0; $i < $ate; $i++) {
            $soma += (int) $digitos[$i] * $peso;
            $peso = $peso === 2 ? 9 : $peso - 1;
        }

        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}
