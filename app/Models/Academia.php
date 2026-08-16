<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\CaixaDeTitulo;
use App\Enums\SituacaoAcademia;
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
        ];
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
