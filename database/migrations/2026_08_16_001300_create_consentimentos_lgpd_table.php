<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consentimento do aluno para o tratamento de dado biométrico.
 *
 * Biometria é dado sensível (LGPD, art. 11). O consentimento precisa ser
 * ESPECÍFICO e SEPARADO do contrato de matrícula, com a finalidade escrita —
 * por isso é tabela própria, e não uma caixa marcada no cadastro.
 *
 * `versao_texto` guarda qual redação a pessoa leu: mudando o texto amanhã, o
 * que ela consentiu ontem continua demonstrável.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consentimentos_lgpd', function (Blueprint $tabela): void {
            $tabela->id();

            $tabela->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();
            $tabela->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();

            // Escrita, não implícita: "controle de acesso e frequência".
            $tabela->string('finalidade');
            $tabela->string('versao_texto', 40);
            $tabela->text('texto_apresentado');

            $tabela->timestampTz('aceito_em');
            // Revogou: o template é apagado e a credencial vira cartão.
            $tabela->timestampTz('revogado_em')->nullable();

            $tabela->string('origem', 20)->default('recepcao');
            $tabela->ipAddress('ip')->nullable();

            $tabela->timestampsTz();

            $tabela->index(['aluno_id', 'revogado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimentos_lgpd');
    }
};
