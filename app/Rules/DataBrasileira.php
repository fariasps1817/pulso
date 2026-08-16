<?php

declare(strict_types=1);

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

/**
 * Data no formato dd/mm/aaaa, que é como se digita no Brasil.
 *
 * Os campos de data do sistema são texto com máscara, e não seletor nativo:
 * no celular, escolher uma data de nascimento de 1974 no calendário exige
 * rolar dezenas de telas, enquanto digitar leva dois segundos.
 *
 * `createFromFormat` com `!` zera hora, minuto e segundo — sem isso, a data
 * herdaria o horário atual e a comparação com "hoje" ficaria instável.
 */
final class DataBrasileira implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (self::converter((string) $value) === null) {
            $fail('Informe uma data no formato dd/mm/aaaa.');
        }
    }

    /** Devolve a data, ou nulo quando o texto não é uma data válida. */
    public static function converter(?string $texto): ?CarbonImmutable
    {
        if ($texto === null || trim($texto) === '') {
            return null;
        }

        try {
            $data = CarbonImmutable::createFromFormat('!d/m/Y', trim($texto));
        } catch (Throwable) {
            return null;
        }

        // 31/02/2026 não estoura: o Carbon "rola" para 03/03. Comparar de
        // volta é o que denuncia a data inexistente.
        return $data->format('d/m/Y') === trim($texto) ? $data : null;
    }
}
