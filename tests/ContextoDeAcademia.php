<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Academia;
use App\Models\Unidade;
use App\Models\User;
use App\Support\Academia\ContextoAcademia;
use App\Support\Academia\PadroesDeAcesso;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Base para testes que precisam de uma academia em foco.
 *
 * Monta academia, unidade e contexto — o mesmo preparo que o middleware faz
 * numa requisição real, e sem o qual as políticas de Row Level Security
 * devolveriam zero linhas para tudo.
 */
abstract class ContextoDeAcademia extends TestCase
{
    use DatabaseTransactions;

    protected Academia $academia;

    protected Unidade $unidade;

    /** Academia vizinha, usada para provar que o isolamento vale. */
    private ?Academia $outraAcademia = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academia = Academia::factory()->create(['nome' => 'Alpha Fit']);

        app(ContextoAcademia::class)->definir($this->academia->id);
        setPermissionsTeamId($this->academia->id);

        $this->unidade = Unidade::factory()->create([
            'academia_id' => $this->academia->id,
            'nome' => 'Matriz',
        ]);
    }

    /** @param array<string, mixed> $atributos */
    protected function usuarioCom(string $papel, array $atributos = []): User
    {
        $usuario = User::factory()->daAcademia($this->academia->id)->create([
            'unidade_padrao_id' => $this->unidade->id,
            ...PadroesDeAcesso::paraPapel($papel),
            ...$atributos,
        ]);

        $usuario->assignRole($papel);
        $usuario->unidades()->syncWithoutDetaching([$this->unidade->id]);

        return $usuario;
    }

    /**
     * Executa algo dentro de OUTRA academia e restaura o contexto.
     *
     * Serve para montar o cenário de vazamento: criar dado alheio e provar
     * que ele não aparece.
     *
     * @template T
     *
     * @param  callable(Academia): T  $acao
     * @return T
     */
    protected function naOutraAcademia(callable $acao): mixed
    {
        // A MESMA academia em todas as chamadas do teste: criar uma nova a
        // cada vez faria a segunda chamada não enxergar o que a primeira
        // criou, e o teste falharia por motivo errado.
        $outra = $this->outraAcademia ??= Academia::factory()->create(['nome' => 'Concorrente']);

        return app(ContextoAcademia::class)->paraAcademia(
            $outra->id,
            fn () => $acao($outra),
        );
    }
}
