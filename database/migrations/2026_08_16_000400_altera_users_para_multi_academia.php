<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Usuários do sistema, agora vinculados a uma academia.
 *
 * `academia_id` NULO identifica o super administrador — a equipe do Pulso, que
 * não pertence a academia nenhuma. Ele opera apenas o plano de controle
 * (academias, unidades, avisos e usuários) e não enxerga aluno, mensalidade
 * nem biometria: as políticas de RLS não abrem exceção para ele.
 *
 * O e-mail deixa de ser único globalmente e passa a ser único POR ACADEMIA:
 * a mesma pessoa pode trabalhar em duas academias com o mesmo e-mail, e
 * impedir isso obrigaria a inventar um endereço falso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabela): void {
            $tabela->foreignId('academia_id')->nullable()->after('id')
                ->constrained('academias')->cascadeOnDelete();

            // Sessão única por padrão: entrar em outro aparelho derruba a
            // sessão anterior. É o comportamento esperado num sistema onde a
            // conta da recepção fica em computador compartilhado.
            $tabela->boolean('sessao_unica')->default(true)->after('preferencias');

            // Nulo usa o padrão do sistema (config/pulso.php).
            $tabela->smallInteger('minutos_inatividade')->nullable()->after('sessao_unica');

            // Demitiu, desativa. Nunca apaga: o histórico perderia o autor de
            // cada pagamento registrado.
            $tabela->boolean('ativo')->default(true)->after('minutos_inatividade');

            // Preenchido pelo bloqueio por tentativas de login.
            $tabela->timestampTz('bloqueado_ate')->nullable()->after('ativo');
            $tabela->timestampTz('ultimo_acesso_em')->nullable()->after('bloqueado_ate');

            $tabela->index(['academia_id', 'ativo']);
        });

        /*
         * O índice único global de e-mail dá lugar a um por academia.
         * Super administradores (academia_id nulo) entram num índice próprio,
         * porque no PostgreSQL dois NULOs não colidem num índice comum — sem
         * este segundo índice, seria possível cadastrar o mesmo e-mail duas
         * vezes na equipe do Pulso.
         */
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique');

        DB::statement('CREATE UNIQUE INDEX users_email_por_academia
            ON users (academia_id, email) WHERE academia_id IS NOT NULL');

        DB::statement('CREATE UNIQUE INDEX users_email_super_admin
            ON users (email) WHERE academia_id IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_por_academia');
        DB::statement('DROP INDEX IF EXISTS users_email_super_admin');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_unique UNIQUE (email)');

        Schema::table('users', function (Blueprint $tabela): void {
            $tabela->dropConstrainedForeignId('academia_id');
            $tabela->dropColumn([
                'sessao_unica',
                'minutos_inatividade',
                'ativo',
                'bloqueado_ate',
                'ultimo_acesso_em',
            ]);
        });
    }
};
