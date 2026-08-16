<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * As catracas e leitores instalados em cada unidade.
 *
 * `fabricante` fica como texto livre em vez de lista fechada: o mercado tem
 * Control iD, Topdata, Henry e Intelbras hoje, e trocar de fornecedor não
 * pode exigir migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivos_acesso', function (Blueprint $tabela): void {
            $tabela->id();

            $tabela->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();
            $tabela->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();

            $tabela->string('nome');
            $tabela->string('fabricante', 60)->nullable();
            $tabela->string('modelo', 60)->nullable();
            $tabela->string('numero_serie', 60)->nullable();
            $tabela->ipAddress('endereco_ip')->nullable();

            $tabela->string('sentido', 10)->default('ambos');
            $tabela->boolean('ativo')->default(true);

            $tabela->timestampTz('ultima_sincronizacao_em')->nullable();

            $tabela->timestampsTz();

            $tabela->index(['unidade_id', 'ativo']);
        });

        DB::statement("ALTER TABLE dispositivos_acesso ADD CONSTRAINT dispositivos_sentido_valido
            CHECK (sentido IN ('entrada', 'saida', 'ambos'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos_acesso');
    }
};
