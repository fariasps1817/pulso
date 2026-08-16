<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAAcademia;
use Database\Factories\ConsentimentoLgpdFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * O aceite do aluno para o tratamento de dado biométrico.
 *
 * Específico e SEPARADO do contrato de matrícula, com a finalidade escrita —
 * é o que a LGPD (art. 11) exige para dado sensível, e o motivo de isto ser
 * tabela própria em vez de uma caixa marcada no cadastro.
 */
final class ConsentimentoLgpd extends Model
{
    /** @use HasFactory<ConsentimentoLgpdFactory> */
    use HasFactory;

    use PertenceAAcademia;

    protected $table = 'consentimentos_lgpd';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'aceito_em' => 'datetime',
            'revogado_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Aluno, $this> */
    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    /** @return HasMany<CredencialAcesso, $this> */
    public function credenciais(): HasMany
    {
        return $this->hasMany(CredencialAcesso::class, 'consentimento_id');
    }

    public function estaVigente(): bool
    {
        return $this->revogado_em === null;
    }

    /**
     * Revoga e apaga as biometrias que dependiam deste consentimento.
     *
     * Revogar sem apagar seria consentimento de fachada: o dado continuaria
     * lá. Por isso as duas coisas acontecem juntas, e não em passos separados
     * que alguém pode esquecer de encadear.
     */
    public function revogar(): void
    {
        $this->forceFill(['revogado_em' => now()])->save();

        $this->credenciais()->whereNotNull('template')->get()
            ->each(fn (CredencialAcesso $credencial) => $credencial->apagarTemplate());
    }
}
