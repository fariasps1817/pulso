<?php

declare(strict_types=1);

namespace Tests\Feature\Isolamento;

use App\Models\Academia;
use App\Models\Aluno;
use App\Models\Matricula;
use App\Models\Mensalidade;
use App\Models\Plano;
use App\Models\Unidade;
use App\Support\Academia\ContextoAcademia;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regras de negócio que vivem no banco, e não só na tela.
 *
 * Cada uma existe porque validação de formulário não cobre importação,
 * correção manual, rotina em fila nem console. O que o banco recusa, ninguém
 * consegue gravar por caminho nenhum.
 */
final class RegrasNoBancoTest extends TestCase
{
    use DatabaseTransactions;

    private Academia $academia;

    private Unidade $unidade;

    private Plano $plano;

    private Aluno $aluno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academia = Academia::factory()->create();
        app(ContextoAcademia::class)->definir($this->academia->id);

        $this->unidade = Unidade::factory()->create();
        $this->plano = Plano::factory()->create();
        $this->aluno = Aluno::factory()->create();
    }

    // -----------------------------------------------------------------
    // Matrícula
    // -----------------------------------------------------------------

    public function test_dia_de_vencimento_acima_de_28_e_recusado(): void
    {
        $this->expectException(QueryException::class);

        Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $this->aluno->id,
            'plano_id' => $this->plano->id,
            // Dia 31 não existe em fevereiro. O limite de 28 elimina o caso em
            // vez de exigir uma regra de ajuste que a recepção teria de
            // explicar toda vez.
            'dia_vencimento' => 31,
        ]);
    }

    public function test_matricula_regular_sem_contrato_assinado_e_recusada(): void
    {
        $this->expectException(QueryException::class);

        Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $this->aluno->id,
            'plano_id' => $this->plano->id,
            'contrato_assinado_em' => null,
        ]);
    }

    public function test_experiencia_pode_existir_sem_contrato(): void
    {
        $matricula = Matricula::factory()->emExperiencia()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $this->aluno->id,
            'plano_id' => $this->plano->id,
        ]);

        $this->assertNull($matricula->contrato_assinado_em);
    }

    /**
     * A constraint EXCLUDE com btree_gist. No MySQL isso viraria trigger ou
     * torcida — é um dos motivos concretos da escolha do PostgreSQL.
     */
    public function test_aluno_nao_pode_ter_duas_matriculas_sobrepostas_na_mesma_unidade(): void
    {
        $comum = [
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $this->aluno->id,
            'plano_id' => $this->plano->id,
            'inicio_em' => now()->subMonth()->toDateString(),
            'fim_previsto_em' => now()->addMonths(6)->toDateString(),
        ];

        Matricula::factory()->create($comum);

        $this->expectException(QueryException::class);

        Matricula::factory()->create($comum);
    }

    public function test_matricula_encerrada_libera_o_periodo_para_uma_nova(): void
    {
        Matricula::factory()->encerrada()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $this->aluno->id,
            'plano_id' => $this->plano->id,
            'inicio_em' => now()->subMonths(6)->toDateString(),
            'encerrada_em' => now()->subDays(10)->toDateString(),
        ]);

        $nova = Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $this->aluno->id,
            'plano_id' => $this->plano->id,
            'inicio_em' => now()->subDays(5)->toDateString(),
        ]);

        $this->assertTrue($nova->exists);
        $this->assertSame(2, Matricula::where('aluno_id', $this->aluno->id)->count());
    }

    // -----------------------------------------------------------------
    // Mensalidade
    // -----------------------------------------------------------------

    /**
     * É o que torna a rotina diária de geração idempotente: rodar duas vezes
     * no mesmo dia não duplica, porque o banco não deixa.
     */
    public function test_nao_existe_mais_de_uma_mensalidade_por_competencia(): void
    {
        $matricula = Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $this->aluno->id,
            'plano_id' => $this->plano->id,
        ]);

        $comum = [
            'unidade_id' => $this->unidade->id,
            'matricula_id' => $matricula->id,
            'aluno_id' => $this->aluno->id,
            'competencia' => now()->startOfMonth()->toDateString(),
        ];

        Mensalidade::factory()->create($comum);

        $this->expectException(QueryException::class);

        Mensalidade::factory()->create($comum);
    }

    public function test_pagamento_com_valor_zero_e_recusado(): void
    {
        $matricula = Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $this->aluno->id,
            'plano_id' => $this->plano->id,
        ]);

        $mensalidade = Mensalidade::factory()->create([
            'unidade_id' => $this->unidade->id,
            'matricula_id' => $matricula->id,
            'aluno_id' => $this->aluno->id,
        ]);

        $this->expectException(QueryException::class);

        DB::table('pagamentos')->insert([
            'academia_id' => $this->academia->id,
            'mensalidade_id' => $mensalidade->id,
            'valor' => 0,
            'forma' => 'dinheiro',
            'recebido_em' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // -----------------------------------------------------------------
    // LGPD
    // -----------------------------------------------------------------

    /**
     * Biometria sem consentimento registrado é recusada pelo banco.
     *
     * Deixar isso só na tela significaria que uma importação de base antiga,
     * ou um comando de console, poderia gravar dado sensível sem o aceite que
     * a LGPD (art. 11) exige.
     */
    public function test_credencial_biometrica_exige_consentimento(): void
    {
        $this->expectException(QueryException::class);

        DB::table('credenciais_acesso')->insert([
            'academia_id' => $this->academia->id,
            'aluno_id' => $this->aluno->id,
            'tipo' => 'facial',
            'consentimento_id' => null,
            'cadastrada_em' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** O cartão é a alternativa não-biométrica: não exige consentimento. */
    public function test_cartao_dispensa_consentimento(): void
    {
        DB::table('credenciais_acesso')->insert([
            'academia_id' => $this->academia->id,
            'aluno_id' => $this->aluno->id,
            'tipo' => 'cartao',
            'identificador_cartao' => '0001234567',
            'consentimento_id' => null,
            'cadastrada_em' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, DB::table('credenciais_acesso')
            ->where('aluno_id', $this->aluno->id)
            ->where('tipo', 'cartao')
            ->count());
    }

    // -----------------------------------------------------------------
    // Unicidade
    // -----------------------------------------------------------------

    public function test_cpf_e_unico_dentro_da_academia(): void
    {
        $cpf = $this->aluno->cpf;

        $this->expectException(QueryException::class);

        Aluno::factory()->create(['cpf' => $cpf]);
    }

    /** O mesmo CPF pode existir em duas academias: são cadastros distintos. */
    public function test_mesmo_cpf_pode_existir_em_outra_academia(): void
    {
        $cpf = $this->aluno->cpf;

        $outra = Academia::factory()->create();
        app(ContextoAcademia::class)->definir($outra->id);

        $aluno = Aluno::factory()->create(['cpf' => $cpf]);

        $this->assertSame($cpf, $aluno->cpf);
        $this->assertSame($outra->id, $aluno->academia_id);
    }
}
