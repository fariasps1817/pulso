<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FormaPagamento;
use App\Models\Concerns\PertenceAAcademia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O dinheiro que entrou.
 *
 * Uma mensalidade pode receber vários: metade em dinheiro, metade no Pix.
 * Estorno marca `estornado_em` em vez de apagar — apagar dinheiro que entrou e
 * depois voltou destrói a conciliação com o extrato.
 */
final class Pagamento extends Model
{
    use PertenceAAcademia;

    protected $table = 'pagamentos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'forma' => FormaPagamento::class,
            'recebido_em' => 'immutable_date',
            'estornado_em' => 'datetime',
            'valor' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Mensalidade, $this> */
    public function mensalidade(): BelongsTo
    {
        return $this->belongsTo(Mensalidade::class);
    }

    /** @return BelongsTo<Cobranca, $this> */
    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }

    /**
     * Quem deu baixa. Nulo = baixa automática por webhook do provedor.
     *
     * @return BelongsTo<User, $this>
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function estaEstornado(): bool
    {
        return $this->estornado_em !== null;
    }

    /**
     * @param  Builder<Pagamento>  $consulta
     * @return Builder<Pagamento>
     */
    public function scopeValidos(Builder $consulta): Builder
    {
        return $consulta->whereNull('estornado_em');
    }
}
