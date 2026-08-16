<?php

declare(strict_types=1);

namespace Tests\Unit\Catraca;

use App\Services\Catraca\Protocolo;
use App\Services\Catraca\RegistroDePassagem;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * A leitura do que o aparelho manda.
 *
 * Cada caso aqui corresponde a uma armadilha de formato descoberta com
 * tráfego real — e todas falham em silêncio, sem exceção nenhuma: o dado
 * simplesmente não chega, ou chega torto.
 */
final class ProtocoloTest extends TestCase
{
    /** O separador é TAB. Nomes têm espaço, então espaço não pode separar. */
    public function test_le_uma_passagem_com_campos_separados_por_tabulacao(): void
    {
        $passagens = Protocolo::lerPassagens("7\t2026-06-22 22:15:58\t255\t15\t0\t0\t0");

        $this->assertCount(1, $passagens);
        $this->assertSame('7', $passagens[0]->pin);
        $this->assertSame('2026-06-22 22:15:58', $passagens[0]->ocorreuEm->format('Y-m-d H:i:s'));
        $this->assertSame(255, $passagens[0]->status);
        $this->assertSame('facial', $passagens[0]->credencial());
    }

    public function test_le_um_lote_com_varias_linhas(): void
    {
        $corpo = "7\t2026-06-22 22:15:58\t255\t15\r\n8\t2026-06-22 22:16:10\t255\t1\r\n";

        $passagens = Protocolo::lerPassagens($corpo);

        $this->assertCount(2, $passagens);
        $this->assertSame('digital', $passagens[1]->credencial());
    }

    /**
     * O método 0 parece "senha" e é "identificação automática". Confundir os
     * dois faz o relatório dizer que o aluno digitou uma senha que ele nunca
     * teve.
     */
    public function test_metodo_zero_e_identificacao_automatica_e_nao_senha(): void
    {
        $automatico = new RegistroDePassagem('1', CarbonImmutable::now(), metodo: 0);
        $senha = new RegistroDePassagem('1', CarbonImmutable::now(), metodo: 3);

        $this->assertSame('automatico', $automatico->credencial());
        $this->assertSame('senha', $senha->credencial());
    }

    public function test_linha_incompleta_e_ignorada_em_vez_de_derrubar_o_lote(): void
    {
        $corpo = "\n7\t2026-06-22 22:15:58\t255\t15\nlixo\n\t\n";

        $this->assertCount(1, Protocolo::lerPassagens($corpo));
    }

    /**
     * A chave de idempotência: o aparelho reenvia o lote inteiro quando não
     * recebe "OK", e o ATTLOG não traz identificador próprio.
     */
    public function test_a_mesma_passagem_produz_sempre_a_mesma_chave(): void
    {
        $registro = new RegistroDePassagem(
            '7',
            CarbonImmutable::create(2026, 6, 22, 22, 15, 58, 'America/Fortaleza'),
            255,
            15,
        );

        $primeira = Protocolo::chaveDeOrigem('NYU7251903222', $registro);
        $segunda = Protocolo::chaveDeOrigem('NYU7251903222', $registro);

        $this->assertSame($primeira, $segunda);

        // Aparelhos diferentes não colidem, nem que a passagem seja idêntica.
        $this->assertNotSame($primeira, Protocolo::chaveDeOrigem('OUTRO123', $registro));
    }

    /**
     * O aparelho não é consistente na caixa das chaves: `FP` manda `Size`,
     * `FACE` manda `SIZE`. Comparar sensível a maiúsculas faz o cadastro
     * facial sumir sem erro nenhum.
     */
    public function test_chaves_sao_lidas_sem_distinguir_maiusculas(): void
    {
        $digital = Protocolo::campos("PIN=1\tFID=6\tSize=1024\tValid=1");
        $face = Protocolo::campos("PIN=1\tFID=0\tSIZE=2048\tVALID=1");

        $this->assertSame('1024', $digital['size']);
        $this->assertSame('2048', $face['size']);
        $this->assertSame('1', $face['valid']);
    }

    /** O único espaço do formato: entre o prefixo e o primeiro campo. */
    public function test_separa_o_prefixo_do_resto_da_linha(): void
    {
        [$prefixo, $resto] = Protocolo::prefixo("BIODATA Pin=1\tNo=6\tType=1");

        $this->assertSame('BIODATA', $prefixo);
        $this->assertSame("Pin=1\tNo=6\tType=1", $resto);
    }

    /** Valor com espaço sobrevive, porque a fronteira é o TAB. */
    public function test_nome_com_espaco_nao_e_partido(): void
    {
        $campos = Protocolo::campos("PIN=1\tName=Ana Maria da Silva\tPri=0");

        $this->assertSame('Ana Maria da Silva', $campos['name']);
    }

    public function test_le_a_ficha_do_aparelho_tirando_o_til(): void
    {
        $ficha = Protocolo::ficha('~DeviceName=SenseFace 2A,~FWVersion=ZAM70-NF24HA-Ver3.3.12,UserCount=42');

        $this->assertSame('SenseFace 2A', $ficha['DeviceName']);
        $this->assertSame('ZAM70-NF24HA-Ver3.3.12', $ficha['FWVersion']);
        $this->assertSame('42', $ficha['UserCount']);
    }

    public function test_le_a_confirmacao_de_um_comando(): void
    {
        $confirmacoes = Protocolo::lerConfirmacoes("ID=12&Return=0&CMD=DATA\nID=13&Return=-10&CMD=DATA");

        $this->assertSame([
            ['id' => 12, 'retorno' => 0],
            ['id' => 13, 'retorno' => -10],
        ], $confirmacoes);
    }

    /**
     * `Realtime=1` é o que faz cada passagem subir na hora. Sem ele, a tela
     * mostraria movimento de meia hora atrás. E omitir um token do
     * `TransFlag` é o bastante para nunca receber aquele tipo de dado.
     */
    public function test_o_handshake_pede_tempo_real_e_habilita_os_tipos_de_dado(): void
    {
        $opcoes = Protocolo::opcoes('NYU7251903222', 10);

        $this->assertStringStartsWith("GET OPTION FROM: NYU7251903222\n", $opcoes);
        $this->assertStringContainsString("\nRealtime=1\n", $opcoes);
        $this->assertStringContainsString("\nDelay=10\n", $opcoes);
        $this->assertStringContainsString('EnrollFP', $opcoes);
        $this->assertStringContainsString('FACE', $opcoes);
    }
}
