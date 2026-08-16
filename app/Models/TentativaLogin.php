<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trilha de auditoria das tentativas de login.
 *
 * O bloqueio em si vive no limitador de taxa, em cache, porque precisa ser
 * rápido. Esta tabela responde a pergunta que o cache esquece: "quem tentou
 * entrar na conta da recepção às três da manhã?".
 *
 * Sem `academia_id`: a tentativa acontece antes de sabermos quem é, e o e-mail
 * digitado pode nem existir.
 */
final class TentativaLogin extends Model
{
    protected $table = 'tentativas_login';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sucesso' => 'boolean',
            'ocorreu_em' => 'immutable_datetime',
        ];
    }

    public static function registrar(string $email, string $ip, bool $sucesso, ?string $agente = null): self
    {
        return self::create([
            // Guardado em minúsculas para que a contagem por e-mail não seja
            // driblada alternando maiúsculas.
            'email' => mb_strtolower($email),
            'ip' => $ip,
            'agente' => $agente,
            'sucesso' => $sucesso,
            'ocorreu_em' => now(),
        ]);
    }

    /**
     * @param  Builder<TentativaLogin>  $consulta
     * @return Builder<TentativaLogin>
     */
    public function scopeFalhasRecentesDe(Builder $consulta, string $email, CarbonImmutable $desde): Builder
    {
        return $consulta
            ->where('email', mb_strtolower($email))
            ->where('sucesso', false)
            ->where('ocorreu_em', '>=', $desde);
    }

    /**
     * @param  Builder<TentativaLogin>  $consulta
     * @return Builder<TentativaLogin>
     */
    public function scopeFalhasRecentesDoIp(Builder $consulta, string $ip, CarbonImmutable $desde): Builder
    {
        return $consulta
            ->where('ip', $ip)
            ->where('sucesso', false)
            ->where('ocorreu_em', '>=', $desde);
    }
}
