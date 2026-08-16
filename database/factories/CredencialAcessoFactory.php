<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoCredencial;
use App\Models\Aluno;
use App\Models\ConsentimentoLgpd;
use App\Models\CredencialAcesso;
use Database\Factories\Concerns\UsaAcademiaDoContexto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CredencialAcesso> */
final class CredencialAcessoFactory extends Factory
{
    use UsaAcademiaDoContexto;

    protected $model = CredencialAcesso::class;

    /** @return array<model-property<CredencialAcesso>, mixed> */
    public function definition(): array
    {
        return [
            'academia_id' => $this->academiaDoContexto(),
            'aluno_id' => Aluno::factory(),
            'consentimento_id' => ConsentimentoLgpd::factory(),
            'tipo' => TipoCredencial::Facial,
            'template' => 'template-biometrico-simulado',
            'ativa' => true,
            'cadastrada_em' => now(),
        ];
    }

    /** Cartão: a alternativa não-biométrica, que dispensa consentimento. */
    public function cartao(): self
    {
        return $this->state(fn (): array => [
            'tipo' => TipoCredencial::Cartao,
            'template' => null,
            'identificador_cartao' => (string) fake()->unique()->numerify('##########'),
            'consentimento_id' => null,
        ]);
    }
}
