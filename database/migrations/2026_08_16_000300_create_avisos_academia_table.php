<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos que o super administrador faz aparecer na tela da academia.
 *
 * Serve para o recado comercial ("sua assinatura vence em 5 dias") sem que
 * ninguém precise telefonar. Com academia_id nulo, o aviso vale para todas —
 * é como se anuncia manutenção programada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos_academia', function (Blueprint $tabela): void {
            $tabela->id();

            // Nulo = aviso para todas as academias.
            $tabela->foreignId('academia_id')->nullable()->constrained('academias')->cascadeOnDelete();

            $tabela->string('tipo', 30)->default('informativo');
            $tabela->string('titulo');
            $tabela->text('mensagem');

            $tabela->date('exibir_de');
            $tabela->date('exibir_ate');

            // Aviso de bloqueio iminente não se dispensa: some da tela e o
            // dono descobre bloqueado na segunda-feira.
            $tabela->boolean('dispensavel')->default(true);

            $tabela->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();

            $tabela->timestampsTz();

            $tabela->index(['academia_id', 'exibir_de', 'exibir_ate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_academia');
    }
};
