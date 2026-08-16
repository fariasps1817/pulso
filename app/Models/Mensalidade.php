<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FormaPagamento;
use App\Enums\SituacaoMensalidade;
use App\Models\Concerns\PertenceAAcademia;
use Carbon\CarbonImmutable;
use Database\Factories\MensalidadeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    /** @return BelongsTo<Unidade, $this> */
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
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

    /** Quanto já entrou, sem contar o que foi estornado. */
    public function valorPago(): string
    {
        return (string) $this->pagamentos()->whereNull('estornado_em')->sum('valor');
    }

    public function valorEmAberto(): string
    {
        $restante = bcsub($this->valorDevido(), $this->valorPago(), 2);

        return bccomp($restante, '0', 2) === 1 ? $restante : '0.00';
    }

    /**
     * Registra dinheiro que entrou e reavalia a situação.
     *
     * Os dois passos ficam juntos de propósito: separá-los abriria a chance de
     * alguém registrar o pagamento e esquecer de dar baixa, deixando a
     * mensalidade paga aparecendo como vencida no Radar.
     */
    public function registrarPagamento(
        string $valor,
        FormaPagamento $forma,
        CarbonImmutable $recebidoEm,
        ?int $registradoPor = null,
    ): Pagamento {
        return DB::transaction(function () use ($valor, $forma, $recebidoEm, $registradoPor): Pagamento {
            $pagamento = $this->pagamentos()->create([
                'valor' => $valor,
                'forma' => $forma,
                'recebido_em' => $recebidoEm->toDateString(),
                'registrado_por' => $registradoPor,
            ]);

            $this->reavaliarSituacao();

            return $pagamento;
        });
    }

    /**
     * Recalcula a situação a partir do que efetivamente entrou.
     *
     * Chamado depois de pagar e depois de estornar — a mensalidade volta a
     * ficar em aberto quando o dinheiro volta, sem ninguém precisar lembrar.
     */
    public function reavaliarSituacao(): void
    {
        if ($this->situacao === SituacaoMensalidade::Cancelada) {
            return;
        }

        $quitada = bccomp($this->valorPago(), $this->valorDevido(), 2) >= 0;

        $this->forceFill([
            'situacao' => $quitada ? SituacaoMensalidade::Paga : SituacaoMensalidade::Aberta,
            'paga_em' => $quitada
                ? ($this->pagamentos()->whereNull('estornado_em')->max('recebido_em'))
                : null,
        ])->save();
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
