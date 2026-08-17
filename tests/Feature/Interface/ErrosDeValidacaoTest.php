<?php

declare(strict_types=1);

namespace Tests\Feature\Interface;

use App\Livewire\Administracao\NovaAcademia;
use App\Livewire\Alunos\Formulario;
use App\Models\Academia;
use App\Models\Aluno;
use App\Models\User;
use Livewire\Livewire;
use Tests\ContextoDeAcademia;

/**
 * Corrigir o campo tem que apagar a crítica.
 *
 * O ERRO QUE ESTES CASOS EXISTEM PARA PEGAR
 *
 * Os formulários validam com `Validator::make(...)->validate()`, e não com
 * `$this->validate()`. A diferença é silenciosa e cara: o Livewire só troca o
 * saco de erros quando uma ValidationException é lançada. Passando a
 * validação, o saco ANTERIOR continua lá.
 *
 * No cadastro de aluno isso travava a tela para sempre: existe uma guarda
 * `if ($this->getErrorBag()->isNotEmpty()) return;` depois do `validate()`,
 * então uma data errada digitada uma vez impedia o cadastro mesmo depois de
 * corrigida. A saída era recarregar a página e começar de novo.
 */
final class ErrosDeValidacaoTest extends ContextoDeAcademia
{
    /** @param array<string, string> $extra */
    private function preencherAluno(mixed $componente, array $extra = []): mixed
    {
        $componente
            ->set('nome', 'Fernanda Souza Lima')
            ->set('cpf', '390.533.447-05')
            ->set('data_nascimento', '15/03/1990')
            ->set('whatsapp', '85999887766');

        foreach ($extra as $campo => $valor) {
            $componente->set($campo, $valor);
        }

        return $componente;
    }

    /**
     * O caso relatado: data inválida, crítica, correção — e a crítica ficava.
     */
    public function test_corrigir_a_data_libera_o_cadastro_do_aluno(): void
    {
        $componente = Livewire::actingAs($this->usuarioCom('recepcao'))->test(Formulario::class);

        $this->preencherAluno($componente, ['data_nascimento' => '99/99/9999'])
            ->call('salvar')
            ->assertHasErrors('data_nascimento');

        // Agora com a data certa: a crítica precisa sumir e o cadastro passar.
        $componente
            ->set('data_nascimento', '15/03/1990')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertNotNull(Aluno::query()->where('nome', 'Fernanda Souza Lima')->first());
    }

    /**
     * A guarda do `getErrorBag()` era o que tornava o travamento permanente:
     * com o saco sujo, a gravação nunca acontecia.
     */
    public function test_a_critica_some_do_saco_de_erros_ao_corrigir(): void
    {
        $componente = Livewire::actingAs($this->usuarioCom('recepcao'))->test(Formulario::class);

        $this->preencherAluno($componente, ['nome' => 'ab'])
            ->call('salvar')
            ->assertHasErrors('nome');

        $componente
            ->set('nome', 'Fernanda Souza Lima')
            ->call('salvar')
            ->assertHasNoErrors('nome');
    }

    /**
     * A data da assinatura era validada DEPOIS de convertida para o formato
     * do banco — então a regra brasileira reprovava `2027-02-16` sempre,
     * qualquer que fosse o que a pessoa digitasse.
     */
    public function test_a_data_da_assinatura_aceita_o_formato_brasileiro(): void
    {
        Livewire::actingAs(User::factory()->superAdministrador()->create())
            ->test(NovaAcademia::class)
            ->set('nome', 'Studio Vida')
            ->set('email', 'contato@studiovida.com.br')
            ->set('cidade', 'Fortaleza')
            ->set('uf', 'CE')
            ->set('unidade_nome', 'Matriz')
            ->set('dono_nome', 'Marcos Andrade')
            ->set('dono_email', 'marcos@studiovida.com.br')
            ->set('assinatura_vence_em', '31/12/2027')
            ->call('salvar')
            ->assertHasNoErrors();

        $academia = Academia::query()->where('nome', 'Studio Vida')->firstOrFail();

        $this->assertSame('2027-12-31', $academia->assinatura_vence_em->toDateString());
    }

    public function test_a_data_da_assinatura_pode_ficar_em_branco(): void
    {
        Livewire::actingAs(User::factory()->superAdministrador()->create())
            ->test(NovaAcademia::class)
            ->set('nome', 'Corpo Livre')
            ->set('email', 'contato@corpolivre.com.br')
            ->set('cidade', 'Cascavel')
            ->set('uf', 'CE')
            ->set('unidade_nome', 'Matriz')
            ->set('dono_nome', 'Paula Nunes')
            ->set('dono_email', 'paula@corpolivre.com.br')
            ->set('assinatura_vence_em', '')
            ->call('salvar')
            ->assertHasNoErrors();
    }
}
