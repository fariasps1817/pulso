<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Precisa rodar pela conexão de manutenção, que atravessa academias:
     *
     *     php artisan db:seed --database=pgsql_admin
     *
     * Pela conexão da aplicação, as políticas de Row Level Security recusariam
     * a gravação em cada academia que o seeder tentasse popular — corretamente.
     */
    public function run(): void
    {
        $this->call([
            PapeisSeeder::class,
            DemonstracaoSeeder::class,
        ]);
    }
}
