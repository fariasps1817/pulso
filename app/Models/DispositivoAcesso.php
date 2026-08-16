<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAAcademia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catracas e leitores instalados na unidade. */
final class DispositivoAcesso extends Model
{
    use PertenceAAcademia;

    protected $table = 'dispositivos_acesso';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'ultima_sincronizacao_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Unidade, $this> */
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    /** @return HasMany<Acesso, $this> */
    public function acessos(): HasMany
    {
        return $this->hasMany(Acesso::class, 'dispositivo_id');
    }
}
