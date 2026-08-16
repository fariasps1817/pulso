<?php

declare(strict_types=1);

namespace App\Services\Catraca;

use Carbon\CarbonImmutable;

/**
 * A leitura do que o aparelho manda, e a montagem do que ele espera ouvir.
 *
 * Só formato: nada aqui toca no banco. Separado assim porque cada regra
 * abaixo custou tráfego real capturado para ser descoberta, e um parser que
 * pode ser testado sem HTTP nem banco é um parser que vai continuar certo.
 *
 * AS TRÊS REGRAS DE FORMATO QUE, SOZINHAS, QUEBRAM TUDO
 *
 *   1. Campos separados por TAB. Nunca espaço. Nomes têm espaço ("Ana Maria
 *      da Silva"), então o espaço não pode ser fronteira.
 *   2. O prefixo (BIODATA, USER, FP, FACE) vem separado do primeiro campo por
 *      UM espaço — o único do formato.
 *   3. As chaves não têm caixa consistente: `FP` manda `Size`/`Valid`, `FACE`
 *      manda `SIZE`/`VALID`. Comparar com sensibilidade a maiúsculas faz o
 *      cadastro facial sumir sem erro nenhum.
 */
final class Protocolo
{
    /**
     * O bloco de opções do handshake.
     *
     * `Realtime=1` é o que faz cada passagem subir na hora em vez de esperar
     * o lote — sem ele, a tela de acesso mostraria movimento de meia hora
     * atrás. `TransFlag` habilita os TIPOS de dado: omitir um token é o
     * bastante para nunca receber aquele tipo, e o sintoma é silêncio.
     */
    public static function opcoes(string $serie, int $intervaloEmSegundos): string
    {
        $linhas = [
            "GET OPTION FROM: {$serie}",
            'ATTLOGStamp=None',
            'OPERLOGStamp=None',
            'BIODATAStamp=None',
            'ATTPHOTOStamp=None',
            'ErrorDelay=10',
            "Delay={$intervaloEmSegundos}",
            'TransTimes=00:00;14:00',
            'TransInterval=1',
            'TransFlag=TransData AttLog OpLog AttPhoto EnrollUser ChgUser EnrollFP ChgFP FACE UserPic BioPhoto',
            'TimeZone='.RelogioZk::fusoEmHoras(),
            'Realtime=1',
            'Encrypt=None',
            'ServerVer=3.0.1 '.CarbonImmutable::now()->toDateString(),
        ];

        return implode("\n", $linhas)."\n";
    }

    /**
     * Uma linha de ATTLOG — uma passagem.
     *
     * Campos: PIN, instante, status, método, e reservados. O `status` chega
     * como 255 ("sem estado") no nosso arranjo: o aparelho não sabe se a
     * pessoa entrou ou saiu, e é o Pulso que deduz.
     *
     * @return list<RegistroDePassagem>
     */
    public static function lerPassagens(string $corpo): array
    {
        $registros = [];

        foreach (self::linhas($corpo) as $linha) {
            $campos = explode("\t", $linha);

            $pin = trim($campos[0]);
            $instante = trim($campos[1] ?? '');

            /*
             * A linha malformada é descartada pelo FORMATO, não por captura
             * de exceção. A diferença importa: um `catch` genérico aqui
             * engoliria também um defeito nosso — e o sintoma seria a
             * academia inteira parar de registrar passagens sem um único erro
             * no log. Melhor falhar alto do que perder movimento em silêncio.
             */
            if ($pin === '' || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $instante) !== 1) {
                continue;
            }

            // O instante vem na hora LOCAL do aparelho, sem fuso.
            $momento = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $instante, config('app.timezone'));

            $registros[] = new RegistroDePassagem(
                pin: $pin,
                ocorreuEm: $momento,
                status: (int) trim($campos[2] ?? '255'),
                metodo: (int) trim($campos[3] ?? '0'),
            );
        }

        return $registros;
    }

    /**
     * A chave que impede a passagem duplicada.
     *
     * O ATTLOG não traz identificador próprio, e o aparelho REENVIA o lote
     * inteiro sempre que não recebe "OK". Sem esta chave num índice único,
     * uma oscilação de rede vira uma fileira de passagens fantasma.
     *
     * Risco residual assumido: duas passagens do mesmo aluno no MESMO segundo
     * colapsam em uma. Numa catraca que gira uma pessoa por vez, é
     * fisicamente improvável.
     */
    public static function chaveDeOrigem(string $serie, RegistroDePassagem $registro): string
    {
        return hash('sha256', implode('|', [
            $serie,
            $registro->pin,
            $registro->ocorreuEm->format('Y-m-d H:i:s'),
            $registro->status,
            $registro->metodo,
        ]));
    }

    /**
     * Campos `chave=valor` separados por TAB, com o prefixo já removido.
     *
     * As chaves voltam em minúsculas porque o aparelho não é consistente na
     * caixa — comparar direto é como o cadastro facial some sem erro nenhum.
     *
     * @return array<string, string>
     */
    public static function campos(string $linha): array
    {
        $campos = [];

        foreach (explode("\t", $linha) as $pedaco) {
            $posicao = strpos($pedaco, '=');

            if ($posicao === false) {
                continue;
            }

            $chave = strtolower(trim(substr($pedaco, 0, $posicao)));
            $campos[$chave] = trim(substr($pedaco, $posicao + 1), " \r\n");
        }

        return $campos;
    }

    /**
     * Separa o prefixo (BIODATA, USER, FP, FACE, OPLOG) do resto da linha.
     *
     * @return array{string, string} prefixo em maiúsculas e o restante
     */
    public static function prefixo(string $linha): array
    {
        $linha = trim($linha);
        $espaco = strpos($linha, ' ');

        if ($espaco === false) {
            return [strtoupper($linha), ''];
        }

        return [
            strtoupper(substr($linha, 0, $espaco)),
            substr($linha, $espaco + 1),
        ];
    }

    /**
     * A ficha do aparelho (`table=options`): `~chave=valor,chave=valor`.
     *
     * @return array<string, string>
     */
    public static function ficha(string $corpo): array
    {
        $ficha = [];

        foreach (explode(',', trim($corpo)) as $pedaco) {
            $posicao = strpos($pedaco, '=');

            if ($posicao === false) {
                continue;
            }

            $chave = ltrim(trim(substr($pedaco, 0, $posicao)), '~');

            if ($chave !== '') {
                $ficha[$chave] = trim(substr($pedaco, $posicao + 1));
            }
        }

        return $ficha;
    }

    /**
     * O ACK de um comando: `ID=1&Return=0&CMD=DATA`.
     *
     * @return list<array{id: int, retorno: int}>
     */
    public static function lerConfirmacoes(string $corpo): array
    {
        preg_match_all('/ID=(\d+)[^\n]*?Return=(-?\d+)/i', $corpo, $achados, PREG_SET_ORDER);

        return array_map(
            static fn (array $achado): array => [
                'id' => (int) $achado[1],
                'retorno' => (int) $achado[2],
            ],
            $achados,
        );
    }

    /** @return list<string> */
    private static function linhas(string $corpo): array
    {
        $linhas = preg_split('/\r\n|\r|\n/', trim($corpo)) ?: [];

        return array_values(array_filter(
            array_map('trim', $linhas),
            static fn (string $linha): bool => $linha !== '',
        ));
    }
}
