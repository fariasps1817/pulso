<?php

declare(strict_types=1);

namespace Tests\Feature\Planos;

use App\Livewire\Planos\Detalhes;
use App\Livewire\Planos\Formulario;
use App\Livewire\Planos\Lista;
use App\Models\Aluno;
use App\Models\Matricula;
use App\Models\Plano;
use App\Support\Academia\ContextoAcademia;
use Livewire\Livewire;
use Tests\ContextoDeAcademia;

final class PlanosTest extends ContextoDeAcademia
{
    // -----------------------------------------------------------------
    // Lista
    // -----------------------------------------------------------------

    public function test_lista_mostra_os_planos_ativos(): void
    {
        Plano::factory()->create(['nome' => 'Mensal Musculação']);
        Plano::factory()->create(['nome' => 'Plano Antigo', 'ativo' => false]);

        $this->actingAs($this->usuarioCom('dono'))
            ->get(route('planos.lista'))
            ->assertOk()
            ->assertSee('Mensal Musculação')
            ->assertDontSee('Plano Antigo');
    }

    public function test_filtro_mostra_os_desativados(): void
    {
        Plano::factory()->create(['nome' => 'Plano Antigo', 'ativo' => false]);

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Lista::class)
            ->set('situacao', 'inativos')
            ->assertSee('Plano Antigo');
    }

    /** O dono olha a lista para saber quantos alunos cada plano tem. */
    public function test_lista_conta_alunos_com_matricula_vigente(): void
    {
        $plano = Plano::factory()->create(['nome' => 'Mensal Musculação']);

        Matricula::factory()->count(2)->create([
            'unidade_id' => $this->unidade->id,
            'plano_id' => $plano->id,
        ]);

        Matricula::factory()->encerrada()->create([
            'unidade_id' => $this->unidade->id,
            'plano_id' => $plano->id,
        ]);

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Lista::class)
            // Duas vigentes; a encerrada não conta.
            ->assertSeeInOrder(['Mensal Musculação', '2']);
    }

    // -----------------------------------------------------------------
    // Formulário
    // -----------------------------------------------------------------

    /**
     * O campo de dinheiro entrega "129,90". Mandar isso direto para uma coluna
     * numeric gravaria 129 — ou estouraria. A conversão é o ponto do teste.
     */
    public function test_cadastra_plano_convertendo_o_valor_brasileiro(): void
    {
        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Formulario::class)
            ->set('nome', 'Mensal Musculação')
            ->set('valor_mensal', '1.234,56')
            ->set('duracao_meses', '1')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertSame('1234.56', Plano::first()->valor_mensal);
    }

    public function test_valor_zero_e_recusado(): void
    {
        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Formulario::class)
            ->set('nome', 'Plano Grátis')
            ->set('valor_mensal', '0,00')
            ->call('salvar')
            ->assertHasErrors('valor_mensal');
    }

    public function test_nome_repetido_na_academia_e_recusado(): void
    {
        Plano::factory()->create(['nome' => 'Mensal Musculação']);

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Formulario::class)
            ->set('nome', 'Mensal Musculação')
            ->set('valor_mensal', '129,90')
            ->call('salvar')
            ->assertHasErrors('nome');
    }

    /** O teto de 30 dias de experiência foi combinado no documento de domínio. */
    public function test_experiencia_acima_de_30_dias_e_recusada(): void
    {
        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Formulario::class)
            ->set('nome', 'Plano Teste')
            ->set('valor_mensal', '129,90')
            ->set('dias_experiencia', '45')
            ->call('salvar')
            ->assertHasErrors('dias_experiencia');
    }

    public function test_edita_plano(): void
    {
        $plano = Plano::factory()->create(['nome' => 'Nome Antigo']);

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Formulario::class, ['plano' => $plano])
            ->assertSet('nome', 'Nome Antigo')
            ->set('nome', 'Nome Novo')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertSame('Nome Novo', $plano->fresh()->nome);
    }

    /**
     * Reajustar o plano NÃO pode mudar o que as matrículas já cobram: o valor
     * foi copiado na contratação justamente para isso.
     */
    public function test_reajustar_o_plano_nao_altera_matricula_existente(): void
    {
        $plano = Plano::factory()->create(['valor_mensal' => 129.90]);

        $matricula = Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'plano_id' => $plano->id,
            'valor_mensal' => 129.90,
        ]);

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Formulario::class, ['plano' => $plano])
            ->set('valor_mensal', '159,90')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertSame('159.90', $plano->fresh()->valor_mensal);
        $this->assertSame('129.90', $matricula->fresh()->valor_mensal);
    }

    // -----------------------------------------------------------------
    // Detalhes e situação
    // -----------------------------------------------------------------

    public function test_desativar_plano(): void
    {
        $plano = Plano::factory()->create();

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Detalhes::class, ['plano' => $plano])
            ->call('alternarAtivo');

        $this->assertFalse((bool) $plano->fresh()->ativo);
    }

    /**
     * Plano usado em matrícula não se exclui: as matrículas apontam para ele e
     * o histórico precisa saber o que foi contratado.
     */
    public function test_plano_com_matricula_nao_pode_ser_excluido(): void
    {
        $plano = Plano::factory()->create();

        Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => Aluno::factory()->create()->id,
            'plano_id' => $plano->id,
        ]);

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Detalhes::class, ['plano' => $plano])
            ->call('excluir')
            ->assertForbidden();

        $this->assertNotSoftDeleted($plano);
    }

    public function test_plano_nunca_usado_pode_ser_excluido(): void
    {
        $plano = Plano::factory()->create();

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Detalhes::class, ['plano' => $plano])
            ->call('excluir');

        $this->assertSoftDeleted($plano);
    }

    // -----------------------------------------------------------------
    // Autorização
    // -----------------------------------------------------------------

    /** Recepção consulta os planos — precisa para matricular —, mas não define preço. */
    public function test_recepcao_ve_a_lista_mas_nao_cria_plano(): void
    {
        Plano::factory()->create(['nome' => 'Mensal Musculação']);

        $this->actingAs($this->usuarioCom('recepcao'))
            ->get(route('planos.lista'))
            ->assertOk()
            ->assertSee('Mensal Musculação')
            ->assertDontSee('Novo plano');

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Formulario::class)
            ->assertForbidden();
    }

    public function test_professor_nao_acessa_planos(): void
    {
        $this->actingAs($this->usuarioCom('professor'))
            ->get(route('planos.lista'))
            ->assertForbidden();
    }

    public function test_nao_abre_plano_de_outra_academia(): void
    {
        $alheio = $this->naOutraAcademia(fn () => Plano::factory()->create());
        $usuario = $this->usuarioCom('dono');

        app(ContextoAcademia::class)->limpar();

        $this->actingAs($usuario)
            ->get(route('planos.detalhes', $alheio))
            ->assertNotFound();
    }
}
