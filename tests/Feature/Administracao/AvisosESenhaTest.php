<?php

declare(strict_types=1);

namespace Tests\Feature\Administracao;

use App\Livewire\Administracao\Academias as ListaDeAcademias;
use App\Livewire\Administracao\Avisos;
use App\Livewire\Administracao\DetalhesDaAcademia;
use App\Livewire\Usuarios\Lista as ListaDeUsuarios;
use App\Models\Academia;
use App\Models\AvisoAcademia;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\ContextoDeAcademia;

/**
 * Avisos do Pulso, redefinição de senha e a lista paginada.
 */
final class AvisosESenhaTest extends ContextoDeAcademia
{
    private function superAdministrador(): User
    {
        return User::factory()->superAdministrador()->create(['name' => 'Equipe Pulso']);
    }

    /** @param array<model-property<AvisoAcademia>, mixed> $atributos */
    private function aviso(array $atributos = []): AvisoAcademia
    {
        return AvisoAcademia::create([
            'academia_id' => $this->academia->id,
            'tipo' => 'atencao',
            'titulo' => 'Sua assinatura vence em 5 dias',
            'mensagem' => 'Renove para não perder o acesso.',
            'exibir_de' => CarbonImmutable::now()->subDay()->toDateString(),
            'exibir_ate' => CarbonImmutable::now()->addDays(5)->toDateString(),
            'dispensavel' => true,
            ...$atributos,
        ]);
    }

    // -----------------------------------------------------------------
    // Avisos
    // -----------------------------------------------------------------

    public function test_o_aviso_aparece_no_topo_de_toda_tela_da_academia(): void
    {
        $this->aviso();

        $usuario = $this->usuarioCom('recepcao');

        // Em duas telas diferentes: o aviso vive no layout, não numa página.
        $this->actingAs($usuario)->get(route('painel.inicio'))
            ->assertOk()->assertSee('Sua assinatura vence em 5 dias');

        $this->actingAs($usuario)->get(route('alunos.lista'))
            ->assertOk()->assertSee('Sua assinatura vence em 5 dias');
    }

    /** Aviso sem academia vale para todas — é como se anuncia manutenção. */
    public function test_aviso_geral_alcanca_todas_as_academias(): void
    {
        $this->aviso(['academia_id' => null, 'titulo' => 'Manutenção no domingo']);

        $this->actingAs($this->usuarioCom('dono'))
            ->get(route('painel.inicio'))
            ->assertSee('Manutenção no domingo');
    }

    /** Fora da janela não aparece: aviso vencido vira paisagem. */
    public function test_aviso_fora_da_janela_nao_aparece(): void
    {
        $this->aviso([
            'titulo' => 'Recado velho',
            'exibir_de' => CarbonImmutable::now()->subMonth()->toDateString(),
            'exibir_ate' => CarbonImmutable::now()->subDays(10)->toDateString(),
        ]);

        $this->actingAs($this->usuarioCom('dono'))
            ->get(route('painel.inicio'))
            ->assertDontSee('Recado velho');
    }

    /** A academia vizinha não vê o recado desta. */
    public function test_aviso_de_uma_academia_nao_vaza_para_outra(): void
    {
        $this->aviso(['titulo' => 'Assunto da Alpha']);

        $vizinho = $this->naOutraAcademia(function ($outra) {
            setPermissionsTeamId($outra->id);

            return User::factory()->daAcademia($outra->id)->create([
                'acessa_todas_unidades' => true,
                'deve_trocar_senha' => false,
            ]);
        });

        $vizinho->assignRole('dono');

        $this->actingAs($vizinho)->get(route('painel.inicio'))->assertDontSee('Assunto da Alpha');
    }

    /** A dispensa fica no perfil: quem fechou no balcão não revê no celular. */
    public function test_dispensar_guarda_no_perfil_e_o_aviso_some(): void
    {
        $aviso = $this->aviso();
        $usuario = $this->usuarioCom('dono');

        $this->actingAs($usuario)
            ->post(route('avisos.dispensar', $aviso))
            ->assertOk();

        $this->assertSame([$aviso->id], $usuario->fresh()->preferencias['avisos_dispensados']);

        $this->actingAs($usuario->fresh())
            ->get(route('painel.inicio'))
            ->assertDontSee('Sua assinatura vence em 5 dias');
    }

    /**
     * O ponto do recurso: alerta de bloqueio que some ao ser fechado deixa o
     * dono descobrir na segunda-feira, com a academia parada.
     */
    public function test_aviso_nao_dispensavel_nao_se_dispensa_nem_por_chamada_direta(): void
    {
        $aviso = $this->aviso(['dispensavel' => false]);

        $this->actingAs($this->usuarioCom('dono'))
            ->post(route('avisos.dispensar', $aviso))
            ->assertForbidden();
    }

