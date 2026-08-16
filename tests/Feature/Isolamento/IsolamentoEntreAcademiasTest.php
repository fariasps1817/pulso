<?php

declare(strict_types=1);

namespace Tests\Feature\Isolamento;

use App\Models\Academia;
use App\Models\Aluno;
use App\Support\Academia\ContextoAcademia;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O teste que sustenta a decisão de arquitetura inteira.
 *
 * O isolamento entre academias tem duas camadas: o filtro do Eloquent (do dia
 * a dia) e a política de Row Level Security no PostgreSQL (a rede de
 * proteção). Os casos abaixo verificam sobretudo a SEGUNDA — porque a
 * primeira, sozinha, cai no dia em que alguém escrever uma consulta crua.
 */
final class IsolamentoEntreAcademiasTest extends TestCase
{
    use DatabaseTransactions;

    private Academia $alpha;

    private Academia $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = Academia::factory()->create(['nome' => 'Alpha Fit']);
        $this->beta = Academia::factory()->create(['nome' => 'Body Tech']);
    }

    private function contexto(): ContextoAcademia
    {
        return app(ContextoAcademia::class);
    }

    /**
     * Antes de mais nada: se a conexão de teste fosse superusuária ou tivesse
     * BYPASSRLS, todos os outros casos passariam sem provar coisa alguma.
     */
    public function test_a_conexao_da_aplicacao_esta_sujeita_ao_rls(): void
    {
        $papel = DB::selectOne('SELECT current_user AS usuario, rolsuper, rolbypassrls
            FROM pg_roles WHERE rolname = current_user');

        $this->assertFalse((bool) $papel->rolsuper, "O papel {$papel->usuario} é superusuário e ignoraria o RLS.");
        $this->assertFalse((bool) $papel->rolbypassrls, "O papel {$papel->usuario} tem BYPASSRLS.");
    }

    public function test_aluno_de_uma_academia_nao_aparece_na_outra(): void
    {
        $this->contexto()->definir($this->alpha->id);
        $aluno = Aluno::factory()->create(['nome' => 'Jose Maria da Silva']);

        $this->contexto()->definir($this->beta->id);

        $this->assertNull(Aluno::find($aluno->id));
        $this->assertSame(0, Aluno::count());
    }

    /**
     * O caso que justifica o RLS existir.
     *
     * `withoutGlobalScopes()` derruba o filtro da aplicação de propósito — é o
     * que aconteceria por descuido numa consulta crua. O banco continua
     * recusando.
     */
    public function test_o_banco_recusa_mesmo_sem_o_filtro_da_aplicacao(): void
    {
        $this->contexto()->definir($this->alpha->id);
        $aluno = Aluno::factory()->create();

        $this->contexto()->definir($this->beta->id);

        $this->assertSame(0, Aluno::withoutGlobalScopes()->count());
        $this->assertSame(0, DB::table('alunos')->count());
        $this->assertNull(DB::table('alunos')->find($aluno->id));
    }

    /**
     * WITH CHECK: não basta impedir a leitura, é preciso impedir a gravação em
     * academia alheia. Sem isso, um INSERT com academia_id de outra passaria.
     */
    public function test_nao_e_possivel_gravar_em_academia_alheia(): void
    {
        $this->contexto()->definir($this->alpha->id);

        $this->expectException(QueryException::class);

        DB::table('alunos')->insert([
            'academia_id' => $this->beta->id,
            'nome' => 'Invasor',
            'cpf' => '12345678909',
            'data_nascimento' => '1990-01-01',
            'whatsapp' => '85999999999',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Falha fechando: sem academia definida (console, filas, super
     * administrador), a política não casa com linha nenhuma. O padrão seguro
     * é não ver nada, e não ver tudo.
     */
    public function test_sem_academia_definida_nada_e_visivel(): void
    {
        $this->contexto()->definir($this->alpha->id);
        Aluno::factory()->create();

        $this->contexto()->limpar();

        $this->assertSame(0, DB::table('alunos')->count());
    }

    /**
     * O super administrador opera só o plano de controle. Ele enxerga as
     * academias, mas nenhum dado de dentro delas — e isso vale mesmo se a
     * conta for comprometida, porque não há exceção na política.
     */
    public function test_super_administrador_nao_enxerga_dado_de_academia(): void
    {
        $this->contexto()->definir($this->alpha->id);
        Aluno::factory()->create();

        // Super administrador: academia_id nulo.
        $this->contexto()->definir(null);

        $this->assertSame(0, DB::table('alunos')->count());
        // Mas continua enxergando o plano de controle.
        $this->assertGreaterThanOrEqual(2, DB::table('academias')->count());
    }

    public function test_atualizacao_nao_alcanca_linha_de_outra_academia(): void
    {
        $this->contexto()->definir($this->alpha->id);
        $aluno = Aluno::factory()->create(['nome' => 'Original']);

        $this->contexto()->definir($this->beta->id);
        $afetadas = DB::table('alunos')->where('id', $aluno->id)->update(['nome' => 'Alterado']);

        $this->assertSame(0, $afetadas);

        $this->contexto()->definir($this->alpha->id);
        $this->assertSame('Original', DB::table('alunos')->where('id', $aluno->id)->value('nome'));
    }

    public function test_exclusao_nao_alcanca_linha_de_outra_academia(): void
    {
        $this->contexto()->definir($this->alpha->id);
        $aluno = Aluno::factory()->create();

        $this->contexto()->definir($this->beta->id);
        $this->assertSame(0, DB::table('alunos')->where('id', $aluno->id)->delete());

        $this->contexto()->definir($this->alpha->id);
        $this->assertNotNull(DB::table('alunos')->find($aluno->id));
    }

    /** O academia_id é preenchido sozinho: ninguém precisa lembrar. */
    public function test_academia_id_e_preenchido_a_partir_do_contexto(): void
    {
        $this->contexto()->definir($this->beta->id);

        $aluno = Aluno::factory()->create();

        $this->assertSame($this->beta->id, $aluno->academia_id);
    }

    /**
     * Todas as tabelas de domínio precisam estar protegidas. Se alguém criar
     * uma nova e esquecer a política, este caso denuncia.
     */
    public function test_todas_as_tabelas_de_dominio_tem_rls_ativo_e_forcado(): void
    {
        $tabelas = [
            'profissionais', 'alunos', 'planos', 'matriculas', 'mensalidades',
            'cobrancas', 'pagamentos', 'consentimentos_lgpd', 'credenciais_acesso',
            'dispositivos_acesso', 'acessos', 'notificacoes',
        ];

        foreach ($tabelas as $tabela) {
            $info = DB::selectOne(
                'SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE relname = ?',
                [$tabela],
            );

            $this->assertTrue((bool) $info->relrowsecurity, "RLS desativado em {$tabela}.");
            $this->assertTrue(
                (bool) $info->relforcerowsecurity,
                "FORCE ROW LEVEL SECURITY ausente em {$tabela}: o dono da tabela ignoraria a política.",
            );

            $politicas = DB::select('SELECT policyname FROM pg_policies WHERE tablename = ?', [$tabela]);
            $this->assertNotEmpty($politicas, "Nenhuma política de isolamento em {$tabela}.");
        }
    }
}
