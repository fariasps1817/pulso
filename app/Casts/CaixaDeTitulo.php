<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Nomes;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Grava nomes próprios em caixa de título brasileira.
 *
 * "JOSE MARIA DA SILVA", "jose maria da silva" e "joSE MAria dA siLVA" viram
 * todos "Jose Maria da Silva".
 *
 * A normalização acontece AO GRAVAR, não ao exibir: se ficasse na exibição, a
 * lista ordenada misturaria "SILVA" e "Silva", e buscar por "Silva" não
 * acharia quem foi digitado em caixa alta.
 *
 * É cast, e não mutator em cada model, porque a regra é a mesma em aluno,
 * profissional, plano, unidade, academia e usuário — seis lugares onde ela
 * poderia divergir com o tempo.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class CaixaDeTitulo implements CastsAttributes
{
    public function get(Model $model, string $chave, mixed $valor, array $atributos): ?string
    {
        return $valor === null ? null : (string) $valor;
    }

    public function set(Model $model, string $chave, mixed $valor, array $atributos): ?string
    {
        return $valor === null ? null : Nomes::caixaDeTitulo((string) $valor);
    }
}
