<?php

declare(strict_types=1);

namespace Database\Factories\Concerns;

use App\Models\Academia;
use App\Support\Academia\ContextoAcademia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Resolve o `academia_id` das factories a partir do contexto da requisição.
 *
 * Sem isto, cada factory criaria uma academia nova, o registro nasceria numa
 * academia diferente da que está em foco e a política de Row Level Security
 * recusaria a gravação — corretamente, aliás: é o WITH CHECK fazendo o
 * trabalho dele.
 *
 * Com o contexto definido, tudo o que a factory cria pertence à mesma
 * academia, que é o cenário real.
 */
trait UsaAcademiaDoContexto
{
    /** @return int|Factory<Academia> */
    protected function academiaDoContexto(): int|Factory
    {
        $contexto = app(ContextoAcademia::class);

        return $contexto->definida()
            ? (int) $contexto->id()
            : Academia::factory();
    }
}
