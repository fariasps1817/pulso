<?php

declare(strict_types=1);

namespace Tests\Feature\Matriculas;

use App\Enums\SituacaoMatricula;
use App\Enums\TipoMatricula;
use App\Livewire\Matriculas\Detalhes;
use App\Livewire\Matriculas\Formulario;
use App\Livewire\Matriculas\Lista;
use App\Models\Aluno;
use App\Models\Matricula;
use App\Models\Plano;
use App\Models\Unidade;
use Carbon\CarbonImmutable;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\ContextoDeAcademia;

final class MatriculasTest extends ContextoDeAcademia
{
    /** @return Testable<Component> */
    private function novaMatricula(string $papel = 'recepcao'): Testable
    {
        return Livewire::actingAs($this->usuarioCom($papel))->test(Formulario::class);
    }

    // -----------------------------------------------------------------
    // Criação
    // -----------------------------------------------------------------

    public function test_cria_matricula_regular(): void
    {
        $aluno = Aluno::factory()->create();
        $plano = Plano::factory()->create(['valor_mensal' => 129.90, 'duracao_meses' => 1]);

        $this->novaMatricula()
            ->set('aluno_id', $aluno->id)
            ->set('plano_id', $plano->id)
            ->set('unidade_id', $this->unidade->id)
            ->set('tipo', TipoMatricula::Regular->value)
            ->set('valor_mensal', '129,90')
            ->call('salvar')
            ->assertHasNoErrors();

        $matricula = Matricula::first();

        $this->assertSame(SituacaoMatricula::Ativa, $matricula->situacao);
        $this->assertNotNull($matricula->contrato_assinado_em);
        $this->assertSame('129.90', $matricula->valor_mensal);
        // Plano mensal não tem fim previsto: corre até alguém encerrar.
        $this->assertNull($matricula->fim_previsto_em);
    }

    /** Plano com prazo calcula o fim a partir do início. */
    public function test_plano_com_prazo_calcula_o_fim_previsto(): void
    {
        $plano = Plano::factory()->create(['duracao_meses' => 12]);

        $this->novaMatricula()
            ->set('aluno_id', Aluno::factory()->create()->id)
            ->set('plano_id', $plano->id)
            ->set('unidade_id', $this->unidade->id)
            ->set('inicio_em', '10/01/2026')
            ->set('valor_mensal', '99,00')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertSame('2027-01-10', Matricula::first()->fim_previsto_em->toDateString());
    }

    public function test_experiencia_nao_exige_contrato(): void
    {
        $plano = Plano::factory()->create(['dias_experiencia' => 7, 'sessoes_experiencia' => 3]);

        $this->novaMatricula()
            ->set('aluno_id', Aluno::factory()->create()->id)
            ->set('plano_id', $plano->id)
            ->set('unidade_id', $this->unidade->id)
            ->set('tipo', TipoMatricula::Experiencia->value)
            ->set('valor_mensal', '129,90')
            ->call('salvar')
            ->assertHasNoErrors();

        $matricula = Matricula::first();

        $this->assertSame(SituacaoMatricula::Experiencia, $matricula->situacao);
        $this->assertNull($matricula->contrato_assinado_em);
    }

    /** Matrícula regular sem contrato não entra por caminho nenhum. */
    public function test_matricula_regular_exige_contrato(): void
    {
        $this->novaMatricula()
            ->set('aluno_id', Aluno::factory()->create()->id)
            ->set('plano_id', Plano::factory()->create()->id)
            ->set('unidade_id', $this->unidade->id)
            ->set('tipo', TipoMatricula::Regular->value)
            ->set('contrato_assinado_em', '')
            ->set('valor_mensal', '129,90')
            ->call('salvar')
            ->assertHasErrors('contrato_assinado_em');
    }

    public function test_dia_de_vencimento_acima_de_28_e_recusado(): void
    {
        $this->novaMatricula()
            ->set('aluno_id', Aluno::factory()->create()->id)
            ->set('plano_id', Plano::factory()->create()->id)
            ->set('unidade_id', $this->unidade->id)
            ->set('dia_vencimento', '31')
            ->set('valor_mensal', '129,90')
            ->call('salvar')
            ->assertHasErrors('dia_vencimento');
    }

