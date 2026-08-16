<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SituacaoAcademia;
use App\Models\Academia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Academia> */
final class AcademiaFactory extends Factory
{
    protected $model = Academia::class;

    /** @return array<model-property<Academia>, mixed> */
    public function definition(): array
    {
        $cidade = fake()->randomElement(['Fortaleza', 'Cascavel', 'Aquiraz', 'Maracanaú', 'Sobral']);

        return [
            'nome' => fake()->randomElement(['Alpha Fit', 'Corpo em Movimento', 'Studio Vida', 'Academia Litoral']),
            'razao_social' => fake()->company(),
            'cnpj' => fake()->unique()->numerify('##############'),
            'email' => fake()->unique()->companyEmail(),
            'telefone' => '85'.fake()->numerify('########'),
            'whatsapp' => '85'.fake()->numerify('#########'),
            'cep' => fake()->numerify('########'),
            'logradouro' => fake()->streetName(),
            'numero' => (string) fake()->buildingNumber(),
            'bairro' => fake()->randomElement(['Centro', 'Aldeota', 'Messejana', 'Parangaba']),
            'cidade' => $cidade,
            'uf' => 'CE',
            'situacao' => SituacaoAcademia::Ativa,
            'assinatura_vence_em' => now()->addMonths(6)->toDateString(),
        ];
    }

    public function bloqueada(): self
    {
        return $this->state(fn (): array => [
            'situacao' => SituacaoAcademia::Bloqueada,
            'bloqueada_em' => now(),
            'motivo_bloqueio' => 'Assinatura em aberto há mais de 30 dias.',
        ]);
    }
}
