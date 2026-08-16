<?php

declare(strict_types=1);

namespace Tests\Feature\Usuarios;

use App\Livewire\Acesso\TrocarSenha;
use App\Livewire\Usuarios\Formulario;
use App\Livewire\Usuarios\Lista;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\ContextoDeAcademia;

/**
 * O cadastro da equipe pelo gestor da academia.
 *
 * Três coisas se provam aqui, e nenhuma é sobre o formulário funcionar:
 * ninguém cria alguém acima de si, ninguém se desativa por engano, e a senha
 * temporária não sobrevive ao primeiro acesso.
 */
final class CadastroDeUsuarioTest extends ContextoDeAcademia
{
    /** @param array<string, mixed> $extra */
    private function preencher(mixed $componente, array $extra = []): mixed
    {
        $componente
            ->set('name', 'Joana Recepcao')
            ->set('email', 'joana@alpha-fit.com.br')
            ->set('papel', 'recepcao')
            ->set('unidade_padrao_id', $this->unidade->id);

        foreach ($extra as $campo => $valor) {
            $componente->set($campo, $valor);
        }

        return $componente;
    }

    // -----------------------------------------------------------------
    // Cadastro
    // -----------------------------------------------------------------

    public function test_cria_o_usuario_com_papel_unidade_e_senha_temporaria(): void
    {
        $componente = Livewire::actingAs($this->usuarioCom('dono'))->test(Formulario::class);

        $this->preencher($componente)->call('salvar')->assertHasNoErrors();

        $criado = User::query()->where('email', 'joana@alpha-fit.com.br')->firstOrFail();

        $this->assertSame($this->academia->id, $criado->academia_id);
        $this->assertTrue($criado->hasRole('recepcao'));
        $this->assertSame($this->unidade->id, $criado->unidade_padrao_id);
        $this->assertTrue($criado->deve_trocar_senha, 'A senha temporária precisa exigir troca.');
        $this->assertTrue($criado->ativo);
    }

    /**
     * A senha aparece UMA vez, na tela, para o gestor repassar. Não vai por
     * e-mail (que ainda não está configurado) nem fica guardada em lugar
     * nenhum de onde pudesse ser mostrada de novo.
     */
    public function test_mostra_a_senha_temporaria_uma_unica_vez(): void
    {
        $componente = Livewire::actingAs($this->usuarioCom('dono'))->test(Formulario::class);

        $this->preencher($componente)->call('salvar');

        $senha = $componente->get('senhaTemporaria');

        $this->assertNotNull($senha);
        $this->assertSame(12, strlen($senha));
        $componente->assertSee($senha);

        // E ela é de verdade a senha da conta.
        $criado = User::query()->where('email', 'joana@alpha-fit.com.br')->firstOrFail();
        $this->assertTrue(Hash::check($senha, $criado->password));
    }

    /** Recepção e professor nascem travados na unidade — padrão do papel. */
    public function test_o_papel_define_o_alcance_entre_unidades(): void
    {
        $componente = Livewire::actingAs($this->usuarioCom('dono'))->test(Formulario::class);

        $componente->set('papel', 'recepcao');
        $this->assertFalse($componente->get('acessa_todas_unidades'));
        $this->assertFalse($componente->get('pode_alternar_unidade'));

        // Promover repõe o padrão: sem isso, o gerente ficaria preso numa
        // unidade e o gestor nunca entenderia por quê.
        $componente->set('papel', 'gerente');
        $this->assertTrue($componente->get('pode_alternar_unidade'));

        $componente->set('papel', 'dono');
        $this->assertTrue($componente->get('acessa_todas_unidades'));
    }

