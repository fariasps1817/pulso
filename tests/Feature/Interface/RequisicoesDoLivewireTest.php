<?php

declare(strict_types=1);

namespace Tests\Feature\Interface;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Tests\ContextoDeAcademia;

/**
 * As requisições internas do Livewire não podem ser desviadas pelo middleware.
 *
 * O ERRO QUE ESTE CASO EXISTE PARA PEGAR
 *
 * Dois middlewares liberavam o caminho `livewire/*`. Só que o endpoint real do
 * Livewire tem prefixo OFUSCADO — `livewire-635c8419/update` —, então o padrão
 * nunca casava. Toda interação virava um redirecionamento:
 *
 *   - o super administrador era jogado na lista de academias a cada clique;
 *   - quem tinha senha temporária não conseguia trocá-la, porque o envio do
 *     formulário era desviado de volta para a própria tela — ou seja, nenhum
 *     usuário novo conseguia entrar no sistema.
 *
 * TUDO AQUI É HTTP DE VERDADE, sem `Livewire::test()`. Foi justamente por isso
 * que a suíte ficou verde com a tela quebrada no navegador: o harness de teste
 * do Livewire desliga o middleware das rotas, e o instantâneo que ele produz
 * vem vazio. Estes casos carregam a página, leem o `wire:snapshot` do HTML —
 * como o navegador faz — e mandam a atualização na URL real.
 */
final class RequisicoesDoLivewireTest extends ContextoDeAcademia
{
    /** Carrega a tela e devolve o instantâneo que o navegador usaria. */
    private function instantaneoDe(string $url): string
    {
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertSame(
            1,
            preg_match('/wire:snapshot="([^"]*)"/', $html, $achado),
            "A tela {$url} não trouxe um componente Livewire.",
        );

        return html_entity_decode($achado[1], ENT_QUOTES);
    }

    /**
     * @param  array<string, mixed>  $atualizacoes
     * @param  list<array{method: string, params: list<mixed>, path: string}>  $chamadas
     * @return TestResponse<JsonResponse>
     */
    private function atualizar(string $instantaneo, array $atualizacoes = [], array $chamadas = []): TestResponse
    {
        return $this->postJson(route('default-livewire.update'), [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => $instantaneo,
                'updates' => $atualizacoes,
                'calls' => $chamadas,
            ]],
        ], [
            // O Livewire prende um middleware proprio a rota de atualizacao
            // que exige este cabecalho. Sem ele a resposta e 404, e o teste
            // estaria medindo outra coisa.
            'X-Livewire' => '1',
        ]);
    }

    /**
     * O sintoma relatado: mexer na situação da academia jogava o super
     * administrador de volta para a lista, sem aplicar nada.
     */
    public function test_o_super_administrador_interage_sem_ser_desviado(): void
    {
        $this->actingAs(User::factory()->superAdministrador()->create());

        $instantaneo = $this->instantaneoDe(route('administracao.academias.detalhes', $this->academia));

        $this->atualizar($instantaneo, ['situacao' => 'bloqueada'])
            ->assertOk()
            ->assertJsonMissingPath('components.0.effects.redirect');
    }

    /** E a ação chega a acontecer, não só a requisição a passar. */
    public function test_o_super_administrador_consegue_bloquear_a_academia(): void
    {
        $this->actingAs(User::factory()->superAdministrador()->create());

        $instantaneo = $this->instantaneoDe(route('administracao.academias.detalhes', $this->academia));

        $this->atualizar(
            $instantaneo,
            ['situacao' => 'bloqueada', 'motivo_bloqueio' => 'Assinatura em aberto há 45 dias.'],
            [['method' => 'alterarSituacao', 'params' => [], 'path' => '']],
        )->assertOk();

        $this->assertSame('bloqueada', $this->academia->fresh()->situacao->value);
    }

    /**
     * O mais grave: sem isto, usuário novo nenhum conseguia definir a senha —
     * e portanto nenhum conseguia entrar.
     */
    public function test_quem_tem_senha_temporaria_consegue_troca_la(): void
    {
        $novo = $this->usuarioCom('recepcao', [
            'password' => 'TemporariaAb12',
            'deve_trocar_senha' => true,
        ]);

        $this->actingAs($novo);

        $instantaneo = $this->instantaneoDe(route('senha.trocar'));

        $this->atualizar(
            $instantaneo,
            [
                'atual' => 'TemporariaAb12',
                'senha' => 'MinhaSenhaBoa4712',
                'senha_confirmation' => 'MinhaSenhaBoa4712',
            ],
            [['method' => 'salvar', 'params' => [], 'path' => '']],
        )->assertOk();

        $this->assertFalse(
            $novo->fresh()->deve_trocar_senha,
            'A troca precisa concluir pela requisição real, não só pelo harness de teste.',
        );
    }

    /**
     * A academia comum continua barrada na área do SaaS — a correção não pode
     * ter aberto a porta que o middleware existe para fechar.
     */
    public function test_a_academia_continua_barrada_na_area_do_saas(): void
    {
        $this->actingAs($this->usuarioCom('dono'))
            ->get(route('administracao.academias.lista'))
            ->assertForbidden();
    }
}
