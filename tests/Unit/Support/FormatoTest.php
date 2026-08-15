<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Formato;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormatoTest extends TestCase
{
    #[DataProvider('telefones')]
    public function test_formata_telefone(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, Formato::telefone($entrada));
    }

    /** @return array<string, array{string, string}> */
    public static function telefones(): array
    {
        return [
            'celular com código do país' => ['5585996085960', '+55 (85) 99608-5960'],
            'celular já pontuado' => ['+55 (85) 99608-5960', '+55 (85) 99608-5960'],
            'fixo com código do país' => ['558533334444', '+55 (85) 3333-4444'],
            'celular sem código do país' => ['85996085960', '(85) 99608-5960'],
            'fixo sem código do país' => ['8533334444', '(85) 3333-4444'],
            // Formato não reconhecido volta cru: exibir algo estranho é melhor
            // do que exibir um número errado como se fosse certo.
            'formato desconhecido' => ['1234', '1234'],
        ];
    }

    #[DataProvider('valores')]
    public function test_formata_dinheiro(int|float|string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, Formato::dinheiro($entrada));
    }

    /** @return array<string, array{int|float|string, string}> */
    public static function valores(): array
    {
        return [
            'inteiro' => [129, 'R$ 129,00'],
            'com centavos' => [129.9, 'R$ 129,90'],
            'milhar' => [4820, 'R$ 4.820,00'],
            'milhão' => [1234567.89, 'R$ 1.234.567,89'],
            'texto numérico' => ['99.5', 'R$ 99,50'],
            'zero' => [0, 'R$ 0,00'],
        ];
    }
}
