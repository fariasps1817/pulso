<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\CaixaDeTitulo;
use App\Models\Concerns\PertenceAAcademia;
use Database\Factories\PlanoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * O que a academia vende.
 *
 * Plano descontinuado não é apagado: matrículas antigas continuam apontando
 * para ele e o histórico precisa saber o que foi contratado. Desativa-se.
 */
final class Plano extends Model
{
    /** @use HasFactory<PlanoFactory> */
    use HasFactory;

    use PertenceAAcademia;
    use SoftDeletes;

    protected $table = 'planos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nome' => CaixaDeTitulo::class,
            'valor_mensal' => 'decimal:2',
            'taxa_matricula' => 'decimal:2',
            'multa_cancelamento' => 'decimal:2',
            'duracao_meses' => 'integer',
            'dias_experiencia' => 'integer',
            'sessoes_experiencia' => 'integer',
            'acesso_todas_unidades' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    /** @return HasMany<Matricula, $this> */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /**
     * Só as matrículas em vigor — ativas ou em experiência.
     *
     * Relacionamento próprio, e não filtro no `withCount`, para que a
     * definição de "vigente" continue num lugar só e a contagem seja tipada.
     *
     * @return HasMany<Matricula, $this>
     */
    public function matriculasVigentes(): HasMany
    {
        return $this->matriculas()->vigentes();
    }

    /** O plano concede período de teste? */
    public function temExperiencia(): bool
    {
        return $this->dias_experiencia > 0 || $this->sessoes_experiencia > 0;
    }
}
