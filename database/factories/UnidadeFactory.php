<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Unidade;
use Database\Factories\Concerns\UsaAcademiaDoContexto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Unidade> */
final class UnidadeFactory extends Factory
{
    use UsaAcademiaDoContexto;

    protected $model = Unidade::class;

    /** @return array<model-property<Unidade>, mixed> */
    public function definition(): array
    {
        return [
            'academia_id' => $this->academiaDoContexto(),
            'nome' => fake()->randomElement(['Matriz', 'Centro', 'Aldeota', 'Praia', 'Norte']),
            'cep' => fake()->numerify('########'),
            'logradouro' => fake()->streetName(),
            'numero' => (string) fake()->buildingNumber(),
            'bairro' => fake()->randomElement(['Centro', 'Aldeota', 'Meireles']),
            'cidade' => 'Fortaleza',
            'uf' => 'CE',
            'telefone' => '85'.fake()->numerify('########'),
            'ativa' => true,
        ];
    }
}
