<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Como o aluno se identifica na catraca: rosto, digital ou cartão.
 *
 * REGRAS QUE NÃO SE QUEBRAM (LGPD, art. 11):
 *
 * 1. `template` guarda o TEMPLATE biométrico cifrado — nunca a imagem do
 *    rosto. Vazar uma foto é ruim; vazar um banco de rostos é irreversível,
 *    porque ninguém troca de rosto como troca de senha.
 * 2. Credencial biométrica exige consentimento registrado. A constraint
 *    abaixo recusa a linha sem ele — não é validação de formulário, é regra
 *    do banco.
 * 3. O cartão é a alternativa obrigatória para quem recusa dar biometria, e
 *    funciona desde o primeiro dia. Não é item de backlog.
 * 4. Ao cancelar a matrícula ou revogar o consentimento, o template é apagado
 *    DE VERDADE (`template` vira nulo) e `excluida_em` registra que houve
 *    exclusão — a demonstração de que se apagou também é obrigação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credenciais_acesso', function (Blueprint $tabela): void {
            $tabela->id();

            $tabela->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();
            $tabela->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $tabela->foreignId('consentimento_id')->nullable()
                ->constrained('consentimentos_lgpd')->nullOnDelete();

            $tabela->string('tipo', 20);

            // Cifrado pela aplicação antes de chegar aqui.
            $tabela->binary('template')->nullable();
            $tabela->string('identificador_cartao')->nullable();

            $tabela->boolean('ativa')->default(true);

            // Auditoria: quem coletou a biometria e quando.
            $tabela->foreignId('cadastrada_por')->nullable()->constrained('users')->nullOnDelete();
            $tabela->timestampTz('cadastrada_em');
            $tabela->timestampTz('excluida_em')->nullable();

            $tabela->timestampsTz();

            $tabela->index(['aluno_id', 'ativa']);
        });

        DB::statement("ALTER TABLE credenciais_acesso ADD CONSTRAINT credenciais_tipo_valido
            CHECK (tipo IN ('facial', 'digital', 'cartao'))");

        // Sem consentimento registrado, não há credencial biométrica.
        DB::statement("ALTER TABLE credenciais_acesso ADD CONSTRAINT credenciais_biometria_exige_consentimento
            CHECK (tipo = 'cartao' OR consentimento_id IS NOT NULL)");

        // Cartão sem número não identifica ninguém.
        DB::statement("ALTER TABLE credenciais_acesso ADD CONSTRAINT credenciais_cartao_exige_identificador
            CHECK (tipo <> 'cartao' OR identificador_cartao IS NOT NULL)");

        DB::statement('CREATE UNIQUE INDEX credenciais_cartao_por_academia
            ON credenciais_acesso (academia_id, identificador_cartao)
            WHERE identificador_cartao IS NOT NULL AND ativa');
    }

    public function down(): void
    {
        Schema::dropIfExists('credenciais_acesso');
    }
};
