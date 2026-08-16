<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Aluno;
use Database\Factories\Concerns\UsaAcademiaDoContexto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Aluno> */
final class AlunoFactory extends Factory
{
    use UsaAcademiaDoContexto;

    protected $model = Aluno::class;

    /** @return array<model-property<Aluno>, mixed> */
    public function definition(): array
    {
        return [
            'academia_id' => $this->academiaDoContexto(),
            'nome' => fake()->name(),
            // CPF gerado com dígitos verificadores válidos: cadastro de teste
            // que não passa na própria validação não serve para testar nada.
            'cpf' => self::cpfValido(),
            /*
             * Maior de idade por padrão. Um menor gerado ao acaso viria sem
             * responsável — dado que o formulário recusa — e faria testes
             * passarem ou falharem conforme o sorteio. Para o caso do menor
             * existe o estado ->menorDeIdade().
             */
            'data_nascimento' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'sexo' => fake()->randomElement(['M', 'F']),
            'email' => fake()->unique()->safeEmail(),
            'whatsapp' => '85'.fake()->numerify('#########'),
            'cep' => fake()->numerify('########'),
            'logradouro' => fake()->streetName(),
            'numero' => (string) fake()->buildingNumber(),
            'bairro' => fake()->randomElement(['Centro', 'Messejana', 'Parangaba', 'Aldeota']),
            'cidade' => 'Fortaleza',
            'uf' => 'CE',
        ];
    }

    public function menorDeIdade(): self
    {
        return $this->state(fn (): array => [
            'data_nascimento' => now()->subYears(14)->toDateString(),
            'responsavel_nome' => fake()->name(),
            'responsavel_cpf' => self::cpfValido(),
            'responsavel_telefone' => '85'.fake()->numerify('#########'),
            'responsavel_parentesco' => 'Mãe',
        ]);
    }

    /** Gera um CPF que passa na validação dos dígitos verificadores. */
    public static function cpfValido(): string
    {
        $base = '';

        for ($i = 0; $i < 9; $i++) {
            $base .= random_int(0, 9);
        }

        foreach ([10, 11] as $peso) {
            $soma = 0;

            for ($i = 0; $i < $peso - 1; $i++) {
                $soma += (int) $base[$i] * ($peso - $i);
            }

            $resto = $soma % 11;
            $base .= $resto < 2 ? '0' : (string) (11 - $resto);
        }

        return $base;
    }
}
