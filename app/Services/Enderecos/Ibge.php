<?php

declare(strict_types=1);

namespace App\Services\Enderecos;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Estados e municípios, da API de localidades do IBGE.
 *
 * Cidade e UF NUNCA são texto livre. Digitação livre produz "Fortaleza",
 * "fortaleza", "Fortaleza-CE" e "Frotaleza" na mesma base, e nenhum relatório
 * por cidade funciona depois.
 *
 * A lista fica em cache longo — o IBGE muda a divisão municipal raramente — e
 * há uma lista fixa de estados como último recurso: o cadastro não pode
 * depender de um serviço externo estar no ar.
 */
final class Ibge
{
    private const SEGUNDOS_LIMITE = 4;

    private const DIAS_EM_CACHE = 60;

    /**
     * Siglas e nomes dos 26 estados e o Distrito Federal.
     *
     * @return array<string, string>
     */
    public function estados(): array
    {
        return Cache::remember('ibge:estados', now()->addDays(self::DIAS_EM_CACHE), function (): array {
            $dados = $this->buscar('https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome');

            if ($dados === null) {
                return self::ESTADOS_DE_RESERVA;
            }

            $estados = [];

            foreach ($dados as $estado) {
                $estados[(string) $estado['sigla']] = (string) $estado['nome'];
            }

            return $estados === [] ? self::ESTADOS_DE_RESERVA : $estados;
        });
    }

    /**
     * Municípios de um estado, em ordem alfabética.
     *
     * @return list<string>
     */
    public function municipios(string $uf): array
    {
        $uf = mb_strtoupper(trim($uf));

        if (! array_key_exists($uf, self::ESTADOS_DE_RESERVA)) {
            return [];
        }

        return Cache::remember("ibge:municipios:{$uf}", now()->addDays(self::DIAS_EM_CACHE), function () use ($uf): array {
            $dados = $this->buscar(
                "https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$uf}/municipios?orderBy=nome",
            );

            if ($dados === null) {
                return [];
            }

            return array_map(
                static fn (array $municipio): string => (string) $municipio['nome'],
                $dados,
            );
        });
    }

    /** @return list<array<string, mixed>>|null */
    private function buscar(string $url): ?array
    {
        try {
            $resposta = Http::timeout(self::SEGUNDOS_LIMITE)->get($url);

            if ($resposta->failed()) {
                return null;
            }

            $dados = $resposta->json();

            return is_array($dados) ? $dados : null;
        } catch (Throwable $erro) {
            Log::warning('IBGE indisponível.', ['url' => $url, 'erro' => $erro->getMessage()]);

            return null;
        }
    }

    /**
     * Reserva para quando o IBGE não responde. A divisão estadual do Brasil
     * não muda desde 1988 — manter em código é seguro e evita que o cadastro
     * pare por causa de um serviço de terceiro.
     *
     * @var array<string, string>
     */
    private const ESTADOS_DE_RESERVA = [
        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
        'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
        'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
        'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
        'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',
    ];
}
