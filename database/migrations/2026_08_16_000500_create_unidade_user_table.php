<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A quais unidades cada usuário tem acesso.
 *
 * Ausência de linha significa "todas as unidades da academia" — é o caso do
 * dono, e evita ter de criar um vínculo novo toda vez que uma filial abre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidade_user', function (Blueprint $tabela): void {
            $tabela->id();

            $tabela->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $tabela->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $tabela->timestampsTz();

            $tabela->unique(['unidade_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidade_user');
    }
};
