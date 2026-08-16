<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SituacaoMatricula;
use App\Enums\TipoMatricula;
use App\Models\Concerns\PertenceAAcademia;
use Carbon\CarbonImmutable;
use Database\Factories\MatriculaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * O vínculo entre aluno, plano e unidade.
 *
 * `valor_mensal` é copiado do plano na contratação — se a mensalidade lesse o
 * preço atual, reajustar o plano em janeiro mudaria retroativamente o que o
 * aluno devia em novembro.
 */
final class Matricula extends Model
{
    /** @use HasFactory<MatriculaFactory> */
    use HasFactory;

    use PertenceAAcademia;

    protected $table = 'matriculas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMatricula::class,
            'situacao' => SituacaoMatricula::class,
            'contrato_assinado_em' => 'immutable_date',
            'inicio_em' => 'immutable_date',
            'fim_previsto_em' => 'immutable_date',
            'encerrada_em' => 'immutable_date',
            'dia_vencimento' => 'integer',
            'sessoes_usadas' => 'integer',
            'valor_mensal' => 'decimal:2',
        ];
    }

    // ---------------------------------------------------------------------
    // Relacionamentos
    // ---------------------------------------------------------------------

    /** @return BelongsTo<Aluno, $this> */
    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    /** @return BelongsTo<Plano, $this> */
    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }

    /** @return BelongsTo<Unidade, $this> */
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    /** @return HasMany<Mensalidade, $this> */
    public function mensalidades(): HasMany
    {
        return $this->hasMany(Mensalidade::class);
    }

    // ---------------------------------------------------------------------
    // Regras
    // ---------------------------------------------------------------------

    /**
     * O período de experiência acabou?
     *
     * Acaba pelo que vier primeiro: os dias ou as sessões. Uma academia usa
     * "7 dias", outra usa "3 aulas experimentais", e a regra precisa atender
     * as duas sem configuração extra.
     */
    public function experienciaEsgotada(?CarbonImmutable $em = null): bool
    {
        if ($this->tipo !== TipoMatricula::Experiencia) {
            return false;
        }

        $em ??= CarbonImmutable::now();
        $plano = $this->plano;

        if ($plano->sessoes_experiencia > 0 && $this->sessoes_usadas >= $plano->sessoes_experiencia) {
            return true;
        }

        if ($plano->dias_experiencia > 0) {
            $ultimoDia = $this->inicio_em->addDays($plano->dias_experiencia);

            if ($em->startOfDay()->greaterThan($ultimoDia)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vencimento da competência informada.
     *
     * `competencia` é sempre o primeiro dia do mês; o dia escolhido está entre
     * 1 e 28, então não há mês em que a data não exista.
     */
    public function vencimentoDe(CarbonImmutable $competencia): CarbonImmutable
    {
        return $competencia->startOfMonth()->addDays($this->dia_vencimento - 1);
    }

    /**
     * @param  Builder<Matricula>  $consulta
     * @return Builder<Matricula>
     */
    public function scopeVigentes(Builder $consulta): Builder
    {
        return $consulta->whereIn('situacao', [
            SituacaoMatricula::Ativa->value,
            SituacaoMatricula::Experiencia->value,
        ]);
    }
}
