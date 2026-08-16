<?php

declare(strict_types=1);

namespace Tests\Feature\Administracao;

use App\Enums\SituacaoAcademia;
use App\Enums\SituacaoMatricula;
use App\Livewire\Administracao\Academias as ListaDeAcademias;
use App\Livewire\Administracao\DetalhesDaAcademia;
use App\Livewire\Administracao\NovaAcademia;
use App\Models\Academia;
use App\Models\Aluno;
use App\Models\Matricula;
use App\Models\Plano;
use App\Models\Unidade;
use App\Models\User;
use App\Support\Academia\ContextoAcademia;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\ContextoDeAcademia;

/**
 * A área da equipe do Pulso.
 *
 * O que mais importa provar aqui não é o cadastro funcionar: é que o super
 * administrador NÃO alcança o interior de academia nenhuma. Essa é a troca
 * que o projeto fez — suporte mais difícil em troca de uma conta comprometida
 * não abrir a base inteira de clientes.
 */
final class AreaDoSuperAdministradorTest extends ContextoDeAcademia
{
    private function superAdministrador(): User
    {
        // `academia_id` nulo é o que define o papel — não um cargo atribuído
        // por tela, que qualquer gestor poderia tentar dar a si mesmo.
        return User::factory()->superAdministrador()->create(['name' => 'Equipe Pulso']);
    }

    // -----------------------------------------------------------------
    // As duas áreas não se misturam
    // -----------------------------------------------------------------

    public function test_a_academia_nao_entra_na_administracao_do_saas(): void
    {
        foreach (['dono', 'gerente', 'recepcao'] as $papel) {
            $this->actingAs($this->usuarioCom($papel))
                ->get(route('administracao.academias.lista'))
                ->assertForbidden();
        }
    }

    /**
     * O painel comum não serve ao super administrador: sem academia, toda
     * consulta volta vazia e a barra de unidade não teria o que mostrar.
     */
    public function test_o_super_administrador_e_levado_para_a_propria_area(): void
    {
        $this->actingAs($this->superAdministrador())
            ->get(route('painel.inicio'))
            ->assertRedirect(route('administracao.academias.lista'));
    }

    public function test_a_lista_de_academias_abre_para_o_super_administrador(): void
    {
        Livewire::actingAs($this->superAdministrador())
            ->test(ListaDeAcademias::class)
            ->assertOk()
            ->assertSee('Alpha Fit');
    }

    // -----------------------------------------------------------------
    // O que ele vê, e o que não vê
    // -----------------------------------------------------------------

    /**
     * O contador existe justamente porque a leitura direta é impossível: as
     * políticas de isolamento não abrem para quem não tem academia.
     */
    public function test_conta_alunos_sem_conseguir_le_los(): void
    {
        $plano = Plano::factory()->create();

        foreach (range(1, 3) as $indice) {
            Matricula::factory()->create([
                'unidade_id' => $this->unidade->id,
                'aluno_id' => Aluno::factory()->create(['nome' => "Aluno Secreto {$indice}"])->id,
                'plano_id' => $plano->id,
                'situacao' => SituacaoMatricula::Ativa,
            ]);
        }

        $this->assertSame(3, $this->academia->fresh()->total_alunos_ativos);

        $componente = Livewire::actingAs($this->superAdministrador())->test(ListaDeAcademias::class);

        // O número aparece...
        $componente->assertViewHas('totais', fn (array $t): bool => $t['alunos'] >= 3);
        // ...e nenhum nome, nem por engano.
        $componente->assertDontSee('Aluno Secreto 1');

        // A prova de fundo: sem contexto, o banco não devolve linha nenhuma.
        app(ContextoAcademia::class)->limpar();
        $this->assertSame(0, Aluno::query()->count());
    }

