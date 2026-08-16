<?php

declare(strict_types=1);

namespace App\Services\Catraca;

use Carbon\CarbonImmutable;

/**
 * A hora no formato da ZKTeco — que NÃO é epoch Unix.
 *
 * O aparelho conta segundos desde 2000-01-01 usando um calendário fictício de
 * 12 meses de 31 dias. Todo mês tem 31 dias; 30 de fevereiro existe nessa
 * contagem. Não é um erro do fabricante: é uma aritmética que dispensa saber
 * o tamanho de cada mês.
 *
 * Trocar por `time()` "porque dá quase no mesmo" faz o relógio do aparelho
 * andar errado por dias — e como o instante do ponto vem do relógio DELE,
 * todo registro nasce com a hora errada. É por isso que esta classe existe
 * sozinha, com teste próprio, em vez de virar duas linhas dentro do
 * controller.
 */
final class RelogioZk
{
    private const SEGUNDOS_POR_DIA = 86400;

    /**
     * O corpo da resposta de sincronização de hora.
     *
     * O aparelho ajusta o próprio relógio por aqui — e o `MachineTZ`/`ServerTZ`
     * é o que o informa do fuso, já que o número em si vem compensado.
     */
    public static function respostaDeSincronizacao(?CarbonImmutable $momento = null): string
    {
        $momento ??= CarbonImmutable::now();
        $fuso = self::fusoFormatado($momento);

        return sprintf(
            'DateTime=%d,MachineTZ=%s,ServerTZ=%s',
            self::segundos($momento),
            $fuso,
            $fuso,
        );
    }

    /**
     * Segundos desde 2000 no calendário fictício de 12 x 31 dias.
     *
     * A compensação de fuso é somada nas horas: para -0300, somam-se 3 horas,
     * devolvendo o equivalente em UTC. O aparelho reconstrói o horário local
     * a partir do `MachineTZ` que vai junto.
     */
    public static function segundos(CarbonImmutable $momento): int
    {
        $dias = ($momento->year - 2000) * 12 * 31
            + ($momento->month - 1) * 31
            + ($momento->day - 1);

        $compensacao = -intdiv($momento->utcOffset(), 60);

        $horas = ($momento->hour + $compensacao) * 60 + $momento->minute;

        return $dias * self::SEGUNDOS_POR_DIA + $horas * 60 + $momento->second;
    }

    /** O fuso como o protocolo quer: `-0300`. */
    public static function fusoFormatado(?CarbonImmutable $momento = null): string
    {
        return ($momento ?? CarbonImmutable::now())->format('O');
    }

    /** O fuso como inteiro, para o bloco de opções do handshake: `-3`. */
    public static function fusoEmHoras(?CarbonImmutable $momento = null): int
    {
        return intdiv(($momento ?? CarbonImmutable::now())->utcOffset(), 60);
    }
}
