<?php

declare(strict_types=1);

namespace Tests\Unit\Catraca;

use App\Services\Catraca\RelogioZk;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * O formato de data proprietário da ZKTeco.
 *
 * Este teste existe porque o erro aqui é silencioso: trocar a conta por
 * `time()` não quebra nada visivelmente — o aparelho aceita o número, ajusta
 * o próprio relógio para uma data errada, e passa a carimbar toda passagem
 * com a hora errada. Descobre-se dias depois, olhando um relatório.
 *
 * O valor esperado abaixo não foi calculado por mim: é o que a especificação
 * registra como verificado contra o equipamento real.
 */
final class RelogioZkTest extends TestCase
{
    /**
     * O caso conferido com o aparelho: 2026-06-22 22:15:58 em -0300 vale
     * 850958158.
     */
    public function test_reproduz_o_valor_verificado_com_o_aparelho(): void
    {
        $momento = CarbonImmutable::create(2026, 6, 22, 22, 15, 58, 'America/Fortaleza');

        $this->assertSame(850958158, RelogioZk::segundos($momento));
    }

    /**
     * O calendário do aparelho tem 12 meses de 31 dias — todos. Não é
     * descuido do fabricante: é uma aritmética que dispensa saber o tamanho
     * de cada mês. Usar o calendário real erra por dias.
     */
    public function test_todo_mes_vale_trinta_e_um_dias(): void
    {
        $marco = CarbonImmutable::create(2026, 3, 1, 0, 0, 0, 'America/Fortaleza');
        $abril = CarbonImmutable::create(2026, 4, 1, 0, 0, 0, 'America/Fortaleza');

        // Março tem 31 dias de verdade; fevereiro, 28. A conta ignora os dois.
        $this->assertSame(31 * 86400, RelogioZk::segundos($abril) - RelogioZk::segundos($marco));

        $fevereiro = CarbonImmutable::create(2026, 2, 1, 0, 0, 0, 'America/Fortaleza');

        $this->assertSame(31 * 86400, RelogioZk::segundos($marco) - RelogioZk::segundos($fevereiro));
    }

    public function test_resposta_de_sincronizacao_traz_o_fuso_nos_dois_campos(): void
    {
        $momento = CarbonImmutable::create(2026, 6, 22, 22, 15, 58, 'America/Fortaleza');

        $this->assertSame(
            'DateTime=850958158,MachineTZ=-0300,ServerTZ=-0300',
            RelogioZk::respostaDeSincronizacao($momento),
        );
    }

    /** O bloco de opções quer o fuso como inteiro: -3, não -0300. */
    public function test_fuso_em_horas_para_o_handshake(): void
    {
        $momento = CarbonImmutable::create(2026, 6, 22, 12, 0, 0, 'America/Fortaleza');

        $this->assertSame(-3, RelogioZk::fusoEmHoras($momento));
    }
}
