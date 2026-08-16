<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Academia;
use App\Services\Catraca\MotorDeAcesso;
use App\Support\Academia\ContextoAcademia;
use Illuminate\Console\Command;

/**
 * Fecha as entradas que ninguém encerrou.
 *
 * Como a catraca não informa o sentido do giro, o Pulso deduz alternando. Quem
 * entra e vai embora sem passar de novo — porque saiu pela porta lateral, ou
 * porque a academia fechou — continuaria marcado como presente indefinidamente.
 *
 * A saída fica registrada como PRESUMIDA, e por isso não gera tempo de
 * permanência: ninguém mediu a hora de saída, e um número inventado num
 * relatório é pior do que a ausência dele.
 *
 * Percorre as academias pela conexão da aplicação, definindo o contexto de
 * cada uma — o Row Level Security a autoriza uma a uma, sem precisar de um
 * papel que atravessa o isolamento.
 */
final class FecharAcessos extends Command
{
    protected $signature = 'pulso:fechar-acessos';

    protected $description = 'Encerra as entradas na catraca que ficaram abertas além da tolerância.';

    public function handle(ContextoAcademia $contexto): int
    {
        $total = 0;

        foreach (Academia::query()->get() as $academia) {
            $fechadas = $contexto->paraAcademia(
                $academia->id,
                fn (): int => MotorDeAcesso::encerrarEntradasAbandonadas(),
            );

            if ($fechadas > 0) {
                $this->line("  {$academia->nome}: {$fechadas} entradas encerradas.");
            }

            $total += $fechadas;
        }

        $contexto->limpar();

        $this->info("{$total} entradas encerradas como saída presumida.");

        return self::SUCCESS;
    }
}
