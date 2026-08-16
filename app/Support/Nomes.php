<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalização de nomes próprios para caixa de título brasileira.
 *
 * "JOSE MARIA DA SILVA", "jose maria da silva" e "joSE MAria dA siLVA" viram
 * todos "Jose Maria da Silva".
 *
 * A normalização acontece AO GRAVAR, não ao exibir. Se ficasse na exibição, a
 * lista ordenada misturaria "SILVA" e "Silva" (o PostgreSQL ordena maiúsculas
 * antes), e buscar por "Silva" não acharia quem foi digitado em caixa alta.
 */
final class Nomes
{
    /**
     * Conectivas que ficam em minúscula — exceto quando abrem o nome.
     *
     * A lista cobre nome de pessoa ("Maria dos Santos e Souza") e também nome
     * de academia, unidade e plano ("Corpo em Movimento", "Espaço para
     * Todos"), porque o mesmo cast atende os dois casos.
     *
     * `a` e `o` sozinhos ficam de fora de propósito: "Studio A" viraria
     * "Studio a", e uma letra isolada quase sempre é sigla, não artigo.
     *
     * @var list<string>
     */
    private const CONECTIVAS = [
        // preposições e artigos contraídos
        'da', 'das', 'de', 'do', 'dos',
        'em', 'na', 'nas', 'no', 'nos',
        'à', 'às', 'ao', 'aos',
        'com', 'para', 'por', 'sem', 'sob',
        'e',
        // partículas estrangeiras comuns em sobrenomes
        'di', 'du', 'del', 'della', 'van', 'von', 'y', 'la', 'le',
    ];

    /**
     * Partículas que mantêm a letra seguinte em maiúscula: D'Ávila, O'Brien.
     *
     * @var list<string>
     */
    private const PREFIXOS_APOSTROFO = ["d'", "o'", "l'"];

    public static function caixaDeTitulo(?string $nome): ?string
    {
        if ($nome === null) {
            return null;
        }

        // Espaços repetidos e nas pontas somem: "Jose  Maria " vira um nome só.
        $limpo = preg_replace('/\s+/u', ' ', trim($nome)) ?? '';

        if ($limpo === '') {
            return '';
        }

        $palavras = explode(' ', mb_strtolower($limpo, 'UTF-8'));

        foreach ($palavras as $posicao => $palavra) {
            // Conectiva no meio do nome fica minúscula; abrindo o nome, não.
            if ($posicao > 0 && in_array($palavra, self::CONECTIVAS, true)) {
                continue;
            }

            $palavras[$posicao] = self::capitalizar($palavra);
        }

        return implode(' ', $palavras);
    }

    private static function capitalizar(string $palavra): string
    {
        foreach (self::PREFIXOS_APOSTROFO as $prefixo) {
            if (str_starts_with($palavra, $prefixo)) {
                $resto = mb_substr($palavra, mb_strlen($prefixo, 'UTF-8'), null, 'UTF-8');

                return mb_strtoupper(mb_substr($prefixo, 0, 1, 'UTF-8'), 'UTF-8')
                    .mb_substr($prefixo, 1, null, 'UTF-8')
                    .self::primeiraMaiuscula($resto);
            }
        }

        // Nome composto por hífen: "Ana-Beatriz" capitaliza os dois lados.
        if (str_contains($palavra, '-')) {
            return implode('-', array_map(
                static fn (string $parte): string => self::primeiraMaiuscula($parte),
                explode('-', $palavra),
            ));
        }

        return self::primeiraMaiuscula($palavra);
    }

    private static function primeiraMaiuscula(string $texto): string
    {
        if ($texto === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($texto, 0, 1, 'UTF-8'), 'UTF-8')
            .mb_substr($texto, 1, null, 'UTF-8');
    }
}
