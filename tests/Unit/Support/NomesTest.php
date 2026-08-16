<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Nomes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NomesTest extends TestCase
{
    #[DataProvider('nomes')]
    public function test_normaliza_para_caixa_de_titulo(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, Nomes::caixaDeTitulo($entrada));
    }

    /** @return array<string, array{string, string}> */
    public static function nomes(): array
    {
        return [
            'tudo maiúsculo' => ['JOSE MARIA DA SILVA', 'Jose Maria da Silva'],
            'tudo minúsculo' => ['jose maria da silva', 'Jose Maria da Silva'],
            'caixa embaralhada' => ['joSE MAria dA siLVA', 'Jose Maria da Silva'],
            'já correto' => ['Jose Maria da Silva', 'Jose Maria da Silva'],

            // Conectivas ficam minúsculas no meio do nome.
            'várias conectivas' => ['MARIA DOS SANTOS E SOUZA', 'Maria dos Santos e Souza'],
            'de e do' => ['pedro de alcantara do nascimento', 'Pedro de Alcantara do Nascimento'],

            // ...mas não quando abrem o nome.
            'conectiva na primeira posição' => ['DA SILVA JUNIOR', 'Da Silva Junior'],

            // O mesmo cast normaliza nome de academia, unidade e plano.
            'nome de academia' => ['academia CORPO em movimento', 'Academia Corpo em Movimento'],
            'com preposição no' => ['VIDA NO RITMO', 'Vida no Ritmo'],
            'com para' => ['espaço PARA todos', 'Espaço para Todos'],

            // Letra isolada quase sempre é sigla, não artigo.
            'letra isolada permanece maiúscula' => ['STUDIO A', 'Studio A'],

            'acentuação preservada' => ['joão césar de assunção', 'João César de Assunção'],
            'apóstrofo' => ["luiz d'avila", "Luiz D'Avila"],
            'nome com hífen' => ['ANA-BEATRIZ NOGUEIRA', 'Ana-Beatriz Nogueira'],

            'espaços repetidos' => ['  jose   maria  ', 'Jose Maria'],
            'nome único' => ['pele', 'Pele'],
            'texto vazio' => ['', ''],
        ];
    }

    public function test_preserva_nulo(): void
    {
        $this->assertNull(Nomes::caixaDeTitulo(null));
    }

    /**
     * Normalizar duas vezes tem de dar o mesmo resultado. Sem isso, editar e
     * salvar um cadastro sem mudar nada alteraria o nome gravado.
     */
    public function test_e_idempotente(): void
    {
        $uma = Nomes::caixaDeTitulo('MARIA DOS SANTOS E SOUZA');
        $duas = Nomes::caixaDeTitulo((string) $uma);

        $this->assertSame($uma, $duas);
    }
}
