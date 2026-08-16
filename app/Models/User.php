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
use Illuminate\Support\Collection;
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
/*
 * Lista explícita, e não `guarded`: o que NÃO está aqui é o que o sistema
 * escreve sozinho — `bloqueado_ate` e `ultimo_acesso_em` vêm das tentativas de
 * login, e os campos de dois fatores, do Fortify. Nenhum deles deve chegar por
 * formulário.
 */
#[Fillable([
    'name', 'email', 'password', 'preferencias',
    'academia_id', 'unidade_padrao_id',
    'acessa_todas_unidades', 'pode_alternar_unidade',
    'sessao_unica', 'minutos_inatividade',
    'ativo', 'deve_trocar_senha',
])]
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
            'acessa_todas_unidades' => 'boolean',
            'pode_alternar_unidade' => 'boolean',
            'ativo' => 'boolean',
            'deve_trocar_senha' => 'boolean',
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
     * Unidades vinculadas explicitamente ao usuário.
     *
     * **Vínculo vazio não significa "todas".** Quem enxerga a rede inteira tem
     * `acessa_todas_unidades` marcado. Antes, a ausência de vínculo liberava
     * tudo — e uma recepcionista cadastrada às pressas ganhava a academia
     * inteira, em silêncio.
     *
     * @return BelongsToMany<Unidade, $this>
     */
    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidade::class, 'unidade_user')->withTimestamps();
    }

    /** @return BelongsTo<Unidade, $this> */
    public function unidadePadrao(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_padrao_id');
    }

    /**
     * As unidades que este usuário pode operar, já ordenadas.
     *
     * Por cadastro, e não por nome: a unidade registrada primeiro é a
     * principal, e é ela que deve abrir por padrão. Alfabético faria a rede
     * abrir na "Aldeota" em vez da "Matriz".
     *
     * @return Collection<int, Unidade>
     */
    public function unidadesAcessiveis(): Collection
    {
        if ($this->academia_id === null) {
            return collect();
        }

        /*
         * Colunas qualificadas: o caminho pelo vínculo faz join com
         * `unidade_user`, e tanto `id` quanto `ativa` ficariam ambíguos — o
         * PostgreSQL recusa a consulta em vez de escolher por conta própria.
         */
        $consulta = $this->acessa_todas_unidades
            ? Unidade::query()->where('unidades.academia_id', $this->academia_id)
            : $this->unidades()->getQuery();

        /*
         * `select unidades.*` é obrigatório no caminho pelo vínculo: com
         * `select *`, o join traz também o `id` de `unidade_user`, e como ele
         * vem depois, sobrescreve o da unidade. O model sairia com o id
         * errado, e comparar unidades passaria a dar falso silenciosamente.
         */
        return $consulta
            ->select('unidades.*')
            ->where('unidades.ativa', true)
            ->orderBy('unidades.id')
            ->get();
    }

    /**
     * A unidade em que o usuário está operando.
     *
     * Preferência de sessão só vale para quem pode alternar; do contrário,
     * bastaria forjar a preferência para escapar do travamento.
     */
    public function unidadeAtual(): ?Unidade
    {
        $acessiveis = $this->unidadesAcessiveis();

        if ($acessiveis->isEmpty()) {
            return null;
        }

        if ($this->pode_alternar_unidade) {
            $escolhida = $acessiveis->firstWhere('id', $this->preferencia('unidade_id'));

            if ($escolhida !== null) {
                return $escolhida;
            }
        }

        return $acessiveis->firstWhere('id', $this->unidade_padrao_id) ?? $acessiveis->first();
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

    /** Tem mais de uma unidade E permissão para trocar entre elas? */
    public function podeTrocarDeUnidade(): bool
    {
        return $this->pode_alternar_unidade && $this->unidadesAcessiveis()->count() > 1;
    }

    /** Lê uma preferência de interface, com valor padrão quando ausente. */
    public function preferencia(string $chave, mixed $padrao = null): mixed
    {
        return data_get($this->preferencias, $chave, $padrao);
    }
}
