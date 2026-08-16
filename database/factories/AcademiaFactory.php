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

    /**
     * CNPJ com dígito verificador correto.
     *
     * Antes, a fábrica gerava catorze dígitos ao acaso — e a academia de
     * demonstração nascia com um CNPJ que a validação recusava, impedindo o
     * gestor de salvar QUALQUER alteração na tela de configurações. Dado de
     * teste que não passaria pela validação da tela esconde exatamente o tipo
     * de defeito que ele deveria revelar.
     */
    private static function cnpjValido(): string
    {
        $base = [];

        foreach (range(1, 12) as $ignorado) {
            $base[] = random_int(0, 9);
        }

        foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $pesos) {
            $soma = 0;

            foreach ($pesos as $posicao => $peso) {
                $soma += $base[$posicao] * $peso;
            }

            $resto = $soma % 11;
            $base[] = $resto < 2 ? 0 : 11 - $resto;
        }

        return implode('', $base);
    }

    /** @return array<model-property<Academia>, mixed> */
    public function definition(): array
    {
        $cidade = fake()->randomElement(['Fortaleza', 'Cascavel', 'Aquiraz', 'Maracanaú', 'Sobral']);

        return [
            'nome' => fake()->randomElement(['Alpha Fit', 'Corpo em Movimento', 'Studio Vida', 'Academia Litoral']),
            'razao_social' => fake()->company(),
            'cnpj' => self::cnpjValido(),
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
