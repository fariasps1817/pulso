<?php

declare(strict_types=1);

namespace Tests\Feature\Alunos;

use App\Livewire\Alunos\Detalhes;
use App\Models\Aluno;
use App\Models\ConsentimentoLgpd;
use App\Models\CredencialAcesso;
use App\Models\Matricula;
use App\Models\Mensalidade;
use App\Models\Plano;
use Livewire\Livewire;
use Tests\ContextoDeAcademia;

final class DetalhesTest extends ContextoDeAcademia
{
    public function test_mostra_a_ficha_do_aluno(): void
    {
        $aluno = Aluno::factory()->create([
            'nome' => 'Ana Beatriz Nogueira',
            'cpf' => '52998224725',
        ]);

        $this->actingAs($this->usuarioCom('recepcao'))
            ->get(route('alunos.detalhes', $aluno))
            ->assertOk()
            ->assertSee('Ana Beatriz Nogueira')
            ->assertSee('529.982.247-25');
    }

    public function test_aluno_sem_matricula_avisa_que_nao_passa_na_catraca(): void
    {
        $aluno = Aluno::factory()->create();

        $this->actingAs($this->usuarioCom('recepcao'))
            ->get(route('alunos.detalhes', $aluno))
            ->assertSee('Sem matrícula');
    }

    public function test_mostra_o_plano_quando_ha_matricula(): void
    {
        $aluno = Aluno::factory()->create();

        Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $aluno->id,
            'plano_id' => Plano::factory()->create(['nome' => 'Mensal Musculação'])->id,
        ]);

        $this->actingAs($this->usuarioCom('recepcao'))
            ->get(route('alunos.detalhes', $aluno))
            ->assertSee('Mensal Musculação')
            ->assertSee('Matriz');
    }

    /** Menor de idade mostra o bloco do responsável. */
    public function test_menor_de_idade_mostra_o_responsavel(): void
    {
        $aluno = Aluno::factory()->menorDeIdade()->create();

        $this->actingAs($this->usuarioCom('recepcao'))
            ->get(route('alunos.detalhes', $aluno))
            ->assertSee('Responsável')
            ->assertSee($aluno->responsavel_nome);
    }

    // -----------------------------------------------------------------
    // Exclusão
    // -----------------------------------------------------------------

    /**
     * Exclusão do aluno é lógica; a do template biométrico é real.
     *
     * As duas acontecem juntas, e não em passos separados que alguém pode
     * esquecer de encadear: revogar sem apagar seria consentimento de fachada.
     */
    public function test_excluir_apaga_o_template_biometrico_de_verdade(): void
    {
        $aluno = Aluno::factory()->create();

        $consentimento = ConsentimentoLgpd::factory()->create(['aluno_id' => $aluno->id]);

        $credencial = CredencialAcesso::factory()->create([
            'aluno_id' => $aluno->id,
            'consentimento_id' => $consentimento->id,
            'tipo' => 'facial',
            'template' => 'dado-biometrico-simulado',
        ]);

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Detalhes::class, ['aluno' => $aluno])
            ->call('excluir');

        $credencial->refresh();

        $this->assertNull($credencial->template, 'O template biométrico precisa ser apagado de verdade.');
        $this->assertNotNull($credencial->excluida_em, 'A exclusão precisa ficar registrada.');
        $this->assertFalse((bool) $credencial->ativa);

        $this->assertSoftDeleted($aluno);
    }

    /** O histórico financeiro sobrevive: a mensalidade paga em março continua existindo. */
    public function test_excluir_preserva_o_historico_financeiro(): void
    {
        $aluno = Aluno::factory()->create();

        $matricula = Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $aluno->id,
            'plano_id' => Plano::factory()->create()->id,
        ]);

        $mensalidade = Mensalidade::factory()->paga()->create([
            'unidade_id' => $this->unidade->id,
            'matricula_id' => $matricula->id,
            'aluno_id' => $aluno->id,
        ]);

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Detalhes::class, ['aluno' => $aluno])
            ->call('excluir');

        $this->assertDatabaseHas('mensalidades', ['id' => $mensalidade->id]);
    }

    // -----------------------------------------------------------------
    // Autorização
    // -----------------------------------------------------------------

    public function test_recepcao_nao_exclui_aluno(): void
    {
        $aluno = Aluno::factory()->create();

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Detalhes::class, ['aluno' => $aluno])
            ->call('excluir')
            ->assertForbidden();

        $this->assertNotSoftDeleted($aluno);
    }

    public function test_professor_ve_a_ficha_mas_nao_o_botao_de_editar(): void
    {
        $aluno = Aluno::factory()->create();

        $this->actingAs($this->usuarioCom('professor'))
            ->get(route('alunos.detalhes', $aluno))
            ->assertOk()
            ->assertDontSee('Excluir aluno');
    }

    /**
     * Aluno de outra academia não existe para este usuário. O Row Level
     * Security já devolveria zero linhas; o 404 é o que a tela mostra.
     */
    public function test_nao_abre_aluno_de_outra_academia(): void
    {
        $alheio = $this->naOutraAcademia(fn () => Aluno::factory()->create());

        $this->actingAs($this->usuarioCom('dono'))
            ->get(route('alunos.detalhes', $alheio))
            ->assertNotFound();
    }
}
