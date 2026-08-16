<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAAcademia;
use Carbon\CarbonImmutable;
use Database\Factories\DispositivoAcessoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catracas e leitores instalados na unidade. */
final class DispositivoAcesso extends Model
{
    /** @use HasFactory<DispositivoAcessoFactory> */
    use HasFactory;

    use PertenceAAcademia;

    protected $table = 'dispositivos_acesso';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'informacoes' => 'array',
            'ultima_sincronizacao_em' => 'immutable_datetime',
            'ultimo_contato_em' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<ComandoDispositivo, $this> */
    public function comandos(): HasMany
    {
        return $this->hasMany(ComandoDispositivo::class, 'dispositivo_id');
    }

    /**
     * O aparelho fala a cada poucos segundos. Passar de dois minutos calado
     * já é sintoma — de queda de rede, de tomada, ou de alguém ter mudado o
     * endereço do servidor no menu.
     */
    public function online(): bool
    {
        return $this->ultimo_contato_em !== null
            && $this->ultimo_contato_em->greaterThan(CarbonImmutable::now()->subMinutes(2));
    }

    public function registrarContato(): void
    {
        // Sem `touch` nos timestamps: o heartbeat chega a cada dois segundos,
        // e mexer em `updated_at` faria a coluna virar ruído.
        $this->forceFill(['ultimo_contato_em' => CarbonImmutable::now()])->saveQuietly();
    }

    /** @param array<string, string> $ficha */
    public function registrarFicha(array $ficha): void
    {
        $this->forceFill([
            'informacoes' => $ficha,
            'firmware' => $ficha['FWVersion'] ?? $this->firmware,
            'ultima_sincronizacao_em' => CarbonImmutable::now(),
        ])->save();
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
