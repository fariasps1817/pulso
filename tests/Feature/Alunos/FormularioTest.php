<?php

declare(strict_types=1);

namespace Tests\Feature\Alunos;

use App\Livewire\Alunos\Formulario;
use App\Models\Aluno;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\ContextoDeAcademia;

final class FormularioTest extends ContextoDeAcademia
{
    /**
     * @param  array<string, string>  $sobrescreve
     * @return array<string, string>
     */
    private function dadosValidos(array $sobrescreve = []): array
    {
        return [
            'nome' => 'jose maria DA silva',
            'cpf' => '529.982.247-25',
            'data_nascimento' => '15/08/1990',
            'whatsapp' => '(85) 99608-5960',
            ...$sobrescreve,
        ];
    }

    /** @return Testable<Component> */
    private function formulario(string $papel = 'recepcao'): Testable
    {
        return Livewire::actingAs($this->usuarioCom($papel))->test(Formulario::class);
    }

    // -----------------------------------------------------------------
    // Gravação
    // -----------------------------------------------------------------

    public function test_cadastra_aluno_com_dados_minimos(): void
    {
        $this->formulario()
            ->set($this->dadosValidos())
            ->call('salvar')
            ->assertHasNoErrors();

        $aluno = Aluno::firstWhere('cpf', '52998224725');

        $this->assertNotNull($aluno);
        // Nome normalizado ao gravar, documento e telefone só com dígitos.
        $this->assertSame('Jose Maria da Silva', $aluno->nome);
        $this->assertSame('85996085960', $aluno->whatsapp);
        $this->assertSame($this->academia->id, $aluno->academia_id);
    }

    public function test_edita_aluno_existente(): void
    {
        $aluno = Aluno::factory()->create(['nome' => 'Nome Antigo']);

        Livewire::actingAs($this->usuarioCom('recepcao'))
            ->test(Formulario::class, ['aluno' => $aluno])
            ->assertSet('nome', 'Nome Antigo')
            ->set('nome', 'Nome Novo')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertSame('Nome Novo', $aluno->fresh()->nome);
    }

    // -----------------------------------------------------------------
    // Validação
    // -----------------------------------------------------------------

    public function test_cpf_invalido_e_recusado(): void
    {
        $this->formulario()
            ->set($this->dadosValidos(['cpf' => '111.111.111-11']))
            ->call('salvar')
            ->assertHasErrors('cpf');
    }

    public function test_cpf_repetido_na_mesma_academia_e_recusado(): void
    {
        Aluno::factory()->create(['cpf' => '52998224725']);

        $this->formulario()
            ->set($this->dadosValidos())
            ->call('salvar')
            ->assertHasErrors('cpf');
    }

    /** O mesmo CPF em outra academia é outro cadastro, e é permitido. */
    public function test_cpf_repetido_em_outra_academia_e_permitido(): void
    {
        $this->naOutraAcademia(fn () => Aluno::factory()->create(['cpf' => '52998224725']));

        $this->formulario()
            ->set($this->dadosValidos())
            ->call('salvar')
            ->assertHasNoErrors();
    }

    public function test_campos_obrigatorios(): void
    {
        $this->formulario()
            ->set(['nome' => '', 'cpf' => '', 'data_nascimento' => '', 'whatsapp' => ''])
            ->call('salvar')
            ->assertHasErrors(['nome', 'cpf', 'data_nascimento', 'whatsapp']);
    }

    public function test_data_inexistente_e_recusada(): void
    {
        $this->formulario()
            ->set($this->dadosValidos(['data_nascimento' => '31/02/2000']))
            ->call('salvar')
            ->assertHasErrors('data_nascimento');
    }

    public function test_data_no_futuro_e_recusada(): void
    {
        $this->formulario()
            ->set($this->dadosValidos(['data_nascimento' => now()->addYear()->format('d/m/Y')]))
            ->call('salvar')
            ->assertHasErrors('data_nascimento');
    }

    /** O teto de 99 anos pega o erro mais comum do balcão: trocar o ano. */
    public function test_idade_acima_de_99_anos_e_recusada(): void
    {
        $this->formulario()
            ->set($this->dadosValidos(['data_nascimento' => '15/08/1899']))
            ->call('salvar')
            ->assertHasErrors('data_nascimento');
    }

