<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unidades — as filiais de uma academia.
 *
 * Toda academia nasce com uma. Cliente de uma loja só nunca vê a palavra
 * "unidade" na tela; a estrutura existe para que uma rede não precise ser
 * cadastrada como vários clientes separados.
 *
 * Também é plano de controle: sem RLS por academia. O isolamento entre
 * academias aqui é feito por autorização, e o super administrador precisa
 * enxergar as unidades para dar suporte ao cadastro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $tabela): void {
            $tabela->id();

            $tabela->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();

            $tabela->string('nome');

            $tabela->string('cep', 8)->nullable();
            $tabela->string('logradouro')->nullable();
            $tabela->string('numero', 20)->nullable();
            $tabela->string('complemento')->nullable();
            $tabela->string('bairro')->nullable();
            $tabela->string('cidade')->nullable();
            $tabela->string('uf', 2)->nullable();
            $tabela->string('telefone', 11)->nullable();

            $tabela->boolean('ativa')->default(true);

            $tabela->timestampsTz();

            $tabela->index(['academia_id', 'ativa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades');
    }
};
