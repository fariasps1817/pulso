<?php

declare(strict_types=1);

namespace Tests\Feature\Acesso;

use App\Models\TentativaLogin;
use App\Models\User;
use App\Services\Acesso\PorteiroDoLogin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\ContextoDeAcademia;

/**
 * Bloqueio de tentativas, sessão única e encerramento por inatividade.
 *
 * O fio comum: cada uma dessas travas falha em silêncio se estiver errada. O
 * bloqueio que não bloqueia, a sessão única que não derruba ninguém e o
 * timeout que nunca dispara continuam com a tela igual — por isso todos
 * precisam de teste.
 */
final class SegurancaDoAcessoTest extends ContextoDeAcademia
{
    private function usuario(string $senha = 'SenhaBoa1234'): User
    {
        return $this->usuarioCom('recepcao', [
            'email' => 'balcao@alpha-fit.com.br',
            'password' => $senha,
            'deve_trocar_senha' => false,
        ]);
    }

    private function errar(string $email, int $vezes, string $ip = '10.0.0.5'): void
    {
        foreach (range(1, $vezes) as $ignorado) {
            app(PorteiroDoLogin::class)->autenticar($email, 'chute-errado', $ip);
        }
    }

    // -----------------------------------------------------------------
    // Bloqueio por tentativas
    // -----------------------------------------------------------------

    public function test_entra_com_a_senha_certa_e_registra_a_tentativa(): void
    {
        $usuario = $this->usuario();

        $autenticado = app(PorteiroDoLogin::class)
            ->autenticar($usuario->email, 'SenhaBoa1234', '10.0.0.5');

        $this->assertNotNull($autenticado);
        $this->assertSame($usuario->id, $autenticado->id);
        $this->assertNotNull($usuario->fresh()->ultimo_acesso_em);

        $this->assertTrue(TentativaLogin::query()->where('email', $usuario->email)->firstOrFail()->sucesso);
    }

    /** Cinco erros no mesmo e-mail fecham a porta, mesmo com a senha certa depois. */
    public function test_bloqueia_o_e_mail_depois_de_cinco_erros(): void
    {
        $usuario = $this->usuario();
        $porteiro = app(PorteiroDoLogin::class);

        $this->errar($usuario->email, 5);

        $this->assertTrue($porteiro->bloqueado($usuario->email, '10.0.0.5'));
        $this->assertNull(
            $porteiro->autenticar($usuario->email, 'SenhaBoa1234', '10.0.0.5'),
            'Com a porta fechada, nem a senha certa entra.',
        );
    }

    /**
     * O IP tem limite mais alto: uma academia inteira sai por um só, e errar
     * senha algumas vezes por dia é rotina.
     */
    public function test_o_ip_so_bloqueia_num_limite_bem_maior(): void
    {
        $porteiro = app(PorteiroDoLogin::class);

        // Vinte erros espalhados por e-mails diferentes, do mesmo IP.
        foreach (range(1, 20) as $indice) {
            $porteiro->autenticar("pessoa{$indice}@exemplo.com", 'chute', '10.0.0.9');
        }

        $this->assertTrue($porteiro->bloqueado('ninguem@exemplo.com', '10.0.0.9'));
        // De outro IP, a mesma conta continua liberada.
        $this->assertFalse($porteiro->bloqueado('ninguem@exemplo.com', '10.0.0.10'));
    }

    /**
     * A contagem é por TEXTO DIGITADO, não por conta encontrada. Se o
     * bloqueio só valesse para e-mail existente, a diferença entre "senha
     * incorreta" e "muitas tentativas" viraria um confirmador de contas.
     */
    public function test_e_mail_inexistente_tambem_e_contado(): void
    {
        $this->errar('nao-existe@lugar-nenhum.com', 5);

        $this->assertTrue(
            app(PorteiroDoLogin::class)->bloqueado('nao-existe@lugar-nenhum.com', '10.0.0.5'),
        );
    }

