<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DispositivoAcesso;
use App\Support\Academia\ContextoAcademia;
use App\Support\Catraca\AparelhoAtual;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descobre de qual academia é o aparelho que está falando.
 *
 * O aparelho não faz login: ele manda o número de série em toda chamada, e é
 * só isso que temos. Este middleware é o equivalente da autenticação para
 * ele — e, como toda autenticação, precisa acontecer ANTES de existir
 * "academia atual".
 *
 * O OVO E A GALINHA, E COMO SE RESOLVE
 *
 * `dispositivos_acesso` está sob Row Level Security. Consultá-la sem contexto
 * devolveria zero linhas, sempre. Consultá-la com um papel que atravessa o
 * isolamento daria justamente ao endpoint público — o único que responde sem
 * login — o maior privilégio do sistema.
 *
 * A saída é uma função no banco que devolve APENAS a tripla de roteamento
 * para um serial exato. A aplicação não ganha "ler dispositivos": ganha
 * "traduzir um serial que ela já conhece". Definido o contexto, tudo depois
 * disso volta a passar pelo RLS normal.
 *
 * SERIAL DESCONHECIDO NÃO RECEBE ERRO. Recebe "OK", e o dado é descartado. É
 * contraintuitivo e é o certo: qualquer resposta de erro faz o aparelho
 * reenviar o lote para sempre, e um 404 ainda confirmaria para quem estiver
 * sondando que aquele serial não existe — enquanto um serial válido daria
 * outra resposta.
 */
final class IdentificaAparelho
{
    public function handle(Request $requisicao, Closure $seguir): Response
    {
        $serie = trim((string) $requisicao->query('SN', ''));

        if ($serie === '') {
            return $this->descartar();
        }

        $rota = DB::selectOne('SELECT * FROM pulso_dispositivo_por_serie(?)', [$serie]);

        if ($rota === null) {
            Log::warning('Aparelho desconhecido procurou o Pulso.', [
                'serie' => $serie,
                'ip' => $requisicao->ip(),
            ]);

            return $this->descartar();
        }

        app(ContextoAcademia::class)->definir((int) $rota->academia_id);

        $dispositivo = DispositivoAcesso::query()->find((int) $rota->dispositivo_id);

        if ($dispositivo === null) {
            return $this->descartar();
        }

        /*
         * Segredo compartilhado, quando o aparelho estiver configurado com um.
         * É o que separa "meu equipamento" de "quem descobriu a URL" — e a
         * comparação é em tempo constante porque a diferença de milissegundos
         * entre uma chave quase certa e uma errada é informação de sobra para
         * adivinhá-la byte a byte.
         */
        if ($dispositivo->chave_push !== null && $dispositivo->chave_push !== '') {
            $enviada = (string) $requisicao->query('pushcommkey', '');

            if (! hash_equals($dispositivo->chave_push, $enviada)) {
                Log::warning('Aparelho apresentou chave incorreta.', [
                    'serie' => $serie,
                    'ip' => $requisicao->ip(),
                ]);

                return $this->descartar();
            }
        }

        // Num portador explícito, e não na requisição nem como parâmetro de
        // rota — ver a explicação em App\Support\Catraca\AparelhoAtual.
        app(AparelhoAtual::class)->definir($dispositivo);

        return $seguir($requisicao);
    }

    /** Descartar é responder "OK" e não gravar nada. Nunca um erro HTTP. */
    private function descartar(): Response
    {
        return response('OK', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
