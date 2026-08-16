<?php

declare(strict_types=1);

namespace App\Support\Academia;

/**
 * Como um usuário novo nasce, conforme o papel.
 *
 * Existe para que o cadastro já venha com o comportamento que a maioria das
 * academias espera, sem obrigar o gestor a configurar nada — e para que essa
 * decisão viva num lugar só, em vez de espalhada pelo formulário, pelo seeder
 * e pelos testes.
 *
 * O gestor pode alterar caso a caso; isto é ponto de partida, não regra.
 */
final class PadroesDeAcesso
{
    /**
     * @return array{acessa_todas_unidades: bool, pode_alternar_unidade: bool}
     */
    public static function paraPapel(?string $papel): array
    {
        return match ($papel) {
            // O dono é da rede inteira: enxerga tudo e circula à vontade.
            'dono' => [
                'acessa_todas_unidades' => true,
                'pode_alternar_unidade' => true,
            ],

            // O gerente atua nas filiais que lhe forem vinculadas, e alterna
            // entre elas — é a rotina de quem cobre mais de um balcão.
            'gerente' => [
                'acessa_todas_unidades' => false,
                'pode_alternar_unidade' => true,
            ],

            /*
             * Recepção e professor nascem TRAVADOS na unidade padrão. Quem
             * atende num balcão não tem por que ver o movimento de outro, e
             * liberar depois é decisão consciente do gestor — o inverso não é
             * verdade: ninguém percebe acesso demais até dar problema.
             */
            default => [
                'acessa_todas_unidades' => false,
                'pode_alternar_unidade' => false,
            ],
        };
    }
}