    /** Alternar maiúsculas não zera a contagem. */
    public function test_a_contagem_ignora_a_caixa_do_e_mail(): void
    {
        $this->errar('Balcao@Alpha-Fit.com.br', 3);
        $this->errar('BALCAO@ALPHA-FIT.COM.BR', 2);

        $this->assertTrue(
            app(PorteiroDoLogin::class)->bloqueado('balcao@alpha-fit.com.br', '10.0.0.5'),
        );
    }

    /** A janela é deslizante: sem rotina de desbloqueio, ninguém fica preso. */
    public function test_a_porta_reabre_sozinha_passada_a_janela(): void
    {
        $usuario = $this->usuario();
        $porteiro = app(PorteiroDoLogin::class);

        $this->errar($usuario->email, 5);
        $this->assertTrue($porteiro->bloqueado($usuario->email, '10.0.0.5'));

        $this->travel(16)->minutes();

        $this->assertFalse($porteiro->bloqueado($usuario->email, '10.0.0.5'));
        $this->assertNotNull($porteiro->autenticar($usuario->email, 'SenhaBoa1234', '10.0.0.5'));
    }

    /** A tentativa barrada também entra na trilha — senão o histórico mente. */
    public function test_a_tentativa_bloqueada_fica_registrada(): void
    {
        $this->errar('alvo@alpha-fit.com.br', 7);

        $this->assertSame(7, TentativaLogin::query()->where('email', 'alvo@alpha-fit.com.br')->count());
    }

    /** A tela de login não diz quanto tempo sem ter o que dizer. */
    public function test_informa_quantos_minutos_faltam(): void
    {
        $this->errar('alvo@alpha-fit.com.br', 5);

        $minutos = app(PorteiroDoLogin::class)->minutosParaLiberar('alvo@alpha-fit.com.br', '10.0.0.5');

        $this->assertGreaterThan(0, $minutos);
        $this->assertLessThanOrEqual(15, $minutos);
    }

    // -----------------------------------------------------------------
    // Login pela tela
    // -----------------------------------------------------------------

    public function test_a_tela_de_login_usa_o_porteiro(): void
    {
        $usuario = $this->usuario();

        $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'SenhaBoa1234',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($usuario);
        $this->assertSame(1, TentativaLogin::query()->where('sucesso', true)->count());
    }

    public function test_a_mensagem_de_bloqueio_nao_revela_se_a_conta_existe(): void
    {
        $usuario = $this->usuario();

        $this->errar($usuario->email, 5, '127.0.0.1');
        $this->errar('inventado@lugar-nenhum.com', 5, '127.0.0.1');

        $this->post('/login', ['email' => $usuario->email, 'password' => 'x'])
            ->assertSessionHasErrors('email');

        $paraContaExistente = session('errors')->first('email');

        $this->post('/login', ['email' => 'inventado@lugar-nenhum.com', 'password' => 'x'])
            ->assertSessionHasErrors('email');

        $paraContaInventada = session('errors')->first('email');

        $this->assertSame(
            $paraContaExistente,
            $paraContaInventada,
            'A mensagem precisa ser idêntica, senão a tela vira confirmador de contas.',
        );
    }

    /**
     * E-mail atendido por duas contas: o login RECUSA, em vez de escolher.
     *
     * O banco permite o mesmo e-mail em academias diferentes — o índice único
     * é `(academia_id, email)`. Mas o login recebe só o e-mail. Pegar a
     * primeira colocaria alguém dentro dos dados da academia errada, que é
     * falha de isolamento na prática ainda que o banco esteja correto.
     *
     * Aconteceu de verdade no banco local, depois de o seeder rodar duas vezes.
     */
    public function test_e_mail_em_duas_academias_nao_entra_em_nenhuma(): void
    {
        $daqui = $this->usuario();

        $deLa = $this->naOutraAcademia(fn ($outra) => User::factory()
            ->daAcademia($outra->id)
            ->create(['email' => $daqui->email, 'password' => 'SenhaBoa1234']));

        $this->assertNotSame($daqui->id, $deLa->id);

        $this->assertNull(
            app(PorteiroDoLogin::class)->autenticar($daqui->email, 'SenhaBoa1234', '10.0.0.5'),
            'Com o e-mail ambíguo, entrar na academia errada é pior do que não entrar.',
        );
    }

