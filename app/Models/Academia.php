<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\CaixaDeTitulo;
use App\Enums\SituacaoAcademia;
use Carbon\CarbonImmutable;
use Database\Factories\AcademiaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * O cliente do Pulso — uma academia ou uma rede.
 *
 * Não usa o trait PertenceAAcademia: é a raiz do plano de controle, não um
 * dado de dentro de uma academia. Quem a manipula é o super administrador, e
 * o acesso da própria academia aos seus dados vem por autorização.
 */
final class Academia extends Model
{
    /** @use HasFactory<AcademiaFactory> */
    use HasFactory;

    protected $table = 'academias';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nome' => CaixaDeTitulo::class,
            'situacao' => SituacaoAcademia::class,
            'assinatura_vence_em' => 'date',
            'bloqueada_em' => 'datetime',
            'dias_tolerancia_bloqueio' => 'integer',
            'dias_baixa_frequencia' => 'integer',
            'idade_minima' => 'integer',
            'total_alunos_ativos' => 'integer',
            'contagem_atualizada_em' => 'immutable_datetime',
        ];
    }

    /**
     * Recalcula quantos alunos ativos a academia tem.
     *
     * RECONTA em vez de somar ou subtrair um. Contador incrementado a cada
     * evento acumula erro — uma exceção no meio de uma transação, uma
     * importação em massa, um `delete` em cascata — e o desvio só aparece
     * meses depois, numa fatura errada. A consulta a mais é o preço de o
     * número não precisar de auditoria.
     *
     * Roda por baixo do Row Level Security, então exige o contexto definido:
     * é a aplicação contando os PRÓPRIOS alunos, não o super administrador
     * contando os alheios.
     */
    public function recontarAlunosAtivos(): int
    {
        $total = Matricula::query()->vigentes()->distinct()->count('aluno_id');

        $this->forceFill([
            'total_alunos_ativos' => $total,
            'contagem_atualizada_em' => CarbonImmutable::now(),
        ])->saveQuietly();

        return $total;
    }

    /**
     * A academia tem filial?
     *
     * Esta o super administrador conta direto: `unidades` é plano de
     * controle, e fica fora do isolamento.
     */
    public function temFilial(): bool
    {
        return $this->unidades()->where('ativa', true)->count() > 1;
    }

    /** @return HasMany<Unidade, $this> */
    public function unidades(): HasMany
    {
        return $this->hasMany(Unidade::class);
    }

    /** @return HasMany<User, $this> */
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Aluno, $this> */
    public function alunos(): HasMany
    {
        return $this->hasMany(Aluno::class);
    }

    /** Falta quanto para a assinatura vencer? Negativo já venceu. */
    public function diasParaVencerAssinatura(): ?int
    {
        return $this->assinatura_vence_em?->diffInDays(now()->startOfDay(), false) !== null
            ? (int) now()->startOfDay()->diffInDays($this->assinatura_vence_em, false)
            : null;
    }
}
