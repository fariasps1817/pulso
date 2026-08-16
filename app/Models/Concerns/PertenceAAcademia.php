<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Academia;
use App\Support\Academia\ContextoAcademia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;

/**
 * Todo model de domínio usa este trait.
 *
 * Faz duas coisas:
 *
 *   1. filtra automaticamente por academia em toda consulta;
 *   2. preenche academia_id ao criar, para que ninguém precise lembrar.
 *
 * O filtro daqui NÃO é a barreira de segurança — é conveniência e desempenho.
 * A barreira é a política de Row Level Security no PostgreSQL, que continua
 * valendo mesmo se este trait for esquecido num model novo. Ver a migration
 * 2026_08_16_001900_ativa_row_level_security.
 */
trait PertenceAAcademia
{
    public static function bootPertenceAAcademia(): void
    {
        static::addGlobalScope(new class implements Scope
        {
            public function apply(Builder $consulta, Model $model): void
            {
                $contexto = app(ContextoAcademia::class);

                /*
                 * Sem academia definida (console, filas, testes de
                 * infraestrutura) o filtro não entra. Não é brecha: nessas
                 * situações o RLS já devolve zero linha pela conexão da
                 * aplicação, e a conexão de manutenção é usada de propósito
                 * quando se precisa atravessar academias.
                 */
                if (! $contexto->definida()) {
                    return;
                }

                $consulta->where($model->qualifyColumn('academia_id'), $contexto->id());
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('academia_id') !== null) {
                return;
            }

            $contexto = app(ContextoAcademia::class);

            if ($contexto->definida()) {
                $model->setAttribute('academia_id', $contexto->id());
            }
        });
    }

    /** @return BelongsTo<Academia, $this> */
    public function academia(): BelongsTo
    {
        return $this->belongsTo(Academia::class);
    }
}
