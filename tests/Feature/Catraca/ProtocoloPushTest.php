<?php

declare(strict_types=1);

namespace Tests\Feature\Catraca;

use App\Enums\SituacaoComando;
use App\Models\Acesso;
use App\Models\Aluno;
use App\Models\ComandoDispositivo;
use App\Models\DispositivoAcesso;
use App\Models\Unidade;
use App\Services\Catraca\FilaDeComandos;
use App\Support\Academia\ContextoAcademia;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\ContextoDeAcademia;

/**
 * O aparelho conversando com o Pulso.
 *
 * O que se prova aqui é o contrato com o equipamento: que ele é identificado
 * pelo número de série, que nunca recebe erro, que lote reenviado não vira
 * passagem duplicada, e que a fila de comandos anda.
 */
final class ProtocoloPushTest extends ContextoDeAcademia
{
    private DispositivoAcesso $aparelho;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aparelho = DispositivoAcesso::factory()->create([
            'unidade_id' => $this->unidade->id,
            'numero_serie' => 'NYU7251903222',
        ]);
    }

    /** @param array<string, string> $parametros */
    private function url(string $caminho, array $parametros = []): string
    {
        return '/iclock/'.$caminho.'?'.http_build_query([
            'SN' => $this->aparelho->numero_serie,
            ...$parametros,
        ]);
    }

    private function passagem(int $pin, string $instante, int $metodo = 15): string
    {
        return "{$pin}\t{$instante}\t255\t{$metodo}\t0\t0\t0";
    }

    /** @return TestResponse<Response> */
    private function attlog(string $corpo): TestResponse
    {
        return $this->call('POST', $this->url('cdata', ['table' => 'ATTLOG', 'Stamp' => '9999']), content: $corpo);
    }

    // -----------------------------------------------------------------
    // Identificação
    // -----------------------------------------------------------------

    /**
     * O aparelho não tem login, não tem sessão e não sabe o que é CSRF. A
     * identidade dele é o número de série — e a academia sai dele.
     */
    public function test_o_numero_de_serie_define_a_academia_sem_ninguem_logado(): void
    {
        // Sem contexto nenhum: é exatamente a situação de uma chamada real,
        // que chega antes de existir "academia atual".
        app(ContextoAcademia::class)->limpar();

        $resposta = $this->get($this->url('cdata', ['options' => 'all']));

        $resposta->assertOk();
        $resposta->assertSee('GET OPTION FROM: NYU7251903222');
        $this->assertSame($this->academia->id, app(ContextoAcademia::class)->id());
    }

    /**
     * Série desconhecida recebe "OK" e é descartada.
     *
     * Responder erro faria o aparelho reenviar para sempre; responder
     * diferente do caso conhecido diria a quem sonda quais seriais existem.
     */
    public function test_aparelho_desconhecido_recebe_ok_e_nada_e_gravado(): void
    {
        $resposta = $this->call(
            'POST',
            '/iclock/cdata?SN=NAO-EXISTE&table=ATTLOG',
            content: $this->passagem(1, '2026-08-16 08:00:00'),
        );

        $resposta->assertOk();
        $resposta->assertSee('OK');
        $this->assertSame(0, Acesso::query()->count());
    }

    public function test_chamada_sem_numero_de_serie_e_descartada(): void
    {
        $this->get('/iclock/getrequest')->assertOk()->assertSee('OK');
    }

    /** Caminho desconhecido responde OK: um 404 seria lido como falha de rede. */
    public function test_caminho_desconhecido_ainda_responde_ok(): void
    {
        $this->get($this->url('coisa-que-nao-existe'))->assertOk()->assertSee('OK');
    }

    // -----------------------------------------------------------------
    // Passagens
    // -----------------------------------------------------------------

    public function test_registra_a_passagem_que_o_aparelho_empurra(): void
    {
        $aluno = Aluno::factory()->create(['nome' => 'Bruno Catraca']);

        $this->attlog($this->passagem($aluno->id, '2026-08-16 07:30:00'))
            ->assertOk()
            ->assertSee('OK');

        $acesso = Acesso::query()->firstOrFail();

        $this->assertSame($aluno->id, $acesso->aluno_id);
        $this->assertSame($this->aparelho->id, $acesso->dispositivo_id);
        $this->assertSame('facial', $acesso->tipo_credencial);
        $this->assertSame('2026-08-16 07:30:00', $acesso->ocorreu_em->format('Y-m-d H:i:s'));
    }

    /**
     * O caso que a idempotência existe para cobrir: o aparelho não recebeu o
     * "OK" (rede oscilou) e mandou o lote inteiro de novo.
     */
    public function test_lote_reenviado_nao_duplica_passagem(): void
    {
        $aluno = Aluno::factory()->create();
        $lote = $this->passagem($aluno->id, '2026-08-16 07:30:00');

        $this->attlog($lote)->assertOk();
        $this->attlog($lote)->assertOk();
        $this->attlog($lote)->assertOk();

        $this->assertSame(1, Acesso::query()->count());
    }

    /** PIN que não casa com ninguém vira linha mesmo assim — é a pista. */
    public function test_pin_desconhecido_vira_passagem_anonima(): void
    {
        $this->attlog($this->passagem(999999, '2026-08-16 07:30:00'))->assertOk();

        $acesso = Acesso::query()->firstOrFail();

        $this->assertNull($acesso->aluno_id);
        $this->assertSame('999999', $acesso->pin);
    }

    public function test_lote_com_varias_passagens_grava_todas(): void
    {
        $ana = Aluno::factory()->create();
        $joao = Aluno::factory()->create();

        $this->attlog(implode("\r\n", [
            $this->passagem($ana->id, '2026-08-16 07:30:00'),
            $this->passagem($joao->id, '2026-08-16 07:31:00', metodo: 1),
        ]))->assertOk();

        $this->assertSame(2, Acesso::query()->count());
        $this->assertSame('digital', Acesso::query()->where('aluno_id', $joao->id)->firstOrFail()->tipo_credencial);
    }

    // -----------------------------------------------------------------
    // Fila de comandos
    // -----------------------------------------------------------------

    public function test_o_polling_entrega_um_comando_e_o_marca_como_entregue(): void
    {
        $aluno = Aluno::factory()->create(['nome' => 'Carla Souza Lima']);
        $comando = (new FilaDeComandos($this->aparelho))->cadastrarAluno($aluno);

        $resposta = $this->get($this->url('getrequest'));

        $resposta->assertOk();
        $resposta->assertSee("C:{$comando->id}:DATA UPDATE USERINFO", escape: false);
        $this->assertSame(SituacaoComando::Entregue, $comando->fresh()->situacao);
    }

    /** Fila vazia responde "OK". Corpo vazio não existe neste protocolo. */
    public function test_fila_vazia_responde_ok(): void
    {
        $this->get($this->url('getrequest'))->assertOk()->assertSee('OK');
    }

    /**
     * Um comando por vez: com um só, cada confirmação casa com um pedido sem
     * ambiguidade.
     */
    public function test_entrega_um_comando_por_vez(): void
    {
        $fila = new FilaDeComandos($this->aparelho);
        $primeiro = $fila->cadastrarAluno(Aluno::factory()->create());
        $segundo = $fila->cadastrarAluno(Aluno::factory()->create());

        $this->get($this->url('getrequest'))->assertSee("C:{$primeiro->id}:", escape: false);

        $this->assertSame(SituacaoComando::Pendente, $segundo->fresh()->situacao);
    }

    public function test_confirmacao_com_retorno_zero_marca_o_comando_como_aplicado(): void
    {
        $comando = (new FilaDeComandos($this->aparelho))->cadastrarAluno(Aluno::factory()->create());

        $this->call('POST', $this->url('devicecmd'), content: "ID={$comando->id}&Return=0&CMD=DATA")
            ->assertOk();

        $this->assertSame(SituacaoComando::Confirmado, $comando->fresh()->situacao);
        $this->assertSame(0, $comando->fresh()->retorno);
    }

    /** Código diferente de zero é diagnóstico, e a fila não insiste. */
    public function test_confirmacao_com_erro_marca_falha_e_guarda_o_codigo(): void
    {
        $comando = (new FilaDeComandos($this->aparelho))->cadastrarAluno(Aluno::factory()->create());

        $this->call('POST', $this->url('devicecmd'), content: "ID={$comando->id}&Return=-10&CMD=DATA")
            ->assertOk();

        $this->assertSame(SituacaoComando::Falhou, $comando->fresh()->situacao);
        $this->assertSame(-10, $comando->fresh()->retorno);
    }

    /**
     * Entregue e nunca confirmado volta para a fila. É o que impede a perda
     * silenciosa quando a rede cai entre a entrega e a aplicação.
     */
    public function test_comando_entregue_sem_confirmacao_e_reenviado(): void
    {
        $comando = (new FilaDeComandos($this->aparelho))->cadastrarAluno(Aluno::factory()->create());

        // Primeira entrega: o comando sai, mas o ACK nunca chega.
        $this->get($this->url('getrequest'))->assertSee("C:{$comando->id}:", escape: false);
        $this->assertSame(SituacaoComando::Entregue, $comando->fresh()->situacao);
        $this->assertSame(1, $comando->fresh()->tentativas);

        // Enquanto o prazo não vence, o comando não volta para a fila —
        // senão o aparelho receberia o mesmo pedido a cada dois segundos.
        $this->get($this->url('getrequest'))->assertSee('OK');
        $this->assertSame(1, $comando->fresh()->tentativas);

        $comando->forceFill(['entregue_em' => CarbonImmutable::now()->subHour()])->save();

        $this->get($this->url('getrequest'))->assertSee("C:{$comando->id}:", escape: false);
        $this->assertSame(2, $comando->fresh()->tentativas);
    }

    /**
     * Nome com espaço sobrevive porque a fronteira entre campos é o TAB.
     * Trocar por espaço faria o aparelho ler o sobrenome como outro campo.
     */
    public function test_o_comando_usa_tabulacao_entre_os_campos(): void
    {
        $aluno = Aluno::factory()->create(['nome' => 'Ana Maria da Silva']);

        $comando = (new FilaDeComandos($this->aparelho))->cadastrarAluno($aluno);

        $this->assertStringContainsString("\tName=", $comando->corpo);
        $this->assertStringStartsWith('DATA UPDATE USERINFO PIN=', $comando->corpo);
    }

    /**
     * Bloquear NUNCA apaga o usuário do aparelho: `DATA DELETE USERINFO` leva
     * junto as biometrias, e o template facial nem sempre volta para nós.
     * Apagar por engano custaria trazer o aluno ao balcão de novo.
     */
    public function test_bloqueio_troca_o_grupo_e_nao_apaga_o_usuario(): void
    {
        $aluno = Aluno::factory()->create();

        $comando = (new FilaDeComandos($this->aparelho))->bloquearAluno($aluno);

        $this->assertSame('DATA UPDATE USERINFO', $comando->verbo);
        $this->assertStringContainsString("\tGrp=2", $comando->corpo);
        $this->assertStringNotContainsString('DELETE', $comando->corpo);
    }

    // -----------------------------------------------------------------
    // Aparelho
    // -----------------------------------------------------------------

    public function test_o_polling_registra_que_o_aparelho_esta_vivo(): void
    {
        $this->assertFalse($this->aparelho->online());

        $this->get($this->url('getrequest'))->assertOk();

        $this->assertTrue($this->aparelho->fresh()->online());
    }

    public function test_guarda_a_ficha_que_o_aparelho_manda(): void
    {
        $this->call('POST', $this->url('cdata', ['table' => 'options']), content: '~DeviceName=SenseFace 2A,~FWVersion=ZAM70-NF24HA-Ver3.3.12,~UserCount=42')
            ->assertOk();

        $aparelho = $this->aparelho->fresh();

        $this->assertSame('ZAM70-NF24HA-Ver3.3.12', $aparelho->firmware);
        $this->assertSame('SenseFace 2A', $aparelho->informacoes['DeviceName']);
    }

    public function test_sincroniza_a_hora_no_formato_do_fabricante(): void
    {
        $this->get($this->url('rtdata'))
            ->assertOk()
            ->assertSee('MachineTZ=-0300');
    }

    /**
     * A chave compartilhada é o que separa "meu aparelho" de "quem descobriu
     * a URL".
     */
    public function test_chave_incorreta_e_descartada(): void
    {
        $this->aparelho->update(['chave_push' => 'segredo-da-unidade']);

        $aluno = Aluno::factory()->create();

        $this->call('POST', $this->url('cdata', ['table' => 'ATTLOG', 'pushcommkey' => 'chute']),
            content: $this->passagem($aluno->id, '2026-08-16 07:30:00'))
            ->assertOk()
            ->assertSee('OK');

        $this->assertSame(0, Acesso::query()->count());

        $this->call('POST', $this->url('cdata', ['table' => 'ATTLOG', 'pushcommkey' => 'segredo-da-unidade']),
            content: $this->passagem($aluno->id, '2026-08-16 07:30:00'))
            ->assertOk();

        $this->assertSame(1, Acesso::query()->count());
    }

    /** Isolamento: o comando de uma academia não sai no polling da outra. */
    public function test_o_aparelho_so_recebe_comando_da_propria_academia(): void
    {
        $alheio = $this->naOutraAcademia(function ($outra): ComandoDispositivo {
            $unidade = Unidade::factory()->create(['academia_id' => $outra->id]);
            $aparelho = DispositivoAcesso::factory()->create([
                'academia_id' => $outra->id,
                'unidade_id' => $unidade->id,
                'numero_serie' => 'OUTRA-ACADEMIA-01',
            ]);

            return (new FilaDeComandos($aparelho))->cadastrarAluno(Aluno::factory()->create());
        });

        $this->get($this->url('getrequest'))
            ->assertOk()
            ->assertSee('OK')
            ->assertDontSee("C:{$alheio->id}:", escape: false);
    }
}
