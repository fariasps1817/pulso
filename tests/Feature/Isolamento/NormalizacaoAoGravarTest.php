<?php

declare(strict_types=1);

namespace Tests\Feature\Isolamento;

use App\Models\Academia;
use App\Models\Aluno;
use App\Support\Academia\ContextoAcademia;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A normalização acontece ao GRAVAR, e é isso que estes casos verificam —
 * lendo o valor direto do banco, sem passar pelo model.
 *
 * Se ficasse só na exibição, a lista ordenada misturaria "SILVA" e "Silva", e
 * buscar por "Silva" não acharia quem foi digitado em caixa alta.
 */
final class NormalizacaoAoGravarTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $academia = Academia::factory()->create();
        app(ContextoAcademia::class)->definir($academia->id);
    }

    public function test_nome_e_gravado_em_caixa_de_titulo(): void
    {
        $aluno = Aluno::factory()->create(['nome' => 'JOSE MARIA DA SILVA']);

        $noBanco = DB::table('alunos')->where('id', $aluno->id)->value('nome');

        $this->assertSame('Jose Maria da Silva', $noBanco);
    }

    public function test_documentos_e_telefones_guardam_so_digitos(): void
    {
        $aluno = Aluno::factory()->create([
            'cpf' => '529.982.247-25',
            'whatsapp' => '(85) 99608-5960',
            'cep' => '62850-000',
        ]);

        $linha = DB::table('alunos')->where('id', $aluno->id)->first();

        $this->assertSame('52998224725', $linha->cpf);
        $this->assertSame('85996085960', $linha->whatsapp);
        $this->assertSame('62850000', $linha->cep);
    }

    /**
     * Campo opcional deixado em branco vira nulo, não string vazia: senão ele
     * ocuparia o índice único e o segundo cadastro em branco colidiria.
     */
    public function test_campo_opcional_vazio_vira_nulo(): void
    {
        $aluno = Aluno::factory()->create(['telefone' => '']);

        $this->assertNull(DB::table('alunos')->where('id', $aluno->id)->value('telefone'));
    }

    public function test_academia_e_usuario_tambem_normalizam_o_nome(): void
    {
        $academia = Academia::factory()->create(['nome' => 'academia CORPO em movimento']);

        $this->assertSame(
            'Academia Corpo em Movimento',
            DB::table('academias')->where('id', $academia->id)->value('nome'),
        );
    }

    /** A busca tolerante a erro de digitação, via pg_trgm. */
    public function test_busca_por_nome_tolera_erro_de_digitacao(): void
    {
        Aluno::factory()->create(['nome' => 'João Silva Nogueira']);

        $encontrados = Aluno::buscandoPorNome('Joao Silva')->get();

        $this->assertCount(1, $encontrados);
        $this->assertSame('João Silva Nogueira', $encontrados->first()->nome);
    }
}
