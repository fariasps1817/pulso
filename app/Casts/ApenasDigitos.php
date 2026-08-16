<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Documentos;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Guarda CPF, CNPJ, telefone e CEP como SÓ DÍGITOS.
 *
 * A pontuação é da tela. Gravar "085.996.085-96" obrigaria a limpar a máscara
 * em toda busca, todo relatório e toda integração — e bastaria um lugar
 * esquecido para o aluno "sumir" da pesquisa.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class ApenasDigitos implements CastsAttributes
{
    public function get(Model $model, string $chave, mixed $valor, array $atributos): ?string
    {
        return $valor === null ? null : (string) $valor;
    }

    public function set(Model $model, string $chave, mixed $valor, array $atributos): ?string
    {
        if ($valor === null) {
            return null;
        }

        $digitos = Documentos::apenasDigitos((string) $valor);

        // String vazia vira nulo: campo opcional deixado em branco não deve
        // ocupar o índice único nem aparecer como "preenchido".
        return $digitos === '' ? null : $digitos;
    }
}
