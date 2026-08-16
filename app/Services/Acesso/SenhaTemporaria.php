<?php

declare(strict_types=1);

namespace App\Services\Acesso;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gera a senha temporária de um usuário.
 *
 * Existe num lugar só porque três telas precisam dela — cadastro de usuário,
 * criação de academia e redefinição — e porque a regra que a acompanha não
 * pode divergir entre elas: quem recebe uma senha daqui é OBRIGADO a trocá-la
 * antes de chegar a qualquer tela.
 *
 * O alfabeto exclui o que se confunde ao telefone (`0`/`O`, `1`/`l`/`I`):
 * alguém vai ditar isto para outra pessoa.
 */
final class SenhaTemporaria
{
    private const ALFABETO = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const TAMANHO = 12;

    public static function gerar(): string
    {
        $senha = '';

        for ($i = 0; $i < self::TAMANHO; $i++) {
            $senha .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return $senha;
    }

    /**
     * Troca a senha de alguém por uma temporária e devolve o que foi gerado.
     *
     * DERRUBA AS SESSÕES ABERTAS. Redefinir senha costuma acontecer porque a
     * pessoa perdeu acesso — mas também porque a conta pode estar em mãos
     * erradas. Manter viva a sessão de quem já estava dentro tornaria a
     * redefinição inútil justamente no caso que mais importa.
     *
     * Quem pediu fica registrado: é a primeira pergunta quando alguém
     * reclamar que a senha mudou sem ter pedido.
     */
    public static function redefinirPara(User $usuario, User $solicitante): string
    {
        $senha = self::gerar();

        DB::transaction(function () use ($usuario, $senha): void {
            $usuario->forceFill([
                'password' => $senha,
                'deve_trocar_senha' => true,
            ])->save();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $usuario->id)
                    ->delete();
            }
        });

        Log::info('Senha redefinida.', [
            'usuario' => $usuario->id,
            'email' => $usuario->email,
            'academia' => $usuario->academia_id,
            'por' => $solicitante->id,
            'por_email' => $solicitante->email,
        ]);

        return $senha;
    }
}
