<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Row Level Security — o isolamento entre academias, no banco.
 *
 * Esta é a rede de proteção que faz o dado de uma academia não vazar para
 * outra mesmo que alguém esqueça um filtro no código. A aplicação já filtra
 * por conta própria (ver App\Models\Concerns\PertenceAAcademia); aqui é o
 * banco recusando por si.
 *
 * QUATRO DETALHES DECIDEM SE ISSO FUNCIONA OU VIRA ENFEITE:
 *
 * 1. FORCE ROW LEVEL SECURITY — sem ele, o DONO da tabela ignora a política.
 *    Como as migrations rodam pela conexão de manutenção, o dono seria
 *    justamente quem escapa.
 * 2. O usuário da aplicação não pode ser SUPERUSER nem ter BYPASSRLS. Já
 *    garantido em database/postgres/01-bancos-e-papeis.sql e verificado.
 * 3. current_setting(..., true) devolve NULO quando a variável não foi
 *    definida (console, filas). A comparação com NULO é falsa, então NENHUMA
 *    linha aparece — falha fechando, que é o comportamento certo.
 * 4. NULLIF trata a string vazia: '' não é bigint válido e derrubaria a
 *    consulta com erro de conversão.
 *
 * FICAM DE FORA, de propósito:
 *   academias, unidades, avisos_academia  plano de controle do super admin
 *   users, sessions, tentativas_login     autenticação acontece antes de
 *                                          existir "academia atual"
 *   roles/permissions (spatie)            catálogo de papéis
 *   cache, jobs, migrations               infraestrutura
 */
return new class extends Migration
{
    /**
     * Tabelas de domínio. Todas têm academia_id e todas ficam isoladas.
     *
     * @var list<string>
     */
    private const TABELAS = [
        'profissionais',
        'alunos',
        'planos',
        'matriculas',
        'mensalidades',
        'cobrancas',
        'pagamentos',
        'consentimentos_lgpd',
        'credenciais_acesso',
        'dispositivos_acesso',
        'acessos',
        'notificacoes',
    ];

    public function up(): void
    {
        /*
         * Uma função em vez de repetir a expressão em 24 políticas: se um dia
         * a origem da academia atual mudar, muda em um lugar só.
         *
         * STABLE permite ao planejador avaliá-la uma vez por consulta, em vez
         * de uma vez por linha — numa tabela de acessos com milhões de
         * registros, a diferença é grande.
         */
        DB::statement("
            CREATE OR REPLACE FUNCTION pulso_academia_atual() RETURNS bigint
            LANGUAGE sql STABLE AS \$\$
                SELECT NULLIF(current_setting('pulso.academia_id', true), '')::bigint
            \$\$
        ");

        foreach (self::TABELAS as $tabela) {
            DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");

            /*
             * USING filtra o que se pode LER (e alterar/apagar).
             * WITH CHECK impede GRAVAR linha de outra academia — sem ele, um
             * INSERT com academia_id alheio passaria despercebido.
             */
            DB::statement("
                CREATE POLICY isolamento_academia ON {$tabela}
                    USING      (academia_id = pulso_academia_atual())
                    WITH CHECK (academia_id = pulso_academia_atual())
            ");
        }

        /*
         * A conexão de manutenção (migrations, comandos de console, rotinas de
         * geração de mensalidade) precisa enxergar todas as academias. Ela usa
         * um papel próprio, e este é o único caminho legítimo para atravessar
         * o isolamento — explícito, nomeado e auditável.
         */
        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'pulso_manutencao') THEN
                    CREATE ROLE pulso_manutencao NOLOGIN BYPASSRLS;
                END IF;
            END
            \$\$
        ");
    }

    public function down(): void
    {
        foreach (self::TABELAS as $tabela) {
            DB::statement("DROP POLICY IF EXISTS isolamento_academia ON {$tabela}");
            DB::statement("ALTER TABLE {$tabela} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$tabela} DISABLE ROW LEVEL SECURITY");
        }

        DB::statement('DROP FUNCTION IF EXISTS pulso_academia_atual()');
    }
};
