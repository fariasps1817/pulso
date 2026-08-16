<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MotivoBloqueioAcesso;
use App\Enums\ResultadoAcesso;
use App\Models\Concerns\PertenceAAcademia;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cada passagem (ou tentativa) na catraca.
 *
 * O `motivo` fica aqui e NUNCA vai para o display: acesso negado mostra
 * sempre "Procure a recepção". Expor a dívida com a fila atrás é
 * constrangimento vedado pelo Código de Defesa do Consumidor (art. 42).
 */
final class Acesso extends Model
{
    use PertenceAAcademia;

    protected $table = 'acessos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'resultado' => ResultadoAcesso::class,
            'motivo' => MotivoBloqueioAcesso::class,
            'ocorreu_em' => 'immutable_datetime',
        ];
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

    /** @return BelongsTo<DispositivoAcesso, $this> */
    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(DispositivoAcesso::class, 'dispositivo_id');
    }

    /** O que o aluno lê na catraca — sempre o mesmo, qualquer que seja o motivo. */
    public function mensagemNaCatraca(): string
    {
        return $this->resultado === ResultadoAcesso::Liberado
            ? 'Acesso liberado'
            : 'Procure a recepção';
    }

    /**
     * @param  Builder<Acesso>  $consulta
     * @return Builder<Acesso>
     */
    public function scopeDesde(Builder $consulta, CarbonImmutable $momento): Builder
    {
        return $consulta->where('ocorreu_em', '>=', $momento);
    }
}
