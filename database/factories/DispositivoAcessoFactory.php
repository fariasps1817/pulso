<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DispositivoAcesso;
use Database\Factories\Concerns\UsaAcademiaDoContexto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DispositivoAcesso> */
final class DispositivoAcessoFactory extends Factory
{
    use UsaAcademiaDoContexto;

    protected $model = DispositivoAcesso::class;

    /** @return array<model-property<DispositivoAcesso>, mixed> */
    public function definition(): array
    {
        return [
            'academia_id' => $this->academiaDoContexto(),
            'nome' => 'Catraca da entrada',
            'fabricante' => 'ZKTeco',
            'modelo' => 'SenseFace 2A',
            // O formato do serial real do equipamento de referência.
            'numero_serie' => strtoupper(fake()->bothify('???##########')),
            'sentido' => 'ambos',
            'ativo' => true,
        ];
    }
}
