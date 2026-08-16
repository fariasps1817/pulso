<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SituacaoMensalidade;
use App\Models\Aluno;
use App\Models\Matricula;
use App\Models\Mensalidade;
use App\Models\Unidade;
use Database\Factories\Concerns\UsaAcademiaDoContexto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Mensalidade> */
final class MensalidadeFactory extends Factory
{
    use UsaAcademiaDoContexto;

    protected $model = Mensalidade::class;

    /** @return array<model-property<Mensalidade>, mixed> */
    public function definition(): array
    {
        return [
            'academia_id' => $this->academiaDoContexto(),
            'unidade_id' => Unidade::factory(),
            'matricula_id' => Matricula::factory(),
            'aluno_id' => Aluno::factory(),
            'competencia' => now()->startOfMonth()->toDateString(),
            'vencimento' => now()->startOfMonth()->addDays(4)->toDateString(),
            'valor' => 129.90,
            'desconto' => 0,
            'situacao' => SituacaoMensalidade::Aberta,
        ];
    }

    /** Aberta com vencimento no passado — é assim que "vencida" existe. */
    public function vencida(int $diasAtras = 10): self
    {
        return $this->state(fn (): array => [
            'situacao' => SituacaoMensalidade::Aberta,
            'vencimento' => now()->subDays($diasAtras)->toDateString(),
        ]);
    }

    public function paga(): self
    {
        return $this->state(fn (): array => [
            'situacao' => SituacaoMensalidade::Paga,
            'paga_em' => now()->toDateString(),
        ]);
    }

    public function vencendoHoje(): self
    {
        return $this->state(fn (): array => [
            'situacao' => SituacaoMensalidade::Aberta,
            'vencimento' => now()->toDateString(),
        ]);
    }
}
