<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferências de interface do usuário.
 *
 * Vão num documento JSON, e não em colunas, porque a lista cresce — tema,
 * barra lateral, itens por página, colunas visíveis, filtros salvos — e cada
 * item novo viraria uma migration. Nenhuma delas é consultada em WHERE: são
 * lidas junto com o usuário.
 *
 * Ficam no perfil, e não só no navegador, porque a equipe alterna entre o
 * computador do balcão e o próprio celular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabela): void {
            $tabela->jsonb('preferencias')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabela): void {
            $tabela->dropColumn('preferencias');
        });
    }
};
