<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SituacaoMatricula;
use App\Enums\TipoMatricula;
use App\Models\Aluno;
use App\Models\Matricula;
use App\Models\Plano;
use App\Models\Unidade;
use Database\Factories\Concerns\UsaAcademiaDoContexto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Matricula> */
final class MatriculaFactory extends Factory
{
    use UsaAcademiaDoContexto;

    protected $model = Matricula::class;

    /** @return array<model-property<Matricula>, mixed> */
    public function definition(): array
    {
        return [
            'academia_id' => $this->academiaDoContexto(),
            'unidade_id' => Unidade::factory(),
            'aluno_id' => Aluno::factory(),
            'plano_id' => Plano::factory(),
            'tipo' => TipoMatricula::Regular,
            'situacao' => SituacaoMatricula::Ativa,
            // Matrícula regular não existe sem contrato: a própria constraint
            // do banco recusaria a linha.
            'contrato_assinado_em' => now()->subMonths(3)->toDateString(),
            'inicio_em' => now()->subMonths(3)->toDateString(),
            'dia_vencimento' => 5,
            'valor_mensal' => 129.90,
        ];
    }

    public function emExperiencia(): self
    {
        return $this->state(fn (): array => [
            'tipo' => TipoMatricula::Experiencia,
            'situacao' => SituacaoMatricula::Experiencia,
            'contrato_assinado_em' => null,
            'inicio_em' => now()->toDateString(),
            'sessoes_usadas' => 0,
        ]);
    }

    public function suspensa(): self
    {
        return $this->state(fn (): array => ['situacao' => SituacaoMatricula::Suspensa]);
    }

    public function encerrada(): self
    {
        return $this->state(fn (): array => [
            'situacao' => SituacaoMatricula::Encerrada,
            'encerrada_em' => now()->subDays(10)->toDateString(),
            'motivo_encerramento' => 'Mudou de cidade.',
        ]);
    }
}
