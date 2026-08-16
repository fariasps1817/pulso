<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SituacaoMensalidade;
use App\Models\Concerns\PertenceAAcademia;
use Carbon\CarbonImmutable;
use Database\Factories\MensalidadeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * O que o aluno deve num mês.
 *
 * "VENCIDA NÃO É COLUNA": é `aberta` com vencimento no passado. Guardar como
 * estado exigiria uma rotina diária virando a chave, e no dia em que ela
 * falhasse o Radar mentiria para o dono — silenciosamente, que é o pior jeito.
 */
final class Mensalidade extends Model
{
    /** @use HasFactory<MensalidadeFactory> */
    use HasFactory;

    use PertenceAAcademia;

    protected $table = 'mensalidades';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoMensalidade::class,
            'competencia' => 'immutable_date',
            'vencimento' => 'immutable_date',
            'paga_em' => 'immutable_date',
            'valor' => 'decimal:2',
            'desconto' => 'decimal:2',
        ];
    }

    // ---------------------------------------------------------------------
    // Relacionamentos
    // ---------------------------------------------------------------------

    /** @return BelongsTo<Matricula, $this> */
    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    /** @return BelongsTo<Aluno, $this> */
    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    /** @return HasMany<Pagamento, $this> */
    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }

    /** @return HasMany<Cobranca, $this> */
    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }

    // ---------------------------------------------------------------------
    // Regras
    // ---------------------------------------------------------------------

    public function valorDevido(): string
    {
        return bcsub((string) $this->valor, (string) $this->desconto, 2);
    }

    public function estaVencida(?CarbonImmutable $em = null): bool
    {
        return $this->situacao === SituacaoMensalidade::Aberta
            && $this->vencimento->lessThan(($em ?? CarbonImmutable::now())->startOfDay());
    }

    /**
     * Está vencida além da tolerância que a academia configurou?
     *
     * É esta pergunta, e não `estaVencida()`, que a catraca faz: bloquear no
     * dia seguinte ao vencimento gera briga no balcão.
     */
    public function venceuAlemDaTolerancia(int $diasTolerancia, ?CarbonImmutable $em = null): bool
    {
        if ($this->situacao !== SituacaoMensalidade::Aberta) {
            return false;
        }

        $limite = $this->vencimento->addDays($diasTolerancia);

        return ($em ?? CarbonImmutable::now())->startOfDay()->greaterThan($limite);
    }

    // ---------------------------------------------------------------------
    // Consultas do Radar
    // ---------------------------------------------------------------------

    /**
     * Vencidas. Usa o índice parcial `mensalidades_em_aberto`.
     *
     * @param  Builder<Mensalidade>  $consulta
     */
    /**
     * @param  Builder<Mensalidade>  $consulta
     * @return Builder<Mensalidade>
     */
    public function scopeVencidas(Builder $consulta, ?CarbonImmutable $em = null): Builder
    {
        return $consulta
            ->where('situacao', SituacaoMensalidade::Aberta)
            ->where('vencimento', '<', ($em ?? CarbonImmutable::now())->startOfDay());
    }

    /**
     * @param  Builder<Mensalidade>  $consulta
     * @return Builder<Mensalidade>
     */
    public function scopeVencendoEm(Builder $consulta, CarbonImmutable $dia): Builder
    {
        return $consulta
            ->where('situacao', SituacaoMensalidade::Aberta)
            ->whereDate('vencimento', $dia);
    }

    /**
     * @param  Builder<Mensalidade>  $consulta
     * @return Builder<Mensalidade>
     */
    public function scopeEmAberto(Builder $consulta): Builder
    {
        return $consulta->where('situacao', SituacaoMensalidade::Aberta);
    }
}