    /**
     * A constraint EXCLUDE do banco é a barreira; aqui verificamos que o erro
     * chega traduzido em vez de estourar SQL na cara de quem atende.
     */
    public function test_matricula_sobreposta_mostra_mensagem_legivel(): void
    {
        $aluno = Aluno::factory()->create();
        $plano = Plano::factory()->create();

        Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $aluno->id,
            'plano_id' => $plano->id,
            'inicio_em' => CarbonImmutable::now()->subMonth()->toDateString(),
        ]);

        $this->novaMatricula()
            ->set('aluno_id', $aluno->id)
            ->set('plano_id', $plano->id)
            ->set('unidade_id', $this->unidade->id)
            ->set('valor_mensal', '129,90')
            ->call('salvar')
            ->assertHasErrors('aluno_id');
    }

    /** Não basta a unidade existir: precisa ser uma que o usuário opera. */
    public function test_nao_matricula_em_unidade_fora_do_alcance(): void
    {
        $outraUnidade = Unidade::factory()->create(['academia_id' => $this->academia->id]);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Formulario::class)
            ->set('aluno_id', Aluno::factory()->create()->id)
            ->set('plano_id', Plano::factory()->create()->id)
            ->set('unidade_id', $outraUnidade->id)
            ->set('valor_mensal', '129,90')
            ->call('salvar')
            ->assertHasErrors('unidade_id');
    }

    /** Trocar de plano puxa o valor, que fica editável para desconto negociado. */
    public function test_escolher_plano_preenche_o_valor(): void
    {
        $plano = Plano::factory()->create(['valor_mensal' => 199.90]);

        $this->novaMatricula()
            ->set('plano_id', $plano->id)
            ->assertSet('valor_mensal', '199,90');
    }

    // -----------------------------------------------------------------
    // Transições
    // -----------------------------------------------------------------

    public function test_converte_experiencia_em_matricula(): void
    {
        $matricula = Matricula::factory()->emExperiencia()->create([
            'unidade_id' => $this->unidade->id,
            'plano_id' => Plano::factory()->create(['duracao_meses' => 12])->id,
        ]);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['matricula' => $matricula])
            ->set('contrato_assinado_em', CarbonImmutable::now()->format('d/m/Y'))
            ->set('dia_vencimento', '10')
            ->call('converter')
            ->assertHasNoErrors();

        $matricula->refresh();

        $this->assertSame(TipoMatricula::Regular, $matricula->tipo);
        $this->assertSame(SituacaoMatricula::Ativa, $matricula->situacao);
        $this->assertSame(10, $matricula->dia_vencimento);
        $this->assertNotNull($matricula->contrato_assinado_em);
        // A vigência recomeça na conversão: o teste acabou.
        $this->assertSame(CarbonImmutable::now()->toDateString(), $matricula->inicio_em->toDateString());
    }

    public function test_converter_exige_contrato(): void
    {
        $matricula = Matricula::factory()->emExperiencia()->create([
            'unidade_id' => $this->unidade->id,
        ]);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['matricula' => $matricula])
            ->set('contrato_assinado_em', '')
            ->call('converter')
            ->assertHasErrors('contrato_assinado_em');
    }

    public function test_tranca_e_reativa(): void
    {
        $matricula = Matricula::factory()->create(['unidade_id' => $this->unidade->id]);

        $componente = Livewire::actingAs($this->usuarioCom('gerente'))
            ->test(Detalhes::class, ['matricula' => $matricula]);

        $componente->call('suspender');
        $this->assertSame(SituacaoMatricula::Suspensa, $matricula->fresh()->situacao);

        $componente->call('reativar');
        $this->assertSame(SituacaoMatricula::Ativa, $matricula->fresh()->situacao);
    }

    public function test_encerra_com_motivo(): void
    {
        $matricula = Matricula::factory()->create(['unidade_id' => $this->unidade->id]);

        Livewire::actingAs($this->usuarioCom('gerente'))
            ->test(Detalhes::class, ['matricula' => $matricula])
            ->set('motivo_encerramento', 'Mudou de cidade.')
            ->call('encerrar');

        $matricula->refresh();

        $this->assertSame(SituacaoMatricula::Encerrada, $matricula->situacao);
        $this->assertNotNull($matricula->encerrada_em);
        $this->assertSame('Mudou de cidade.', $matricula->motivo_encerramento);
    }

    /**
     * Cada transição confere de onde se está saindo: sem isso, um duplo
     * clique ou uma aba antiga faria uma matrícula encerrada voltar a ativa.
     */
    public function test_matricula_encerrada_nao_volta_a_ativa(): void
    {
        $matricula = Matricula::factory()->encerrada()->create(['unidade_id' => $this->unidade->id]);

        Livewire::actingAs($this->usuarioCom('gerente'))
            ->test(Detalhes::class, ['matricula' => $matricula])
            ->call('reativar');

        $this->assertSame(SituacaoMatricula::Encerrada, $matricula->fresh()->situacao);
    }

    // -----------------------------------------------------------------
    // Autorização
    // -----------------------------------------------------------------

    /** Encerrar fica com gerente e dono: desfazer exige recriar a matrícula. */
    public function test_recepcao_nao_encerra_matricula(): void
    {
        $matricula = Matricula::factory()->create(['unidade_id' => $this->unidade->id]);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['matricula' => $matricula])
            ->call('encerrar')
            ->assertForbidden();

        $this->assertSame(SituacaoMatricula::Ativa, $matricula->fresh()->situacao);
    }

    /** Professor vê a matrícula, mas não o dinheiro. */
    public function test_professor_ve_a_matricula_sem_valores(): void
    {
        $matricula = Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'valor_mensal' => 129.90,
        ]);

        $this->actingAs($this->usuarioCom('professor'))
            ->get(route('matriculas.detalhes', $matricula))
            ->assertOk()
            ->assertSee($matricula->aluno->nome)
            ->assertDontSee('129,90')
            ->assertSee('Valores visíveis apenas para quem cuida do financeiro');
    }

    public function test_recepcao_ve_os_valores(): void
    {
        $matricula = Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'valor_mensal' => 129.90,
        ]);

        $this->actingAs($this->usuarioCom('recepcao'))
            ->get(route('matriculas.detalhes', $matricula))
            ->assertSee('129,90');
    }

    /** A lista só traz matrículas das unidades que o usuário opera. */
    public function test_lista_respeita_o_alcance_de_unidades(): void
    {
        $outraUnidade = Unidade::factory()->create([
            'academia_id' => $this->academia->id,
            'nome' => 'Filial Distante',
        ]);

        $daMinha = Matricula::factory()->create(['unidade_id' => $this->unidade->id]);
        $daOutra = Matricula::factory()->create(['unidade_id' => $outraUnidade->id]);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Lista::class)
            ->assertSee($daMinha->aluno->nome)
            ->assertDontSee($daOutra->aluno->nome);
    }
}
