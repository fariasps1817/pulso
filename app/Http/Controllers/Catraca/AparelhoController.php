<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catraca;

use App\Http\Controllers\Controller;
use App\Models\ComandoDispositivo;
use App\Models\DispositivoAcesso;
use App\Services\Catraca\FilaDeComandos;
use App\Services\Catraca\MotorDeAcesso;
use App\Services\Catraca\Protocolo;
use App\Services\Catraca\RelogioZk;
use App\Support\Catraca\AparelhoAtual;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Os endpoints que o aparelho biométrico procura.
 *
 * O SENTIDO DA CONVERSA É FIXO: o aparelho é o cliente, o Pulso é o servidor.
 * Nunca abrimos conexão com ele. Ele pergunta a cada poucos segundos se há
 * comando, e empurra o que capturou. Não há login, não há sessão, não há
 * CSRF — a identidade é o número de série, e só.
 *
 * A REGRA DE OURO: responder 200 com "OK" a todo upload. Um 500, um 404 ou um
 * timeout fazem o aparelho REENVIAR o lote inteiro na próxima tentativa. Foi
 * por antecipar isso que a chave de idempotência existe: as duas defesas
 * trabalham juntas, e nenhuma das duas basta sozinha.
 *
 * Erro de processamento vai para o log e devolve "OK" assim mesmo. Parece
 * errado e não é: insistir com o aparelho não conserta um defeito nosso, e
 * ainda enche a tabela de passagens repetidas.
 */
final class AparelhoController extends Controller
{
    public function __construct(private readonly AparelhoAtual $aparelho) {}

    /**
     * Handshake e uploads compartilham a URL; o método separa os dois.
     *
     * GET com `options=all` é o aparelho se apresentando ao ligar. POST é
     * dado subindo, e o `table` diz de que tipo.
     */
    public function cdata(Request $requisicao): Response
    {
        $dispositivo = $this->aparelho->obrigatorio();

        if ($requisicao->isMethod('GET')) {
            return $this->texto(Protocolo::opcoes(
                (string) $dispositivo->numero_serie,
                (int) config('pulso.catraca.intervalo_polling'),
            ));
        }

        $corpo = $requisicao->getContent();

        try {
            match ($requisicao->query('table')) {
                'ATTLOG' => $this->registrarPassagens($dispositivo, $corpo),
                'options' => $dispositivo->registrarFicha(Protocolo::ficha($corpo)),
                default => null,
            };
        } catch (Throwable $erro) {
            $this->registrarFalha($dispositivo, $erro, (string) $requisicao->query('table'));
        }

        return $this->ok();
    }

    /**
     * O polling. É aqui que a fila anda.
     *
     * Corpo vazio não existe neste protocolo: fila vazia responde "OK", e o
     * aparelho volta a perguntar depois do intervalo combinado.
     */
    public function getrequest(Request $requisicao): Response
    {
        $dispositivo = $this->aparelho->obrigatorio();

        $dispositivo->registrarContato();

        // O aparelho aproveita o polling para contar como está: quantos
        // usuários, quantas digitais, qual firmware.
        if ($requisicao->has('INFO')) {
            $dispositivo->forceFill([
                'informacoes' => array_merge(
                    $dispositivo->informacoes ?? [],
                    ['INFO' => (string) $requisicao->query('INFO')],
                ),
            ])->saveQuietly();
        }

        $comando = (new FilaDeComandos($dispositivo))->proximo();

        return $this->texto($comando?->paraOAparelho() ?? 'OK');
    }

    /** A confirmação de um comando: `ID=1&Return=0&CMD=DATA`. */
    public function devicecmd(Request $requisicao): Response
    {
        $dispositivo = $this->aparelho->obrigatorio();

        foreach (Protocolo::lerConfirmacoes($requisicao->getContent()) as $confirmacao) {
            $comando = ComandoDispositivo::query()
                ->where('dispositivo_id', $dispositivo->id)
                ->find($confirmacao['id']);

            $comando?->registrarRetorno($confirmacao['retorno']);
        }

        return $this->ok();
    }

    /** Sincronização do relógio do aparelho. */
    public function rtdata(): Response
    {
        return $this->texto(RelogioZk::respostaDeSincronizacao());
    }

    public function ping(): Response
    {
        $dispositivo = $this->aparelho->obrigatorio();

        $dispositivo->registrarContato();

        return $this->ok();
    }

    /**
     * Registro inicial. O aparelho só precisa receber alguma coisa no
     * formato certo; o valor em si não é verificado depois.
     */
    public function registry(): Response
    {
        $dispositivo = $this->aparelho->obrigatorio();

        return $this->texto("RegistryCode={$dispositivo->id}\n");
    }

    /** Qualquer rota do aparelho que não conheçamos: "OK" e seguimos. */
    public function ok(): Response
    {
        return $this->texto('OK');
    }

    // -----------------------------------------------------------------

    private function registrarPassagens(DispositivoAcesso $dispositivo, string $corpo): void
    {
        $motor = new MotorDeAcesso($dispositivo);
        $serie = (string) $dispositivo->numero_serie;

        foreach (Protocolo::lerPassagens($corpo) as $registro) {
            $motor->registrar($registro, Protocolo::chaveDeOrigem($serie, $registro));
        }
    }

    private function registrarFalha(DispositivoAcesso $dispositivo, Throwable $erro, string $tabela): void
    {
        Log::error('Falha ao processar dado da catraca.', [
            'dispositivo' => $dispositivo->id,
            'serie' => $dispositivo->numero_serie,
            'tabela' => $tabela,
            'erro' => $erro->getMessage(),
        ]);
    }

    private function texto(string $corpo): Response
    {
        return response($corpo, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
