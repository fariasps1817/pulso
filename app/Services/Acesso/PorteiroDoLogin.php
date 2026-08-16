<?php

declare(strict_types=1);

namespace App\Services\Acesso;

use App\Models\TentativaLogin;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;

/**
 * Quem tenta entrar, quantas vezes, e quando para de poder tentar.
 *
 * DUAS CONTAGENS, PORQUE SÃO DOIS ATAQUES DIFERENTES
 *
 *   Por E-MAIL: alguém martelando a conta da recepção. Poucas tentativas
 *   bastam, porque quem sabe a senha acerta em duas ou três.
 *
 *   Por IP: alguém varrendo uma lista de e-mails a partir da mesma máquina. O
 *   limite é mais alto, porque uma academia inteira atrás do mesmo IP erra
 *   senha várias vezes por dia sem nada de errado.
 *
 * A MENSAGEM É A MESMA NOS DOIS CASOS, e também quando o e-mail nem existe. A
 * contagem é por TEXTO DIGITADO, não por conta encontrada — se o bloqueio só
 * acontecesse para e-mail existente, a diferença entre "senha incorreta" e
 * "muitas tentativas" viraria um confirmador de contas.
 *
 * O bloqueio é por janela deslizante, sem coluna de estado: não há rotina para
 * "desbloquear" e, portanto, não há como alguém ficar preso porque um agendador
 * falhou.
 */
final class PorteiroDoLogin
{
    /** Erros do mesmo e-mail que fecham a porta. */
    private const LIMITE_POR_EMAIL = 5;

    /** Erros do mesmo IP — mais alto: a academia inteira sai por um só. */
    private const LIMITE_POR_IP = 20;

    private const MINUTOS_DE_JANELA = 15;

    /**
     * Autentica, contando as tentativas.
     *
     * Devolve nulo quando não deve entrar — sem dizer por quê, para quem
     * chama traduzir numa mensagem única.
     */
    public function autenticar(string $email, string $senha, string $ip, ?string $agente = null): ?User
    {
        $email = mb_strtolower(trim($email));

        if ($this->bloqueado($email, $ip)) {
            /*
             * A tentativa bloqueada TAMBÉM é registrada. Sem isso, o
             * histórico mostraria cinco tentativas e um silêncio — e não
             * daria para saber se o atacante desistiu ou se continuou
             * batendo na porta trancada.
             */
            TentativaLogin::registrar($email, $ip, false, $agente);

            return null;
        }

        $usuario = User::query()->where('email', $email)->first();

        // Hash::check contra hash nulo é impossível, mas o tempo de resposta
        // não pode denunciar a diferença — daí o hash falso.
        $senhaConfere = $usuario !== null
            ? Hash::check($senha, $usuario->password)
            : Hash::check($senha, '$2y$12$'.str_repeat('a', 53));

        TentativaLogin::registrar($email, $ip, $senhaConfere && $usuario !== null, $agente);

        if (! $senhaConfere || $usuario === null) {
            return null;
        }

        $usuario->forceFill(['ultimo_acesso_em' => CarbonImmutable::now()])->saveQuietly();

        return $usuario;
    }

    public function bloqueado(string $email, string $ip): bool
    {
        $desde = CarbonImmutable::now()->subMinutes(self::MINUTOS_DE_JANELA);

        $porEmail = TentativaLogin::query()->falhasRecentesDe($email, $desde)->count();

        if ($porEmail >= self::LIMITE_POR_EMAIL) {
            return true;
        }

        return TentativaLogin::query()->falhasRecentesDoIp($ip, $desde)->count() >= self::LIMITE_POR_IP;
    }

    /**
     * Quanto falta para a porta reabrir.
     *
     * Dizer o prazo não ajuda quem ataca — ele descobriria tentando — e evita
     * que a academia ligue para o suporte achando que o sistema quebrou.
     */
    public function minutosParaLiberar(string $email, string $ip): int
    {
        $desde = CarbonImmutable::now()->subMinutes(self::MINUTOS_DE_JANELA);

        $primeira = TentativaLogin::query()
            ->where('sucesso', false)
            ->where('ocorreu_em', '>=', $desde)
            ->where(fn ($q) => $q->where('email', $email)->orWhere('ip', $ip))
            ->orderBy('ocorreu_em')
            ->value('ocorreu_em');

        if ($primeira === null) {
            return 0;
        }

        $liberaEm = CarbonImmutable::parse($primeira)->addMinutes(self::MINUTOS_DE_JANELA);

        return max(1, (int) ceil(CarbonImmutable::now()->diffInMinutes($liberaEm)));
    }
}
