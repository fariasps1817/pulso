<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Professores, instrutores e personais.
 *
 * Separado de `users` porque nem todo profissional faz login: muita academia
 * cadastra o professor só para vinculá-lo ao aluno e à ficha. Quando ele
 * ganhar acesso ao sistema, `user_id` aponta para a conta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profissionais', function (Blueprint $tabela): void {
            $tabela->id();

            $tabela->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();
            $tabela->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $tabela->string('nome');
            $tabela->string('cpf', 11)->nullable();
            $tabela->string('cref', 20)->nullable();
            $tabela->string('telefone', 11)->nullable();
            $tabela->string('email')->nullable();

            $tabela->boolean('ativo')->default(true);

            $tabela->timestampsTz();
            $tabela->softDeletesTz();

            $tabela->index(['academia_id', 'ativo']);
        });

        // CPF único por academia, ignorando os excluídos: reaproveitar o CPF
        // de um cadastro apagado é legítimo, colidir com um ativo não é.
        DB::statement('CREATE UNIQUE INDEX profissionais_cpf_por_academia
            ON profissionais (academia_id, cpf)
            WHERE cpf IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('profissionais');
    }
};
