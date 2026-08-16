<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Casts\CaixaDeTitulo;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Quem faz login no sistema.
 *
 * `academia_id` NULO identifica o super administrador — a equipe do Pulso, que
 * não pertence a academia nenhuma. Ele opera só o plano de controle
 * (academias, unidades, avisos e usuários): as políticas de Row Level Security
 * não abrem exceção para ele, então aluno, mensalidade e biometria continuam
 * fora do seu alcance mesmo que a conta seja comprometida.
 */
#[Fillable(['name', 'email', 'password', 'preferencias'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => CaixaDeTitulo::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Tema, barra lateral, itens por página. Documento JSON porque a
            // lista cresce, e cada item novo viraria uma migration.
            'preferencias' => 'array',
            'sessao_unica' => 'boolean',
            'ativo' => 'boolean',
            'bloqueado_ate' => 'datetime',
            'ultimo_acesso_em' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------------
    // Relacionamentos
    // ---------------------------------------------------------------------

    /** @return BelongsTo<Academia, $this> */
    public function academia(): BelongsTo
    {
        return $this->belongsTo(Academia::class);
    }

    /**
     * Unidades a que o usuário tem acesso.
     *
     * Sem nenhuma linha aqui, o usuário enxerga TODAS as unidades da academia
     * — é o caso do dono, e evita ter de criar um vínculo novo toda vez que
     * uma filial abre.
     *
     * @return BelongsToMany<Unidade, $this>
     */
    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidade::class, 'unidade_user')->withTimestamps();
    }

    // ---------------------------------------------------------------------
    // Papéis
    // ---------------------------------------------------------------------

    public function ehSuperAdministrador(): bool
    {
        return $this->academia_id === null;
    }

    public function estaBloqueado(): bool
    {
        return $this->bloqueado_ate !== null && $this->bloqueado_ate->isFuture();
    }

    public function podeEntrar(): bool
    {
        return $this->ativo && ! $this->estaBloqueado();
    }

    /** Enxerga todas as unidades da academia? */
    public function enxergaTodasAsUnidades(): bool
    {
        return $this->unidades()->count() === 0;
    }

    /** Lê uma preferência de interface, com valor padrão quando ausente. */
    public function preferencia(string $chave, mixed $padrao = null): mixed
    {
        return data_get($this->preferencias, $chave, $padrao);
    }
}
