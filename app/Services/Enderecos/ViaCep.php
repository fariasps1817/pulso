<?php

declare(strict_types=1);

namespace App\Services\Enderecos;

use App\Support\Documentos;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Busca de endereço por CEP no ViaCEP.
 *
 * DUAS REGRAS QUE NÃO SE QUEBRAM:
 *
 * 1. **Nunca trava o cadastro.** CEP não encontrado, API fora do ar ou lenta —
 *    o resultado é nulo e a recepção digita à mão. Aluno em rua nova não pode
 *    ficar sem cadastro porque um serviço público caiu.
 * 2. **Tempo limite curto.** A recepcionista está com o aluno na frente; três
 *    segundos parados na tela é tempo demais.
 *
 * O resultado é guardado em cache: CEP praticamente não muda, e a mesma rua
 * se repete o dia inteiro numa academia de bairro.
 */
final class ViaCep
{
    private const SEGUNDOS_LIMITE = 3;

    private const DIAS_EM_CACHE = 30;

    /**
     * @return array{cep: string, logradouro: string, bairro: string, cidade: string, uf: string}|null
     */
    public function buscar(string $cep): ?array
    {
        $digitos = Documentos::apenasDigitos($cep);

        if (strlen($digitos) !== 8) {
            return null;
        }

        return Cache::remember(
            "viacep:{$digitos}",
            now()->addDays(self::DIAS_EM_CACHE),
            fn (): ?array => $this->consultar($digitos),
        );
    }

    /** @return array{cep: string, logradouro: string, bairro: string, cidade: string, uf: string}|null */
    private function consultar(string $cep): ?array
    {
        try {
            $resposta = Http::timeout(self::SEGUNDOS_LIMITE)
                ->get("https://viacep.com.br/ws/{$cep}/json/");

            if ($resposta->failed()) {
                return null;
            }

            $dados = $resposta->json();

            // O ViaCEP responde 200 com {"erro": true} para CEP inexistente.
            if (! is_array($dados) || ($dados['erro'] ?? false)) {
                return null;
            }

            return [
                'cep' => $cep,
                'logradouro' => (string) ($dados['logradouro'] ?? ''),
                'bairro' => (string) ($dados['bairro'] ?? ''),
                'cidade' => (string) ($dados['localidade'] ?? ''),
                'uf' => (string) ($dados['uf'] ?? ''),
            ];
        } catch (Throwable $erro) {
            // Registrado para diagnóstico, mas o cadastro segue: o endereço
            // é conveniência, não requisito.
            Log::warning('ViaCEP indisponível.', ['cep' => $cep, 'erro' => $erro->getMessage()]);

            return null;
        }
    }
}
