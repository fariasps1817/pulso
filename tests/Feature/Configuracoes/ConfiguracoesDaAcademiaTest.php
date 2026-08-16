<?php

declare(strict_types=1);

namespace Tests\Feature\Configuracoes;

use App\Livewire\Configuracoes\DadosDaAcademia;
use App\Livewire\Configuracoes\RegrasDaAcademia;
use App\Models\Acesso;
use App\Models\Aluno;
use App\Models\Matricula;
use App\Models\Plano;
use App\Services\Radar\Radar;
use App\Support\Academia\ContextoAcademia;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\ContextoDeAcademia;

/**
 * O que a academia ajusta por conta própria.
 *
 * Duas coisas se provam aqui: que só o dono mexe, e que os três números da
 * tela de regras realmente mudam o comportamento do sistema — configuração
 * que não muda nada é pior do que configuração nenhuma, porque dá a impressão
 * de estar no controle.
 */
final class ConfiguracoesDaAcademiaTest extends ContextoDeAcademia
{
    // -----------------------------------------------------------------
    // Quem pode
    // -----------------------------------------------------------------

    public function test_o_dono_configura(): void
    {
        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(DadosDaAcademia::class)
            // `assertSet`, e não `assertSee`: o Livewire preenche os campos no
            // cliente, então o HTML do servidor sai com value vazio.
            ->assertSet('nome', 'Alpha Fit')
            ->assertSet('email', $this->academia->email);
    }

    /**
     * O que se ajusta ali muda o cabeçalho dos recibos e o dia em que a
     * catraca para de liberar. Erro nisso aparece no documento que o aluno
     * leva para casa.
     */
    public function test_gerente_e_recepcao_nao_configuram(): void
    {
        foreach (['gerente', 'recepcao'] as $papel) {
            $this->actingAs($this->usuarioCom($papel))
                ->get(route('configuracoes.academia'))
                ->assertForbidden();

            $this->actingAs($this->usuarioCom($papel))
                ->get(route('configuracoes.regras'))
                ->assertForbidden();
        }
    }

    // -----------------------------------------------------------------
    // Dados
    // -----------------------------------------------------------------

    public function test_grava_os_dados_que_saem_impressos(): void
    {
        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(DadosDaAcademia::class)
            ->set('razao_social', 'Alpha Fit Academia Ltda')
            ->set('cnpj', '11.222.333/0001-81')
            ->set('email', 'contato@alpha-fit.com.br')
            ->set('cidade', 'Fortaleza')
            ->set('uf', 'ce')
            ->call('salvar')
            ->assertHasNoErrors();

        $academia = $this->academia->fresh();

        $this->assertSame('Alpha Fit Academia Ltda', $academia->razao_social);
        // Guardado só com dígitos: a máscara é da tela, não do banco.
        $this->assertSame('11222333000181', $academia->cnpj);
        $this->assertSame('CE', $academia->uf);
    }

    /** CNPJ errado só apareceria na primeira nota fiscal, meses depois. */
    public function test_recusa_cnpj_com_digito_invalido(): void
    {
        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(DadosDaAcademia::class)
            ->set('cnpj', '11.222.333/0001-00')
            ->call('salvar')
            ->assertHasErrors('cnpj');
    }

    public function test_guarda_a_logo_da_academia(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(DadosDaAcademia::class)
            ->set('logo', UploadedFile::fake()->image('logo.png', 400, 120))
            ->call('salvar')
            ->assertHasNoErrors();

        $caminho = $this->academia->fresh()->logo_path;

        $this->assertNotNull($caminho);
        Storage::disk('public')->assertExists($caminho);
    }

    /** Trocar a logo apaga a antiga: guardar as duas enche o disco de órfãos. */
    public function test_trocar_a_logo_apaga_a_anterior(): void
    {
        Storage::fake('public');

        $componente = Livewire::actingAs($this->usuarioCom('dono'))->test(DadosDaAcademia::class);

        $componente->set('logo', UploadedFile::fake()->image('primeira.png'))->call('salvar');
        $primeira = $this->academia->fresh()->logo_path;

        $componente->set('logo', UploadedFile::fake()->image('segunda.png'))->call('salvar');
        $segunda = $this->academia->fresh()->logo_path;

        $this->assertNotSame($primeira, $segunda);
        Storage::disk('public')->assertMissing($primeira);
        Storage::disk('public')->assertExists($segunda);
    }

    // -----------------------------------------------------------------
    // Regras — e o efeito delas
    // -----------------------------------------------------------------

    public function test_grava_as_tres_regras(): void
    {
        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(RegrasDaAcademia::class)
            ->set('dias_tolerancia_bloqueio', '10')
            ->set('dias_baixa_frequencia', '21')
            ->set('idade_minima', '16')
            ->call('salvar')
            ->assertHasNoErrors();

        $academia = $this->academia->fresh();

        $this->assertSame(10, $academia->dias_tolerancia_bloqueio);
        $this->assertSame(21, $academia->dias_baixa_frequencia);
        $this->assertSame(16, $academia->idade_minima);
    }

    /**
     * O teste que importa: mudar o número muda quem aparece no Radar. Sem
     * isto, a tela seria um formulário decorativo.
     */
    public function test_mudar_a_baixa_frequencia_muda_o_radar(): void
    {
        $aluno = Aluno::factory()->create(['nome' => 'Sergio Sumido']);

        Matricula::factory()->create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $aluno->id,
            'plano_id' => Plano::factory()->create()->id,
        ]);

        Acesso::create([
            'unidade_id' => $this->unidade->id,
            'aluno_id' => $aluno->id,
            'ocorreu_em' => CarbonImmutable::now()->subDays(20),
            'sentido' => 'entrada',
            'resultado' => 'liberado',
        ]);

        // Com 30 dias de tolerância, ele ainda não sumiu.
        $this->academia->update(['dias_baixa_frequencia' => 30]);
        $this->assertSame(0, (new Radar($this->academia->fresh()))->totalDeSumidos());

        // Com 15, sumiu.
        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(RegrasDaAcademia::class)
            ->set('dias_baixa_frequencia', '15')
            ->call('salvar');

        $this->assertSame(1, (new Radar($this->academia->fresh()))->totalDeSumidos());
    }

    /** Acima de 30 dias a catraca deixa de servir como cobrança. */
    public function test_recusa_tolerancia_alem_do_teto(): void
    {
        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(RegrasDaAcademia::class)
            ->set('dias_tolerancia_bloqueio', '90')
            ->call('salvar')
            ->assertHasErrors('dias_tolerancia_bloqueio');
    }

    /** Abaixo de 7 dias, quem viajou uma semana já viraria "sumido". */
    public function test_recusa_baixa_frequencia_curta_demais(): void
    {
        Livewire::actingAs($this->usuarioCom('dono'))
            ->test(RegrasDaAcademia::class)
            ->set('dias_baixa_frequencia', '2')
            ->call('salvar')
            ->assertHasErrors('dias_baixa_frequencia');
    }

    /** Uma academia não configura a outra — nem por engano de contexto. */
    public function test_configura_apenas_a_propria_academia(): void
    {
        $outra = $this->naOutraAcademia(fn ($academia) => $academia);

        $dono = $this->usuarioCom('dono');

        Livewire::actingAs($dono)
            ->test(DadosDaAcademia::class)
            ->assertSet('nome', 'Alpha Fit');

        $this->assertFalse($dono->can('configurar', $outra));

        app(ContextoAcademia::class)->definir($this->academia->id);
    }
}
