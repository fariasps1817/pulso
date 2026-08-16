<?php

declare(strict_types=1);

namespace Tests\Feature\Alunos;

use App\Http\Middleware\DefinirAcademiaAtual;
use App\Models\Aluno;
use App\Support\Academia\ContextoAcademia;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Tests\ContextoDeAcademia;

/**
 * A rota /alunos/{aluno} devolvia 404 para aluno existente e da própria
 * academia.
 *
 * CAUSA
 * `SubstituteBindings` é quem transforma "1" no model Aluno, e essa busca já
 * passa pelas políticas de Row Level Security. O middleware que define a
 * academia atual estava registrado DEPOIS dele, então o banco devolvia zero
 * linhas e a rota nem chegava ao componente.
 *
 * POR QUE OS TESTES ANTERIORES NÃO PEGARAM
 * A base de teste define o contexto no setUp, e ele sobrevive até a
 * requisição — mascarando a ordem errada. Estes casos LIMPAM o contexto
 * antes de chamar a rota, que é a situação real de uma requisição nova.
 */
final class RotaDeDetalhesTest extends ContextoDeAcademia
{
    /** Simula requisição nova: sem contexto herdado do preparo do teste. */
    private function comoRequisicaoNova(): void
    {
        app(ContextoAcademia::class)->limpar();
    }

    public function test_abre_a_ficha_do_aluno_em_requisicao_nova(): void
    {
        $aluno = Aluno::factory()->create(['nome' => 'Ana Beatriz Nogueira']);
        $usuario = $this->usuarioCom('recepcao');

        $this->comoRequisicaoNova();

        $this->actingAs($usuario)
            ->get(route('alunos.detalhes', $aluno))
            ->assertOk()
            ->assertSee('Ana Beatriz Nogueira');
    }

    public function test_abre_a_edicao_em_requisicao_nova(): void
    {
        $aluno = Aluno::factory()->create(['nome' => 'Carlos Eduardo Lima']);
        $usuario = $this->usuarioCom('recepcao');

        $this->comoRequisicaoNova();

        $this->actingAs($usuario)
            ->get(route('alunos.editar', $aluno))
            ->assertOk()
            ->assertSee('Carlos Eduardo Lima');
    }

    /** O isolamento continua valendo: aluno alheio segue devolvendo 404. */
    public function test_aluno_de_outra_academia_continua_dando_404(): void
    {
        $alheio = $this->naOutraAcademia(fn () => Aluno::factory()->create());
        $usuario = $this->usuarioCom('dono');

        $this->comoRequisicaoNova();

        $this->actingAs($usuario)
            ->get(route('alunos.detalhes', $alheio))
            ->assertNotFound();
    }

    /**
     * A garantia estrutural: se alguém reordenar o middleware, este caso
     * denuncia antes de virar 404 na tela do cliente.
     */
    public function test_academia_e_definida_antes_da_resolucao_do_model(): void
    {
        $ordem = app('router')->gatherRouteMiddleware(
            app('router')->getRoutes()->getByName('alunos.detalhes'),
        );

        $posicaoContexto = array_search(DefinirAcademiaAtual::class, $ordem, true);
        $posicaoBinding = array_search(SubstituteBindings::class, $ordem, true);

        $this->assertNotFalse($posicaoContexto, 'O middleware da academia não está na rota.');
        $this->assertNotFalse($posicaoBinding, 'SubstituteBindings não está na rota.');
        $this->assertLessThan(
            $posicaoBinding,
            $posicaoContexto,
            'A academia precisa ser definida ANTES da resolução do model: '
            .'a busca do aluno passa pelo Row Level Security e devolveria zero linhas.',
        );
    }
}