    /** Matrícula encerrada tira o aluno da contagem — é o número da cobrança. */
    public function test_a_contagem_acompanha_o_encerramento_da_matricula(): void
    {
        $matricula = Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => Aluno::factory()->create()->id,
            'plano_id' => Plano::factory()->create()->id,
            'situacao' => SituacaoMatricula::Ativa,
        ]);

        $this->assertSame(1, $this->academia->fresh()->total_alunos_ativos);

        $matricula->encerrar('Mudou de cidade.');

        $this->assertSame(0, $this->academia->fresh()->total_alunos_ativos);
    }

    /** Filial o super administrador conta direto: `unidades` é plano de controle. */
    public function test_identifica_quem_tem_filial(): void
    {
        Unidade::factory()->create(['academia_id' => $this->academia->id, 'nome' => 'Filial']);

        Livewire::actingAs($this->superAdministrador())
            ->test(ListaDeAcademias::class)
            ->assertViewHas('totais', fn (array $t): bool => $t['com_filial'] >= 1)
            ->assertSee('com filial');
    }

    // -----------------------------------------------------------------
    // Cadastro de academia
    // -----------------------------------------------------------------

    /**
     * Academia, unidade e dono na mesma transação. Criar só a academia
     * produziria um cliente inacessível, e o super administrador não pode
     * suprir a falta depois — ele não enxerga o interior de nenhuma.
     */
    public function test_cria_academia_com_unidade_e_dono_de_uma_vez(): void
    {
        $componente = Livewire::actingAs($this->superAdministrador())
            ->test(NovaAcademia::class)
            ->set('nome', 'Corpo em Movimento')
            ->set('email', 'contato@corpoemmovimento.com.br')
            ->set('cidade', 'Cascavel')
            ->set('uf', 'CE')
            ->set('unidade_nome', 'Matriz')
            ->set('dono_nome', 'Vladir Alencar')
            ->set('dono_email', 'vladir@corpoemmovimento.com.br')
            ->call('salvar')
            ->assertHasNoErrors();

        $academia = Academia::query()->where('nome', 'Corpo em Movimento')->firstOrFail();

        $this->assertSame(SituacaoAcademia::Ativa, $academia->situacao);
        $this->assertSame(1, $academia->unidades()->count());

        $dono = User::query()->where('email', 'vladir@corpoemmovimento.com.br')->firstOrFail();

        $this->assertSame($academia->id, $dono->academia_id);
        $this->assertTrue($dono->deve_trocar_senha);
        $this->assertTrue($dono->acessa_todas_unidades);
        $this->assertSame($academia->unidades()->first()->id, $dono->unidade_padrao_id);

        // O papel vale DENTRO da academia nova, e não em geral.
        setPermissionsTeamId($academia->id);
        $this->assertTrue($dono->fresh()->hasRole('dono'));

        // A senha temporária de verdade abre a conta.
        $senha = $componente->get('senhaTemporaria');
        $this->assertNotNull($senha);
        $this->assertTrue(Hash::check($senha, $dono->password));
    }

    public function test_recusa_e_mail_de_dono_ja_usado_em_outra_academia(): void
    {
        $existente = $this->usuarioCom('dono');

        Livewire::actingAs($this->superAdministrador())
            ->test(NovaAcademia::class)
            ->set('nome', 'Studio Vida')
            ->set('email', 'contato@studiovida.com.br')
            ->set('cidade', 'Fortaleza')
            ->set('unidade_nome', 'Matriz')
            ->set('dono_nome', 'Outra Pessoa')
            ->set('dono_email', $existente->email)
            ->call('salvar')
            ->assertHasErrors('dono_email');
    }

    // -----------------------------------------------------------------
    // Bloqueio
    // -----------------------------------------------------------------

    /** Sem motivo, o bloqueio não passa: quem atender o telefone amanhã precisa saber. */
    public function test_bloquear_exige_motivo(): void
    {
        Livewire::actingAs($this->superAdministrador())
            ->test(DetalhesDaAcademia::class, ['academia' => $this->academia])
            ->set('situacao', SituacaoAcademia::Bloqueada->value)
            ->set('motivo_bloqueio', '')
            ->call('alterarSituacao')
            ->assertHasErrors('motivo_bloqueio');

        $this->assertSame(SituacaoAcademia::Ativa, $this->academia->fresh()->situacao);
    }

    /**
     * O bloqueio precisa ter efeito — senão é só uma palavra numa tabela.
     */
    public function test_academia_bloqueada_desconecta_a_equipe(): void
    {
        $dono = $this->usuarioCom('dono');

        Livewire::actingAs($this->superAdministrador())
            ->test(DetalhesDaAcademia::class, ['academia' => $this->academia])
            ->set('situacao', SituacaoAcademia::Bloqueada->value)
            ->set('motivo_bloqueio', 'Assinatura em aberto há 45 dias.')
            ->call('alterarSituacao')
            ->assertHasNoErrors();

        $this->academia->refresh();

        $this->assertSame(SituacaoAcademia::Bloqueada, $this->academia->situacao);
        $this->assertNotNull($this->academia->bloqueada_em);

        $this->actingAs($dono)->get(route('painel.inicio'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * "Em aviso" é cobrança, não punição: a academia continua funcionando.
     */
    public function test_em_aviso_nao_tira_o_acesso(): void
    {
        $this->academia->update(['situacao' => SituacaoAcademia::EmAviso]);

        $this->actingAs($this->usuarioCom('dono'))
            ->get(route('painel.inicio'))
            ->assertOk();
    }

    /**
     * A data da assinatura usa máscara brasileira na tela.
     *
     * Entregar o formato do banco ao campo mascarado produzia `20/27/0216` —
     * e salvar de volta gravaria lixo ou uma data trocada.
     */
    public function test_a_data_da_assinatura_vai_e_volta_no_formato_brasileiro(): void
    {
        $this->academia->update(['assinatura_vence_em' => '2027-02-16']);

        $componente = Livewire::actingAs($this->superAdministrador())
            ->test(DetalhesDaAcademia::class, ['academia' => $this->academia->fresh()]);

        $componente->assertSet('assinatura_vence_em', '16/02/2027');

        $componente
            ->set('assinatura_vence_em', '31/12/2027')
            ->call('alterarSituacao')
            ->assertHasNoErrors();

        $this->assertSame('2027-12-31', $this->academia->fresh()->assinatura_vence_em->toDateString());
    }

    /**
     * O papel só existe DENTRO de uma academia. Sem definir a da tela, a
     * coluna mostrava um travessão para a equipe inteira.
     */
    public function test_mostra_o_papel_de_cada_um_da_equipe(): void
    {
        $this->usuarioCom('dono', ['name' => 'Dona Da Alpha']);
        $this->usuarioCom('recepcao', ['name' => 'Moca Da Recepcao']);

        Livewire::actingAs($this->superAdministrador())
            ->test(DetalhesDaAcademia::class, ['academia' => $this->academia])
            ->assertViewHas('equipe', fn ($equipe) => $equipe->every(
                fn ($pessoa) => $pessoa->getRoleNames()->isNotEmpty(),
            ))
            ->assertSee('Recepção');
    }

    /** Usuário desativado pelo gestor cai na mesma porta. */
    public function test_usuario_desativado_nao_entra(): void
    {
        $usuario = $this->usuarioCom('recepcao', ['ativo' => false]);

        $this->actingAs($usuario)->get(route('alunos.lista'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
