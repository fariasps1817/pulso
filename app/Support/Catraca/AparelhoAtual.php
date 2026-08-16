<?php

declare(strict_types=1);

namespace App\Support\Catraca;

use App\Models\DispositivoAcesso;

/**
 * Qual aparelho está falando nesta requisição.
 *
 * Mesmo papel do ContextoAcademia, e pela mesma razão: uma requisição, um
 * aparelho. O middleware identifica pelo número de série e guarda aqui; o
 * controller lê.
 *
 * POR QUE NÃO PELA REQUISIÇÃO, QUE SERIA O ÓBVIO
 *
 * As duas tentativas naturais falharam do mesmo jeito — em silêncio:
 *
 *   1. Declarar `DispositivoAcesso $dispositivo` na assinatura do controller.
 *      Estas rotas não têm parâmetro na URL, então o container CONSTRÓI um
 *      modelo vazio em vez de reclamar. O handshake passou a responder
 *      `GET OPTION FROM:` sem série nenhuma, com status 200.
 *   2. Guardar no atributo da requisição. Funciona numa chamada HTTP comum,
 *      mas o controller recebe a `Request` que o CONTAINER tem — e ela nem
 *      sempre é o objeto que o middleware tocou.
 *
 * Um portador explícito não depende de nenhuma dessas resoluções.
 */
final class AparelhoAtual
{
    private ?DispositivoAcesso $dispositivo = null;

    public function definir(?DispositivoAcesso $dispositivo): void
    {
        $this->dispositivo = $dispositivo;
    }

    public function obter(): ?DispositivoAcesso
    {
        return $this->dispositivo;
    }

    /**
     * O aparelho, ou falha.
     *
     * Sem o middleware a requisição não chega ao controller; se chegou sem
     * aparelho, é defeito nosso — e defeito nosso deve aparecer.
     */
    public function obrigatorio(): DispositivoAcesso
    {
        $dispositivo = $this->dispositivo;

        abort_if($dispositivo === null, 500, 'Aparelho não identificado.');

        return $dispositivo;
    }

    public function limpar(): void
    {
        $this->dispositivo = null;
    }
}
