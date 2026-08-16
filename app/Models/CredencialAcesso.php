<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoCredencial;
use App\Models\Concerns\PertenceAAcademia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Como o aluno se identifica na catraca.
 *
 * O `template` é cifrado pelo cast `encrypted` — e é TEMPLATE, nunca a imagem
 * do rosto. Vazar uma foto é ruim; vazar um banco de rostos é irreversível,
 * porque ninguém troca de rosto como troca de senha.
 */
final class CredencialAcesso extends Model
{
    use PertenceAAcademia;

    protected $table = 'credenciais_acesso';

    protected $guarded = ['id'];

    /**
     * O template nunca aparece em serialização — nem em resposta de API, nem
     * em log, nem em `dd()`. Dado biométrico não circula por descuido.
     *
     * @var list<string>
     */
    protected $hidden = ['template'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoCredencial::class,
            'template' => 'encrypted',
            'ativa' => 'boolean',
            'cadastrada_em' => 'datetime',
            'excluida_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Aluno, $this> */
    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    /** @return BelongsTo<ConsentimentoLgpd, $this> */
    public function consentimento(): BelongsTo
    {
        return $this->belongsTo(ConsentimentoLgpd::class, 'consentimento_id');
    }

    /**
     * Apaga o template DE VERDADE, registrando que houve exclusão.
     *
     * Chamado ao cancelar a matrícula e ao revogar o consentimento (LGPD,
     * art. 18). Guardar a data é parte da obrigação: além de apagar, é preciso
     * conseguir demonstrar que se apagou.
     *
     * A linha permanece — sem o dado, mas com a prova.
     */
    public function apagarTemplate(): void
    {
        $this->forceFill([
            'template' => null,
            'ativa' => false,
            'excluida_em' => now(),
        ])->save();
    }
}
