<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recado do super administrador na tela da academia.
 *
 * Plano de controle: sem RLS. Com `academia_id` nulo, vale para todas — é como
 * se anuncia manutenção programada.
 */
final class AvisoAcademia extends Model
{
    protected $table = 'avisos_academia';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'exibir_de' => 'immutable_date',
            'exibir_ate' => 'immutable_date',
            'dispensavel' => 'boolean',
        ];
    }

    /** @return BelongsTo<Academia, $this> */
    public function academia(): BelongsTo
    {
        return $this->belongsTo(Academia::class);
    }

    /**
     * Avisos que uma academia deve ver hoje — os dela e os gerais.
     *
     * @param  Builder<AvisoAcademia>  $consulta
     * @return Builder<AvisoAcademia>
     */
    public function scopeVisiveisPara(Builder $consulta, int $academiaId, ?CarbonImmutable $dia = null): Builder
    {
        $hoje = ($dia ?? CarbonImmutable::now())->toDateString();

        return $consulta
            ->where(fn (Builder $q) => $q->where('academia_id', $academiaId)->orWhereNull('academia_id'))
            ->whereDate('exibir_de', '<=', $hoje)
            ->whereDate('exibir_ate', '>=', $hoje);
    }
}
