<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plano;
use Database\Factories\Concerns\UsaAcademiaDoContexto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plano> */
final class PlanoFactory extends Factory
{
    use UsaAcademiaDoContexto;

    protected $model = Plano::class;

    /** @return array<model-property<Plano>, mixed> */
    public function definition(): array
    {
        return [
            'academia_id' => $this->academiaDoContexto(),
            'nome' => 'Mensal Musculação',
            'descricao' => 'Acesso livre à musculação em horário comercial.',
            'valor_mensal' => 129.90,
            'taxa_matricula' => 0,
            'multa_cancelamento' => 0,
            'duracao_meses' => 1,
            'acesso_todas_unidades' => false,
            'dias_experiencia' => 7,
            'sessoes_experiencia' => 3,
            'ativo' => true,
        ];
    }

    public function anual(): self
    {
        return $this->state(fn (): array => [
            'nome' => 'Anual Completo',
            'valor_mensal' => 99.00,
            'duracao_meses' => 12,
            'acesso_todas_unidades' => true,
            'multa_cancelamento' => 150.00,
        ]);
    }

    public function semExperiencia(): self
    {
        return $this->state(fn (): array => [
            'dias_experiencia' => 0,
            'sessoes_experiencia' => 0,
        ]);
    }
}