    public function test_idade_abaixo_da_minima_da_academia_e_recusada(): void
    {
        $this->academia->update(['idade_minima' => 12]);

        $this->formulario()
            ->set($this->dadosValidos(['data_nascimento' => now()->subYears(10)->format('d/m/Y')]))
            ->call('salvar')
            ->assertHasErrors('data_nascimento');
    }

    // -----------------------------------------------------------------
    // Responsável
    // -----------------------------------------------------------------

    public function test_menor_de_idade_exige_responsavel(): void
    {
        $this->formulario()
            ->set($this->dadosValidos(['data_nascimento' => now()->subYears(14)->format('d/m/Y')]))
            ->call('salvar')
            ->assertHasErrors([
                'responsavel_nome',
                'responsavel_cpf',
                'responsavel_telefone',
                'responsavel_parentesco',
            ]);
    }

    public function test_menor_de_idade_com_responsavel_e_aceito(): void
    {
        $this->formulario()
            ->set($this->dadosValidos([
                'data_nascimento' => now()->subYears(14)->format('d/m/Y'),
                'responsavel_nome' => 'Maria da Silva',
                'responsavel_cpf' => '529.982.247-25',
                'responsavel_telefone' => '(85) 98888-7777',
                'responsavel_parentesco' => 'Mãe',
            ], ))
            ->set('cpf', '111.444.777-35')
            ->call('salvar')
            ->assertHasNoErrors();
    }

    /** Maior de idade não vê o bloco de responsável — seria ruído. */
    public function test_bloco_de_responsavel_so_aparece_para_menor(): void
    {
        $componente = $this->formulario();

        $componente->set('data_nascimento', '15/08/1990')->assertDontSee('Responsável');
        $componente->set('data_nascimento', now()->subYears(14)->format('d/m/Y'))->assertSee('Responsável');
    }

    // -----------------------------------------------------------------
    // Endereço
    // -----------------------------------------------------------------

    public function test_cep_preenche_o_endereco(): void
    {
        Http::fake(['viacep.com.br/*' => Http::response([
            'logradouro' => 'Rua das Flores',
            'bairro' => 'Centro',
            'localidade' => 'Cascavel',
            'uf' => 'CE',
        ])]);

        $this->formulario()
            ->set('cep', '62850-000')
            ->assertSet('logradouro', 'Rua das Flores')
            ->assertSet('bairro', 'Centro')
            ->assertSet('cidade', 'Cascavel')
            ->assertSet('uf', 'CE');
    }

    /**
     * A regra que não se quebra: CEP não encontrado avisa, mas não impede o
     * cadastro. Aluno em rua nova não pode ficar sem cadastro porque uma API
     * pública não conhece o endereço dele.
     */
    public function test_cep_desconhecido_avisa_mas_nao_trava(): void
    {
        Http::fake(['viacep.com.br/*' => Http::response(['erro' => true])]);

        $this->formulario()
            ->set('cep', '00000-000')
            ->assertSet('avisoCep', 'CEP não encontrado. Preencha o endereço à mão.')
            ->set($this->dadosValidos())
            ->call('salvar')
            ->assertHasNoErrors();
    }

    public function test_viacep_fora_do_ar_nao_trava_o_cadastro(): void
    {
        Http::fake(['viacep.com.br/*' => Http::response('', 500)]);

        $this->formulario()
            ->set('cep', '62850-000')
            ->set($this->dadosValidos())
            ->call('salvar')
            ->assertHasNoErrors();
    }

    public function test_trocar_de_estado_limpa_a_cidade(): void
    {
        $this->formulario()
            ->set('uf', 'CE')
            ->set('cidade', 'Fortaleza')
            ->set('uf', 'SP')
            ->assertSet('cidade', '');
    }

    // -----------------------------------------------------------------
    // Autorização
    // -----------------------------------------------------------------

    public function test_professor_nao_cadastra_aluno(): void
    {
        Livewire::actingAs($this->usuarioCom('professor'))
            ->test(Formulario::class)
            ->assertForbidden();
    }

    public function test_professor_nao_edita_aluno(): void
    {
        $aluno = Aluno::factory()->create();

        Livewire::actingAs($this->usuarioCom('professor'))
            ->test(Formulario::class, ['aluno' => $aluno])
            ->assertForbidden();
    }
}
