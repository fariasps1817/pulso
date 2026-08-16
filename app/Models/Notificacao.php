<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAAcademia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O que foi enviado ao aluno — hoje por WhatsApp.
 *
 * Uma linha por envio, para que a regra "mensalidade vencida gera UM lembrete,
 * não uma sequência" (CDC art. 42 — cobrança não pode constranger) seja
 * verificável com uma consulta, em vez de confiada à memória do código. O
 * índice único no banco garante o resto.
 */
final class Notificacao extends Model
{
    use PertenceAAcademia;

    protected $table = 'notificacoes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'enviada_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Aluno, $this> */
    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    /** @return BelongsTo<Mensalidade, $this> */
    public function mensalidade(): BelongsTo
    {
        return $this->belongsTo(Mensalidade::class);
    }
}
