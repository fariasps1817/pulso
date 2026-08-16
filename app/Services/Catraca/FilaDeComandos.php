<?php

declare(strict_types=1);

namespace App\Services\Catraca;

use App\Enums\SituacaoComando;
use App\Models\Aluno;
use App\Models\ComandoDispositivo;
use App\Models\DispositivoAcesso;
use App\Support\Nomes;
use Carbon\CarbonImmutable;

/**
 * O que o Pulso quer que o aparelho faça.
 *
 * Nunca chamamos o aparelho: ele pergunta, a cada poucos segundos, se há algo
 * para ele. Toda ação — cadastrar aluno, pedir a digital, bloquear quem está
 * devendo — é uma linha na fila esperando essa pergunta.
 *
 * REGRA DE FORMATO QUE NÃO SE NEGOCIA: o verbo é separado do primeiro campo
 * por UM espaço, e os campos entre si por TAB. Como nomes têm espaço
 * ("Ana Maria da Silva"), trocar um TAB por espaço faz o aparelho ler o
 * sobrenome como se fosse outro campo.
 */
final class FilaDeComandos
{
    public function __construct(private readonly DispositivoAcesso $dispositivo) {}

    /**
     * O próximo comando, já marcado como entregue.
     *
     * Um por vez, de propósito. O protocolo aceita vários por resposta, mas
     * com um só cada confirmação casa com um pedido sem ambiguidade — e a
     * fila anda a cada poucos segundos de qualquer forma.
     */
    public function proximo(): ?ComandoDispositivo
    {
        $this->devolverAbandonados();

        $comando = ComandoDispositivo::query()
            ->where('dispositivo_id', $this->dispositivo->id)
            ->where('situacao', SituacaoComando::Pendente)
            ->orderBy('id')
            ->first();

        $comando?->marcarEntregue();

        return $comando;
    }

    /**
     * Comandos entregues que nunca foram confirmados voltam para a fila.
     *
     * É o que impede a perda silenciosa: o comando saiu na resposta, a rede
     * caiu antes de o aparelho aplicá-lo, e ninguém saberia. Reenviar é
     * seguro porque os comandos do protocolo são declarativos — "o usuário 7
     * passa a ser assim" —, e não incrementais.
     */
    public function devolverAbandonados(): int
    {
        $limite = CarbonImmutable::now()
            ->subMinutes((int) config('pulso.catraca.minutos_para_reenviar_comando'));

        return ComandoDispositivo::query()
            ->where('dispositivo_id', $this->dispositivo->id)
            ->semRespostaDesde($limite)
            ->update(['situacao' => SituacaoComando::Pendente]);
    }

    // -----------------------------------------------------------------
    // Comandos
    // -----------------------------------------------------------------

    /** Cria ou atualiza o aluno no aparelho. */
    public function cadastrarAluno(Aluno $aluno): ComandoDispositivo
    {
        return $this->enfileirar('DATA UPDATE USERINFO', [
            'PIN' => (string) $aluno->id,
            // O aparelho tem tela pequena: o nome inteiro não cabe e o que
            // aparece na hora da passagem é o começo dele.
            'Name' => Nomes::curto($aluno->nome),
            'Pri' => '0',
            'Passwd' => '',
            'Card' => '',
            'Grp' => '1',
            'TZ' => '',
        ], $aluno);
    }

    /**
     * Tira o aluno do grupo que tem permissão de passar.
     *
     * É ASSIM que se bloqueia quem está devendo — e NÃO apagando o usuário.
     * `DATA DELETE USERINFO` leva junto as biometrias, e a especificação do
     * fabricante avisa que o template facial nem sempre volta para o
     * servidor. Apagar por engano custaria trazer o aluno ao balcão para
     * cadastrar o rosto de novo.
     *
     * O grupo 2 precisa estar configurado no aparelho sem faixa de horário
     * liberada. Enquanto isso não for verificado com o equipamento em mãos, o
     * bloqueio automático fica desligado — ver docs/dominio §5.4.
     */
    public function bloquearAluno(Aluno $aluno): ComandoDispositivo
    {
        return $this->enfileirar('DATA UPDATE USERINFO', [
            'PIN' => (string) $aluno->id,
            'Name' => Nomes::curto($aluno->nome),
            'Pri' => '0',
            'Passwd' => '',
            'Card' => '',
            'Grp' => '2',
            'TZ' => '',
        ], $aluno);
    }

    /** O aparelho pede o dedo na tela e devolve o template. */
    public function cadastrarDigital(Aluno $aluno, int $dedo = 0): ComandoDispositivo
    {
        return $this->enfileirar('ENROLL_FP', [
            'PIN' => (string) $aluno->id,
            'FID' => (string) $dedo,
            'RETRY' => '3',
            'OVERWRITE' => '1',
        ], $aluno);
    }

    /**
     * Replica num aparelho um template já guardado.
     *
     * É o que faz "cadastra uma vez, vale em todas as unidades" — e o que
     * torna aceitável um dia precisar recriar o usuário no equipamento.
     */
    public function replicarDigital(Aluno $aluno, string $template, int $dedo = 0): ComandoDispositivo
    {
        return $this->enfileirar('DATA UPDATE BIODATA', [
            'Pin' => (string) $aluno->id,
            'No' => (string) $dedo,
            'Index' => '0',
            'Valid' => '1',
            'Duress' => '0',
            'Type' => '1',
            'MajorVer' => '13',
            'MinorVer' => '0',
            'Format' => '0',
            'Tmp' => $template,
        ], $aluno);
    }

    public function removerAluno(Aluno $aluno): ComandoDispositivo
    {
        return $this->enfileirar('DATA DELETE USERINFO', [
            'PIN' => (string) $aluno->id,
        ], $aluno);
    }

    /** Pede ao aparelho que reenvie tudo, conforme os marcadores. */
    public function pedirSincronizacao(): ComandoDispositivo
    {
        return $this->enfileirar('CHECK', []);
    }

    /**
     * @param  array<string, string>  $campos
     */
    public function enfileirar(string $verbo, array $campos, ?Aluno $aluno = null): ComandoDispositivo
    {
        $corpo = $verbo;

        if ($campos !== []) {
            $partes = [];

            foreach ($campos as $chave => $valor) {
                $partes[] = "{$chave}={$valor}";
            }

            // UM espaço depois do verbo; TAB entre os campos.
            $corpo .= ' '.implode("\t", $partes);
        }

        return ComandoDispositivo::create([
            'unidade_id' => $this->dispositivo->unidade_id,
            'dispositivo_id' => $this->dispositivo->id,
            'aluno_id' => $aluno?->id,
            'verbo' => $verbo,
            'corpo' => $corpo,
        ]);
    }
}
