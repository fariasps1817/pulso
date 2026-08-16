<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SituacaoComando;
use App\Models\Concerns\PertenceAAcademia;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um comando esperando o aparelho vir buscar.
 *
 * O aparelho nunca é chamado: ele pergunta. Cadastrar um aluno no leitor é
 * gravar esta linha e esperar o próximo polling.
 */
final class ComandoDispositivo extends Model
{
    use PertenceAAcademia;

    protected $table = 'comandos_dispositivo';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoComando::class,
            'tentativas' => 'integer',
            'retorno' => 'integer',
            'entregue_em' => 'immutable_datetime',
            'confirmado_em' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<DispositivoAcesso, $this> */
    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(DispositivoAcesso::class, 'dispositivo_id');
    }

    /** @return BelongsTo<Aluno, $this> */
    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    /**
     * A linha que vai no corpo da resposta do polling.
     *
     * O identificador do comando é a chave primária: é por ele que o ACK do
     * aparelho volta, e é o que permite casar resposta com pedido.
     */
    public function paraOAparelho(): string
    {
        return "C:{$this->id}:{$this->corpo}";
    }

    public function marcarEntregue(): void
    {
        $this->forceFill([
            'situacao' => SituacaoComando::Entregue,
            'entregue_em' => CarbonImmutable::now(),
            'tentativas' => $this->tentativas + 1,
        ])->save();
    }

    /**
     * Registra o ACK. Zero é sucesso; qualquer outro código é diagnóstico.
     *
     * Não há reenvio automático depois de uma recusa: o aparelho respondeu, e
     * insistir com o mesmo comando daria o mesmo resultado. Quem resolve é
     * quem lê o código na tela.
     */
    public function registrarRetorno(int $retorno): void
    {
        $this->forceFill([
            'situacao' => $retorno === 0 ? SituacaoComando::Confirmado : SituacaoComando::Falhou,
            'confirmado_em' => CarbonImmutable::now(),
            'retorno' => $retorno,
        ])->save();
    }

    /**
     * Comandos entregues que nunca voltaram — candidatos a reenvio.
     *
     * @param  Builder<ComandoDispositivo>  $consulta
     * @return Builder<ComandoDispositivo>
     */
    public function scopeSemRespostaDesde(Builder $consulta, CarbonImmutable $limite): Builder
    {
        return $consulta
            ->where('situacao', SituacaoComando::Entregue)
            ->where('entregue_em', '<', $limite);
    }
}
