<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MotivoBloqueioAcesso;
use App\Enums\ResultadoAcesso;
use App\Enums\SentidoAcesso;
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
            'sentido' => SentidoAcesso::class,
            'ocorreu_em' => 'immutable_datetime',
            'encerrada_em' => 'immutable_datetime',
            'encerrada_presumida' => 'boolean',
        ];
    }

    /**
     * Fecha a entrada.
     *
     * `presumida` separa o que se sabe do que se concluiu: uma saída que
     * ninguém registrou e que o sistema deduziu não pode se parecer com uma
     * que aconteceu. Um relatório de permanência que trate as duas igual
     * mente com confiança.
     */
    public function encerrar(CarbonImmutable $quando, bool $presumida = false): void
    {
        $this->forceFill([
            'encerrada_em' => $quando,
            'encerrada_presumida' => $presumida,
        ])->save();
    }

    public function estaDentro(): bool
    {
        return $this->sentido === SentidoAcesso::Entrada && $this->encerrada_em === null;
    }

    /**
     * Quanto tempo o aluno ficou.
     *
     * Nulo enquanto não saiu — e também quando a saída foi PRESUMIDA: nesse
     * caso ninguém sabe a hora real, e devolver a diferença até o instante em
     * que o sistema concluiu seria inventar um número com cara de medição.
     */
    public function permanenciaEmMinutos(): ?int
    {
        if ($this->encerrada_em === null || $this->encerrada_presumida) {
            return null;
        }

        return (int) $this->ocorreu_em->diffInMinutes($this->encerrada_em);
    }

    /**
     * Quem está na academia agora.
     *
     * @param  Builder<Acesso>  $consulta
     * @return Builder<Acesso>
     */
    public function scopePresentes(Builder $consulta): Builder
    {
        return $consulta
            ->where('sentido', SentidoAcesso::Entrada)
            ->whereNull('encerrada_em');
    }

    /**
     * @param  Builder<Acesso>  $consulta
     * @return Builder<Acesso>
     */
    public function scopeEntradas(Builder $consulta): Builder
    {
        return $consulta->where('sentido', SentidoAcesso::Entrada);
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
