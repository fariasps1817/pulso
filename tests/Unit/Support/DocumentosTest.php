<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Documentos;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentosTest extends TestCase
{
    #[DataProvider('cpfs')]
    public function test_valida_cpf(string $cpf, bool $esperado, string $porque): void
    {
        $this->assertSame($esperado, Documentos::cpfValido($cpf), $porque);
    }

    /** @return array<string, array{string, bool, string}> */
    public static function cpfs(): array
    {
        return [
            'válido sem pontuação' => ['52998224725', true, 'CPF com dígitos verificadores corretos'],
            'válido com pontuação' => ['529.982.247-25', true, 'A máscara não deve atrapalhar'],
            'dígito verificador errado' => ['52998224726', false, 'Último dígito trocado'],
            'curto demais' => ['1234567890', false, 'Menos de 11 dígitos'],
            'longo demais' => ['123456789012', false, 'Mais de 11 dígitos'],
            'vazio' => ['', false, 'Nada digitado'],

            // Sequências passam no cálculo dos verificadores, mas não existem.
            // É o cadastro-lixo mais comum do balcão.
            'todos iguais 1' => ['11111111111', false, 'Sequência repetida'],
            'todos iguais 0' => ['00000000000', false, 'Sequência repetida'],
            'todos iguais 9' => ['99999999999', false, 'Sequência repetida'],
        ];
    }

    #[DataProvider('cnpjs')]
    public function test_valida_cnpj(string $cnpj, bool $esperado): void
    {
        $this->assertSame($esperado, Documentos::cnpjValido($cnpj));
    }

    /** @return array<string, array{string, bool}> */
    public static function cnpjs(): array
    {
        return [
            'válido sem pontuação' => ['11222333000181', true],
            'válido com pontuação' => ['11.222.333/0001-81', true],
            'dígito errado' => ['11222333000182', false],
            'curto demais' => ['1122233300018', false],
            'todos iguais' => ['11111111111111', false],
        ];
    }

    public function test_formata_para_leitura(): void
    {
        $this->assertSame('085.996.085-96', Documentos::formatarCpf('08599608596'));
        $this->assertSame('11.222.333/0001-81', Documentos::formatarCnpj('11222333000181'));
    }

    /** Formato irreconhecível volta cru: exibir estranho é melhor que exibir errado. */
    public function test_formato_desconhecido_volta_como_veio(): void
    {
        $this->assertSame('123', Documentos::formatarCpf('123'));
        $this->assertSame('456', Documentos::formatarCnpj('456'));
    }

    public function test_extrai_apenas_digitos(): void
    {
        $this->assertSame('08599608596', Documentos::apenasDigitos('085.996.085-96'));
        $this->assertSame('85996085960', Documentos::apenasDigitos('(85) 99608-5960'));
        $this->assertSame('', Documentos::apenasDigitos(null));
    }
}
