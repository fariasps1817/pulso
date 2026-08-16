<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Aluno;
use App\Models\ConsentimentoLgpd;
use Database\Factories\Concerns\UsaAcademiaDoContexto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConsentimentoLgpd> */
final class ConsentimentoLgpdFactory extends Factory
{
    use UsaAcademiaDoContexto;

    protected $model = ConsentimentoLgpd::class;

    /** @return array<model-property<ConsentimentoLgpd>, mixed> */
    public function definition(): array
    {
        return [
            'academia_id' => $this->academiaDoContexto(),
            'aluno_id' => Aluno::factory(),
            // A finalidade é escrita, não implícita: é o que a LGPD exige para
            // consentimento de dado sensível.
            'finalidade' => 'Controle de acesso e frequência',
            'versao_texto' => 'v1',
            'texto_apresentado' => 'Autorizo o uso da minha biometria para controle de acesso e frequência nesta academia.',
            'aceito_em' => now(),
            'origem' => 'recepcao',
        ];
    }

    public function revogado(): self
    {
        return $this->state(fn (): array => ['revogado_em' => now()]);
    }
}
