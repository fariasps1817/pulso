<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAAcademia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cobrança emitida no provedor — Pix ou link de cartão.
 *
 * Deliberadamente neutra quanto ao provedor: `provedor`, `id_externo` e
 * `payload` acomodam Asaas, Mercado Pago, Efí ou integração direta com banco
 * sem migration. A escolha ainda não foi feita.
 */
final class Cobranca extends Model
{
    use PertenceAAcademia;

    protected $table = 'cobrancas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'expira_em' => 'datetime',
            // Retorno cru do provedor e dos webhooks. Quando algo não bater
            // com o extrato, a resposta original estará aqui — e não perdida
            // num log rotacionado.
            'payload' => 'array',
        ];
    }

    /** @return BelongsTo<Mensalidade, $this> */
    public function mensalidade(): BelongsTo
    {
        return $this->belongsTo(Mensalidade::class);
    }

    /** @return HasMany<Pagamento, $this> */
    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }
}
