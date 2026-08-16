<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Documentos;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CPF com dígitos verificadores conferidos.
 *
 * Validação matemática apenas: de graça, instantânea e sem que nenhum dado
 * pessoal saia do sistema. Pega todo erro de digitação, que é o problema real
 * do balcão. Não diz se o CPF existe nem a quem pertence — isso exigiria
 * serviço pago e entraria no inventário de LGPD.
 */
final class CpfValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return; // Obrigatoriedade é assunto da regra `required`.
        }

        if (! Documentos::cpfValido((string) $value)) {
            $fail('Confira o CPF digitado.');
        }
    }
}
