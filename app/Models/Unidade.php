<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\CaixaDeTitulo;
use Database\Factories\UnidadeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Filial de uma academia. Toda academia tem pelo menos uma.
 *
 * Não usa PertenceAAcademia: é plano de controle, como a academia. O
 * isolamento aqui é por autorização — e o dono da rede precisa enxergar todas
 * as suas unidades, o que uma política de RLS por academia atrapalharia sem
 * ganho de segurança.
 */
final class Unidade extends Model
{
    /** @use HasFactory<UnidadeFactory> */
    use HasFactory;

    protected $table = 'unidades';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nome' => CaixaDeTitulo::class,
            'ativa' => 'boolean',
        ];
    }

    /** @return BelongsTo<Academia, $this> */
    public function academia(): BelongsTo
    {
        return $this->belongsTo(Academia::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'unidade_user')->withTimestamps();
    }

    /** @return HasMany<Matricula, $this> */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /** @return HasMany<DispositivoAcesso, $this> */
    public function dispositivos(): HasMany
    {
        return $this->hasMany(DispositivoAcesso::class);
    }
}