    // -----------------------------------------------------------------
    // Sessão única
    // -----------------------------------------------------------------

    /**
     * Sem isto, uma senha emprestada "só para ver uma coisa" vira um segundo
     * acesso permanente — e ninguém percebe, porque nada deixa de funcionar.
     */
    public function test_sessao_unica_derruba_o_outro_aparelho(): void
    {
        // A suíte roda com sessão em memória; a varredura só existe com a
        // sessão no banco, que é o que a VPS usa.
        config(['session.driver' => 'database']);

        $usuario = $this->usuario();

        $this->assertTrue($usuario->sessao_unica);

        // A sessão que já existia noutro aparelho.
        DB::table('sessions')->insert([
            'id' => 'sessao-do-outro-aparelho',
            'user_id' => $usuario->id,
            'ip_address' => '10.0.0.99',
            'user_agent' => 'Outro',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->post('/login', ['email' => $usuario->email, 'password' => 'SenhaBoa1234']);

        $this->assertDatabaseMissing('sessions', ['id' => 'sessao-do-outro-aparelho']);
    }

    public function test_sem_sessao_unica_os_dois_aparelhos_continuam(): void
    {
        config(['session.driver' => 'database']);

        $usuario = $this->usuario();
        $usuario->update(['sessao_unica' => false]);

        DB::table('sessions')->insert([
            'id' => 'sessao-que-fica',
            'user_id' => $usuario->id,
            'ip_address' => '10.0.0.99',
            'user_agent' => 'Outro',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->post('/login', ['email' => $usuario->email, 'password' => 'SenhaBoa1234']);

        $this->assertDatabaseHas('sessions', ['id' => 'sessao-que-fica']);
    }

    // -----------------------------------------------------------------
    // Inatividade
    // -----------------------------------------------------------------

    /**
     * O caso real: computador de balcão, num salão com cem pessoas por dia.
     */
    public function test_encerra_a_sessao_parada_alem_do_limite(): void
    {
        $usuario = $this->usuarioCom('recepcao', [
            'minutos_inatividade' => 10,
            'deve_trocar_senha' => false,
        ]);

        $this->actingAs($usuario)->get(route('alunos.lista'))->assertOk();

        $this->travel(11)->minutes();

        $this->get(route('alunos.lista'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** Quem está usando não é interrompido no meio do atendimento. */
    public function test_o_relogio_reinicia_a_cada_uso(): void
    {
        $usuario = $this->usuarioCom('recepcao', [
            'minutos_inatividade' => 10,
            'deve_trocar_senha' => false,
        ]);

        $this->actingAs($usuario)->get(route('alunos.lista'))->assertOk();

        // Uso a cada oito minutos, por meia hora: nunca encerra.
        foreach (range(1, 4) as $ignorado) {
            $this->travel(8)->minutes();
            $this->get(route('alunos.lista'))->assertOk();
        }

        $this->assertAuthenticated();
    }

    /** Zero desliga — a máquina trancada da sala da direção não precisa disso. */
    public function test_zero_minutos_desliga_o_encerramento(): void
    {
        $usuario = $this->usuarioCom('recepcao', [
            'minutos_inatividade' => 0,
            'deve_trocar_senha' => false,
        ]);

        $this->actingAs($usuario)->get(route('alunos.lista'))->assertOk();

        $this->travel(5)->hours();

        $this->get(route('alunos.lista'))->assertOk();
    }

    public function test_sem_limite_proprio_vale_o_padrao_de_trinta_minutos(): void
    {
        $usuario = $this->usuarioCom('recepcao', [
            'minutos_inatividade' => null,
            'deve_trocar_senha' => false,
        ]);

        $this->actingAs($usuario)->get(route('alunos.lista'))->assertOk();

        $this->travel(25)->minutes();
        $this->get(route('alunos.lista'))->assertOk();

        $this->travel(31)->minutes();
        $this->get(route('alunos.lista'))->assertRedirect(route('login'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }
}