    public function test_o_super_administrador_publica_um_aviso(): void
    {
        Livewire::actingAs($this->superAdministrador())
            ->test(Avisos::class)
            ->set('academia_id', $this->academia->id)
            ->set('tipo', 'atencao')
            ->set('titulo', 'Assinatura vence em 5 dias')
            ->set('mensagem', 'Renove para não perder o acesso ao sistema.')
            ->set('exibir_de', '16/08/2026')
            ->set('exibir_ate', '21/08/2026')
            ->call('publicar')
            ->assertHasNoErrors();

        $aviso = AvisoAcademia::query()->firstOrFail();

        $this->assertSame($this->academia->id, $aviso->academia_id);
        $this->assertSame('2026-08-21', $aviso->exibir_ate->toDateString());
        // Quem escreveu: primeira pergunta quando alguém reclama do recado.
        $this->assertNotNull($aviso->criado_por);
    }

    public function test_recusa_janela_invertida(): void
    {
        Livewire::actingAs($this->superAdministrador())
            ->test(Avisos::class)
            ->set('titulo', 'Recado')
            ->set('mensagem', 'Mensagem qualquer para o teste.')
            ->set('exibir_de', '21/08/2026')
            ->set('exibir_ate', '16/08/2026')
            ->call('publicar')
            ->assertHasErrors('exibir_ate');
    }

    public function test_a_academia_nao_publica_aviso(): void
    {
        $this->actingAs($this->usuarioCom('dono'))
            ->get(route('administracao.avisos'))
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Redefinição de senha
    // -----------------------------------------------------------------

    public function test_o_gestor_gera_senha_nova_para_a_equipe(): void
    {
        $recepcao = $this->usuarioCom('recepcao', [
            'password' => 'SenhaAntiga123',
            'deve_trocar_senha' => false,
        ]);

        $componente = Livewire::actingAs($this->usuarioCom('dono'))
            ->test(ListaDeUsuarios::class)
            ->call('redefinirSenha', $recepcao->id);

        $senha = $componente->get('senhaTemporaria');

        $this->assertNotNull($senha);
        $componente->assertSee($senha);

        $recepcao->refresh();

        $this->assertTrue(Hash::check($senha, $recepcao->password));
        $this->assertFalse(Hash::check('SenhaAntiga123', $recepcao->password));
        // A troca no primeiro acesso vale igual ao cadastro.
        $this->assertTrue($recepcao->deve_trocar_senha);
    }

    /**
     * A hierarquia vale também para a senha: gerar uma senha para alguém é
     * entrar na conta dessa pessoa. Se o gerente pudesse fazer isso com o
     * dono, a trava de "ninguém cria alguém acima de si" não valeria nada.
     */
    public function test_gerente_nao_gera_senha_para_o_dono(): void
    {
        $dono = $this->usuarioCom('dono');

        Livewire::actingAs($this->usuarioCom('gerente'))
            ->test(ListaDeUsuarios::class)
            ->call('redefinirSenha', $dono->id)
            ->assertForbidden();
    }

    /** E a recepção não chega nem à tela. */
    public function test_recepcao_nao_abre_a_area_de_usuarios(): void
    {
        $this->actingAs($this->usuarioCom('recepcao'))
            ->get(route('usuarios.lista'))
            ->assertForbidden();
    }

    /**
     * O caso real: o dono é um só, esqueceu a senha, e o e-mail de recuperação
     * ainda não está configurado.
     */
    public function test_o_pulso_devolve_o_acesso_ao_dono_que_se_perdeu(): void
    {
        $dono = $this->usuarioCom('dono', ['deve_trocar_senha' => false]);

        $componente = Livewire::actingAs($this->superAdministrador())
            ->test(DetalhesDaAcademia::class, ['academia' => $this->academia])
            ->call('redefinirSenhaDe', $dono->id);

        $senha = $componente->get('senhaTemporaria');

        $this->assertNotNull($senha);
        $this->assertTrue(Hash::check($senha, $dono->fresh()->password));
        $this->assertTrue($dono->fresh()->deve_trocar_senha);
    }

    /** E não alcança quem é de outra academia. */
    public function test_a_redefinicao_fica_presa_a_academia_da_tela(): void
    {
        $alheio = $this->naOutraAcademia(fn ($outra) => User::factory()->daAcademia($outra->id)->create());

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->superAdministrador())
            ->test(DetalhesDaAcademia::class, ['academia' => $this->academia])
            ->call('redefinirSenhaDe', $alheio->id);
    }

    // -----------------------------------------------------------------
    // Paginação
    // -----------------------------------------------------------------

    /**
     * Os totais saem de consulta agregada, não da página: somar o que está na
     * tela daria "3 academias" na segunda página de uma base com duzentas.
     */
    public function test_os_totais_nao_dependem_da_pagina(): void
    {
        Academia::factory()->count(30)->create();

        $total = Academia::query()->count();

        Livewire::actingAs($this->superAdministrador())
            ->test(ListaDeAcademias::class)
            ->assertViewHas('academias', fn ($p) => $p->count() === 25 && $p->total() === $total)
            ->assertViewHas('totais', fn (array $t) => $t['academias'] === $total);
    }
}