    /** A unidade padrão entra como vínculo sozinha, sempre. */
    public function test_a_unidade_padrao_vira_vinculo_automaticamente(): void
    {
        $outra = Unidade::factory()->create(['academia_id' => $this->academia->id, 'nome' => 'Filial']);

        $componente = Livewire::actingAs($this->usuarioCom('dono'))->test(Formulario::class);

        $this->preencher($componente, [
            'papel' => 'gerente',
            'unidade_padrao_id' => $outra->id,
            'unidades' => [$this->unidade->id],
        ])->call('salvar')->assertHasNoErrors();

        $criado = User::query()->where('email', 'joana@alpha-fit.com.br')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$this->unidade->id, $outra->id],
            $criado->unidades()->pluck('unidades.id')->all(),
        );
    }

    // -----------------------------------------------------------------
    // Hierarquia
    // -----------------------------------------------------------------

    /**
     * A trava que importa: sem ela, um gerente cadastraria um dono, entraria
     * com ele e teria a rede inteira — sem invadir nada, só usando o
     * formulário.
     */
    public function test_gerente_nao_cria_dono_nem_outro_gerente(): void
    {
        $componente = Livewire::actingAs($this->usuarioCom('gerente'))->test(Formulario::class);

        $this->preencher($componente, ['papel' => 'dono'])
            ->call('salvar')
            ->assertHasErrors('papel');

        $this->assertNull(User::query()->where('email', 'joana@alpha-fit.com.br')->first());
    }

    public function test_gerente_cria_recepcao(): void
    {
        $componente = Livewire::actingAs($this->usuarioCom('gerente'))->test(Formulario::class);

        $this->preencher($componente)->call('salvar')->assertHasNoErrors();

        $this->assertNotNull(User::query()->where('email', 'joana@alpha-fit.com.br')->first());
    }

    public function test_gerente_nao_edita_o_dono(): void
    {
        $dono = $this->usuarioCom('dono');

        Livewire::actingAs($this->usuarioCom('gerente'))
            ->test(Formulario::class, ['usuario' => $dono])
            ->assertForbidden();
    }

    public function test_recepcao_nao_acessa_a_area_de_usuarios(): void
    {
        $this->actingAs($this->usuarioCom('recepcao'))
            ->get(route('usuarios.lista'))
            ->assertForbidden();
    }

    /**
     * Ninguém edita a própria conta por esta tela. Um dono que se rebaixe por
     * engano tranca a academia, e a saída seria mexer no banco.
     */
    public function test_ninguem_edita_a_propria_conta_por_aqui(): void
    {
        $dono = $this->usuarioCom('dono');

        Livewire::actingAs($dono)
            ->test(Formulario::class, ['usuario' => $dono])
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Isolamento
    // -----------------------------------------------------------------

    /**
     * `users` fica FORA do Row Level Security — a autenticação acontece antes
     * de existir "academia atual". Então o filtro da lista não é conveniência:
     * é a única barreira.
     */
    public function test_a_lista_nao_mostra_usuario_de_outra_academia(): void
    {
        $alheio = $this->naOutraAcademia(fn ($outra) => User::factory()
            ->daAcademia($outra->id)
            ->create(['name' => 'Intruso Da Concorrente']));

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(Lista::class)
            ->assertDontSee($alheio->name)
            ->assertViewHas('usuarios', fn ($u) => $u->every(
                fn ($p) => $p->academia_id === $this->academia->id,
            ));
    }

    // -----------------------------------------------------------------
    // Troca da senha temporária
    // -----------------------------------------------------------------

    /**
     * Enquanto a senha for temporária, o usuário não chega a tela nenhuma. É
     * o que fecha a janela em que duas pessoas conhecem a mesma senha.
     */
    public function test_quem_tem_senha_temporaria_e_levado_para_a_troca(): void
    {
        $novo = $this->usuarioCom('recepcao', ['deve_trocar_senha' => true]);

        $this->actingAs($novo)->get(route('painel.inicio'))->assertRedirect(route('senha.trocar'));
        $this->actingAs($novo)->get(route('alunos.lista'))->assertRedirect(route('senha.trocar'));

        // A própria tela de troca continua acessível, e sair também.
        $this->actingAs($novo)->get(route('senha.trocar'))->assertOk();
    }

    public function test_trocar_a_senha_libera_o_sistema(): void
    {
        $novo = $this->usuarioCom('recepcao', [
            'password' => 'TemporariaAb12',
            'deve_trocar_senha' => true,
        ]);

        Livewire::actingAs($novo)
            ->test(TrocarSenha::class)
            ->set('atual', 'TemporariaAb12')
            ->set('senha', 'MinhaSenhaBoa4712')
            ->set('senha_confirmation', 'MinhaSenhaBoa4712')
            ->call('salvar')
            ->assertHasNoErrors();

        $novo->refresh();

        $this->assertFalse($novo->deve_trocar_senha);
        $this->assertTrue(Hash::check('MinhaSenhaBoa4712', $novo->password));

        $this->actingAs($novo)->get(route('painel.inicio'))->assertOk();
    }

    /** Repetir a temporária deixaria valendo para sempre a senha que o gestor conhece. */
    public function test_a_senha_nova_nao_pode_ser_a_temporaria(): void
    {
        $novo = $this->usuarioCom('recepcao', [
            'password' => 'TemporariaAb12',
            'deve_trocar_senha' => true,
        ]);

        Livewire::actingAs($novo)
            ->test(TrocarSenha::class)
            ->set('atual', 'TemporariaAb12')
            ->set('senha', 'TemporariaAb12')
            ->set('senha_confirmation', 'TemporariaAb12')
            ->call('salvar')
            ->assertHasErrors('senha');

        $this->assertTrue($novo->fresh()->deve_trocar_senha);
    }

    public function test_senha_atual_errada_e_recusada(): void
    {
        $novo = $this->usuarioCom('recepcao', [
            'password' => 'TemporariaAb12',
            'deve_trocar_senha' => true,
        ]);

        Livewire::actingAs($novo)
            ->test(TrocarSenha::class)
            ->set('atual', 'chute-errado-99')
            ->set('senha', 'MinhaSenhaBoa4712')
            ->set('senha_confirmation', 'MinhaSenhaBoa4712')
            ->call('salvar')
            ->assertHasErrors('atual');
    }
}
