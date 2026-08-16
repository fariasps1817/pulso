<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SituacaoMatricula;
use App\Enums\SituacaoMensalidade;
use App\Enums\TipoMatricula;
use App\Models\Academia;
use App\Models\Aluno;
use App\Models\Matricula;
use App\Models\Mensalidade;
use App\Models\Plano;
use App\Models\Unidade;
use App\Models\User;
use App\Support\Academia\ContextoAcademia;
use App\Support\Academia\PadroesDeAcesso;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dados de demonstração para desenvolvimento.
 *
 * Cria DUAS academias de propósito: com uma só, um vazamento de isolamento
 * passaria despercebido no ambiente local e só apareceria em produção, com
 * dado de cliente.
 *
 * As situações cobrem o que o Radar precisa mostrar: em dia, a vencer,
 * vencida e baixa frequência.
 */
final class DemonstracaoSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * NAO RODA DUAS VEZES.
         *
         * Sem esta guarda, executar o seeder num banco ja populado criava uma
         * SEGUNDA Alpha Fit e uma segunda Corpo em Movimento — clientes
         * duplicados na tela do super administrador, com o mesmo e-mail de
         * dono em ambas. E o login, que recebe so o e-mail, ficava sem saber
         * em qual entrar.
         *
         * Aconteceu de verdade. E o rastro nao era obvio: a execucao morria
         * depois, no e-mail unico do super administrador, deixando o banco
         * meio populado e a mensagem de erro apontando para o lugar errado.
         */
        if (Academia::query()->exists()) {
            $this->command->warn('Ja existem academias cadastradas — o seeder de demonstracao nao roda de novo.');
            $this->command->line('Para recomecar do zero: php artisan migrate:fresh --database=pgsql_admin --seed');

            return;
        }

        $contexto = app(ContextoAcademia::class);

        $alpha = $this->criarAcademia('Alpha Fit', 'Fortaleza', comFiliais: true);
        $beta = $this->criarAcademia('Corpo em Movimento', 'Cascavel', comFiliais: false);

        foreach ([$alpha, $beta] as $academia) {
            $contexto->paraAcademia($academia->id, function () use ($academia): void {
                $this->popular($academia);
            });
        }

        $contexto->limpar();

        // Equipe do Pulso: sem academia, e sem enxergar dado de academia
        // alguma — o RLS não abre exceção nem para ela.
        User::factory()->superAdministrador()->create([
            'name' => 'Equipe Pulso',
            'email' => 'suporte@usepulso.com.br',
            'password' => Hash::make('pulso1234'),
        ]);

        $this->command->info('Academias: Alpha Fit (3 unidades) e Corpo em Movimento (1 unidade).');
        $this->command->newLine();
        $this->command->info('Dono da academia .... dono@alpha-fit.com.br / pulso1234');
        $this->command->info('Recepção ............ recepcao@alpha-fit.com.br / pulso1234');
        $this->command->info('Equipe do Pulso ..... suporte@usepulso.com.br / pulso1234');
    }

    private function criarAcademia(string $nome, string $cidade, bool $comFiliais): Academia
    {
        $academia = Academia::factory()->create([
            'nome' => $nome,
            'cidade' => $cidade,
            'uf' => 'CE',
        ]);

        $unidades = $comFiliais ? ['Matriz', 'Aldeota', 'Praia'] : ['Matriz'];

        foreach ($unidades as $nomeUnidade) {
            Unidade::factory()->create([
                'academia_id' => $academia->id,
                'nome' => $nomeUnidade,
                'cidade' => $cidade,
            ]);
        }

        return $academia;
    }

    private function popular(Academia $academia): void
    {
        /*
         * Os papéis são por academia. Sem esta linha, `assignRole` gravaria
         * academia_id nulo em model_has_roles — e a coluna é obrigatória
         * justamente para que "gerente" nunca signifique "gerente em todas".
         */
        setPermissionsTeamId($academia->id);

        $unidade = $academia->unidades()->first();
        $apelido = str($academia->nome)->slug()->toString();

        // ---------------- equipe ----------------
        // Nomes de pessoa de verdade: "Dono da Alpha Fit" no cabeçalho vira
        // "Dono Alpha", que não ajuda a avaliar como a tela fica em uso real.
        [$nomeDono, $nomeRecepcao] = $academia->nome === 'Alpha Fit'
            ? ['Vladir Alencar de Sousa', 'Patrícia Gomes Lima']
            : ['Rita de Cássia Barroso', 'Douglas Feitosa Rocha'];

        $dono = User::factory()->daAcademia($academia->id)->create([
            'name' => $nomeDono,
            'email' => "dono@{$apelido}.com.br",
            'password' => Hash::make('pulso1234'),
            ...PadroesDeAcesso::paraPapel('dono'),
        ]);
        $dono->assignRole('dono');

        // A recepção nasce travada na unidade dela: quem atende num balcão não
        // tem por que ver o movimento de outro.
        $recepcao = User::factory()->daAcademia($academia->id)->create([
            'name' => $nomeRecepcao,
            'email' => "recepcao@{$apelido}.com.br",
            'password' => Hash::make('pulso1234'),
            'unidade_padrao_id' => $unidade->id,
            ...PadroesDeAcesso::paraPapel('recepcao'),
        ]);
        $recepcao->assignRole('recepcao');
        $recepcao->unidades()->attach($unidade->id);

        // ---------------- planos ----------------
        $mensal = Plano::factory()->create([
            'nome' => 'Mensal Musculação',
            'valor_mensal' => 129.90,
        ]);

        $anual = Plano::factory()->anual()->create();

        // ---------------- alunos e situações ----------------
        $hoje = CarbonImmutable::now();

        $cenarios = [
            ['nome' => 'Ana Beatriz Nogueira', 'plano' => $mensal, 'estado' => 'paga'],
            ['nome' => 'Carlos Eduardo Lima', 'plano' => $anual, 'estado' => 'a_vencer'],
            ['nome' => 'Jonas Ferreira Alves', 'plano' => $mensal, 'estado' => 'vencida'],
            ['nome' => 'Marina Sousa Vieira', 'plano' => $anual, 'estado' => 'sumiu'],
            ['nome' => 'Rafael Queiroz Matos', 'plano' => $mensal, 'estado' => 'experiencia'],
        ];

        foreach ($cenarios as $cenario) {
            $aluno = Aluno::factory()->create(['nome' => $cenario['nome']]);

            if ($cenario['estado'] === 'experiencia') {
                Matricula::factory()->emExperiencia()->create([
                    'unidade_id' => $unidade->id,
                    'aluno_id' => $aluno->id,
                    'plano_id' => $cenario['plano']->id,
                    'valor_mensal' => $cenario['plano']->valor_mensal,
                ]);

                continue;
            }

            $matricula = Matricula::factory()->create([
                'unidade_id' => $unidade->id,
                'aluno_id' => $aluno->id,
                'plano_id' => $cenario['plano']->id,
                'tipo' => TipoMatricula::Regular,
                'situacao' => SituacaoMatricula::Ativa,
                'valor_mensal' => $cenario['plano']->valor_mensal,
            ]);

            $mensalidade = [
                'unidade_id' => $unidade->id,
                'matricula_id' => $matricula->id,
                'aluno_id' => $aluno->id,
                'valor' => $cenario['plano']->valor_mensal,
                'competencia' => $hoje->startOfMonth()->toDateString(),
            ];

            match ($cenario['estado']) {
                'paga' => Mensalidade::factory()->create($mensalidade + [
                    'situacao' => SituacaoMensalidade::Paga,
                    'vencimento' => $hoje->subDays(3)->toDateString(),
                    'paga_em' => $hoje->subDays(3)->toDateString(),
                ]),
                'a_vencer' => Mensalidade::factory()->create($mensalidade + [
                    'vencimento' => $hoje->toDateString(),
                ]),
                // "Vencida" é aberta com vencimento no passado — não há
                // situação "vencida" no banco, e é assim de propósito.
                'vencida', 'sumiu' => Mensalidade::factory()->create($mensalidade + [
                    'vencimento' => $hoje->subDays(12)->toDateString(),
                ]),
            };
        }
    }
}
