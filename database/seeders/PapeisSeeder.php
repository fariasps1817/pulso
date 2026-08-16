<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Papéis e permissões, conforme a matriz de docs/dominio/README.md §4.2.1.
 *
 * Três regras sustentam a matriz:
 *
 *   1. Professor não vê dinheiro. Nem valor de plano, nem mensalidade, nem
 *      quem está devendo. Vê aluno e frequência.
 *   2. Recepção vê a inadimplência do aluno na ficha — precisa saber para
 *      atender — mas não o relatório financeiro consolidado.
 *   3. Gerente atua nas unidades a que está vinculado; dono enxerga a rede.
 */
final class PapeisSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const PERMISSOES = [
        'academia' => ['configurar', 'ver'],
        'unidade' => ['ver', 'criar', 'editar'],
        'usuario' => ['ver', 'criar', 'editar', 'excluir'],
        'plano' => ['ver', 'criar', 'editar', 'excluir'],
        'aluno' => ['ver', 'criar', 'editar', 'excluir'],
        'matricula' => ['ver', 'criar', 'editar', 'encerrar'],
        'mensalidade' => ['ver', 'receber', 'estornar'],
        'biometria' => ['ver', 'cadastrar', 'excluir'],
        'dispositivo' => ['ver', 'criar', 'editar'],
        'radar' => ['ver'],
        'relatorio_financeiro' => ['ver'],
        'frequencia' => ['ver'],
    ];

    /** @var array<string, list<string>> */
    private const PAPEIS = [
        'dono' => ['*'],

        'gerente' => [
            'academia.ver',
            'unidade.ver',
            'plano.ver',
            'aluno.ver', 'aluno.criar', 'aluno.editar', 'aluno.excluir',
            'matricula.ver', 'matricula.criar', 'matricula.editar', 'matricula.encerrar',
            'mensalidade.ver', 'mensalidade.receber', 'mensalidade.estornar',
            'biometria.ver', 'biometria.cadastrar', 'biometria.excluir',
            'dispositivo.ver', 'dispositivo.criar', 'dispositivo.editar',
            'radar.ver',
            'relatorio_financeiro.ver',
            'frequencia.ver',
        ],

        'recepcao' => [
            'plano.ver',
            'aluno.ver', 'aluno.criar', 'aluno.editar',
            'matricula.ver', 'matricula.criar', 'matricula.editar',
            'mensalidade.ver', 'mensalidade.receber',
            'biometria.ver', 'biometria.cadastrar',
            'radar.ver',
            'frequencia.ver',
            // Sem relatorio_financeiro.ver: precisa saber que o aluno deve
            // para atendê-lo, não conhecer o faturamento da academia.
        ],

        'professor' => [
            'aluno.ver',
            'frequencia.ver',
            // Nada de dinheiro. Nem valor de plano.
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $todas = [];

        foreach (self::PERMISSOES as $recurso => $acoes) {
            foreach ($acoes as $acao) {
                $nome = "{$recurso}.{$acao}";
                $todas[] = $nome;

                Permission::findOrCreate($nome, 'web');
            }
        }

        foreach (self::PAPEIS as $papel => $permissoes) {
            /*
             * Papel sem academia (team_id nulo) funciona como modelo: cada
             * academia recebe a sua cópia ao ser criada. Assim, quem é gerente
             * na Alpha Fit não vira gerente na Body Tech.
             */
            $registro = Role::findOrCreate($papel, 'web');

            $registro->syncPermissions($permissoes === ['*'] ? $todas : $permissoes);
        }
    }
}
