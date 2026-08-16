<?php

declare(strict_types=1);

namespace Tests\Feature\Alunos;

use App\Livewire\Alunos\Lista;
use App\Models\Aluno;
use App\Models\Matricula;
use App\Models\Plano;
use App\Models\Unidade;
use Livewire\Livewire;
use Tests\ContextoDeAcademia;

final class ListaTest extends ContextoDeAcademia
{
    public function test_lista_exige_autenticacao(): void
    {
        $this->get(route('alunos.lista'))->assertRedirect(route('login'));
    }

    public function test_professor_ve_a_lista(): void
    {
        Aluno::factory()->create(['nome' => 'Ana Beatriz Nogueira']);

        $this->actingAs($this->usuarioCom('professor'))
            ->get(route('alunos.lista'))
            ->assertOk()
            ->assertSee('Ana Beatriz Nogueira');
    }

    /** Professor não cadastra aluno — a matriz de permissões não lhe dá isso. */
    public function test_professor_nao_ve_o_botao_de_novo_aluno(): void
    {
        $this->actingAs($this->usuarioCom('professor'))
            ->get(route('alunos.lista'))
            ->assertDontSee('Novo aluno');
    }

    public function test_recepcao_ve_o_botao_de_novo_aluno(): void
    {
        $this->actingAs($this->usuarioCom('recepcao'))
            ->get(route('alunos.lista'))
            ->assertSee('Novo aluno');
    }

    public function test_busca_por_nome(): void
    {
        Aluno::factory()->create(['nome' => 'Ana Beatriz Nogueira']);
        Aluno::factory()->create(['nome' => 'Carlos Eduardo Lima']);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Lista::class)
            ->set('termo', 'Ana')
            ->assertSee('Ana Beatriz Nogueira')
            ->assertDontSee('Carlos Eduardo Lima');
    }

    /** A busca tolera erro de digitação, via pg_trgm. */
    public function test_busca_tolera_erro_de_digitacao(): void
    {
        Aluno::factory()->create(['nome' => 'João Silva Nogueira']);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Lista::class)
            ->set('termo', 'Joao Silva')
            ->assertSee('João Silva Nogueira');
    }

    /**
     * Digitou número, procura CPF. A recepção não deveria ter de escolher em
     * qual campo pesquisar: ela tem o aluno na frente e o documento na mão.
     */
    public function test_busca_por_cpf(): void
    {
        $aluno = Aluno::factory()->create(['nome' => 'Marina Sousa Vieira']);
        Aluno::factory()->create(['nome' => 'Carlos Eduardo Lima']);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Lista::class)
            ->set('termo', substr((string) $aluno->cpf, 0, 6))
            ->assertSee('Marina Sousa Vieira')
            ->assertDontSee('Carlos Eduardo Lima');
    }

    public function test_filtra_por_matriculados_e_sem_matricula(): void
    {
        $comMatricula = Aluno::factory()->create(['nome' => 'Ana Beatriz Nogueira']);
        Aluno::factory()->create(['nome' => 'Carlos Eduardo Lima']);

        Matricula::factory()->create([
            'unidade_id' => Unidade::factory()->create()->id,
            'aluno_id' => $comMatricula->id,
            'plano_id' => Plano::factory()->create()->id,
        ]);

        $componente = Livewire::actingAs($this->usuarioCom('recepcao'))->test(Lista::class);

        $componente->set('situacao', 'ativos')
            ->assertSee('Ana Beatriz Nogueira')
            ->assertDontSee('Carlos Eduardo Lima');

        $componente->set('situacao', 'sem_matricula')
            ->assertSee('Carlos Eduardo Lima')
            ->assertDontSee('Ana Beatriz Nogueira');
    }

    /** Filtro novo recomeça da primeira página — senão a busca cai numa página vazia. */
    public function test_buscar_volta_para_a_primeira_pagina(): void
    {
        Aluno::factory()->count(30)->create();

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Lista::class)
            ->set('paginators.page', 2)
            ->set('termo', 'a')
            ->assertSet('paginators.page', 1);
    }

    public function test_estado_vazio_quando_nao_ha_aluno(): void
    {
        $this->actingAs($this->usuarioCom('recepcao'))
            ->get(route('alunos.lista'))
            ->assertSee('Nenhum aluno cadastrado ainda');
    }

    public function test_estado_vazio_da_busca_e_diferente_do_estado_inicial(): void
    {
        Aluno::factory()->create(['nome' => 'Ana Beatriz Nogueira']);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Lista::class)
            ->set('termo', 'Zzzzzz')
            ->assertSee('Nenhum aluno encontrado')
            ->assertDontSee('Nenhum aluno cadastrado ainda');
    }

    /**
     * O isolamento vale também aqui: aluno de outra academia não aparece nem
     * na busca.
     */
    public function test_nao_lista_aluno_de_outra_academia(): void
    {
        $daOutra = $this->naOutraAcademia(
            fn () => Aluno::factory()->create(['nome' => 'Aluno da Concorrente']),
        );

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Lista::class)
            ->set('termo', 'Concorrente')
            ->assertDontSee('Aluno da Concorrente');

        /*
         * O registro existe de fato — o que não existe é o acesso a ele.
         *
         * `assertDatabaseHas` não serve aqui: ele consulta pela conexão da
         * aplicação, que está sujeita ao Row Level Security e devolve vazio.
         * A verificação precisa acontecer dentro do contexto da outra
         * academia, e o fato de ser assim já é a prova do isolamento.
         */
        $this->naOutraAcademia(fn () => $this->assertTrue($daOutra->exists()));
    }
}
