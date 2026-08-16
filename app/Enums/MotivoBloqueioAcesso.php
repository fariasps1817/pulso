<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Por que a catraca bloqueou.
 *
 * ISTO NUNCA VAI PARA O DISPLAY DA CATRACA. Acesso negado mostra sempre
 * "Procure a recepção" — expor a dívida do aluno com a fila atrás dele é
 * constrangimento vedado pelo Código de Defesa do Consumidor (art. 42).
 *
 * O motivo existe para a recepção entender o que aconteceu quando o aluno
 * chegar ao balcão, e para o histórico. Nada além disso.
 */
enum MotivoBloqueioAcesso: string
{
    case Inadimplente = 'inadimplente';
    case MatriculaEncerrada = 'matricula_encerrada';
    case MatriculaSuspensa = 'matricula_suspensa';
    case NaoReconhecido = 'nao_reconhecido';
    case UnidadeNaoPermitida = 'unidade_nao_permitida';
    case CredencialInativa = 'credencial_inativa';
    case ExperienciaEsgotada = 'experiencia_esgotada';

    /** O que a recepção lê na tela dela. */
    public function rotulo(): string
    {
        return match ($this) {
            self::Inadimplente => 'Mensalidade em aberto além da tolerância',
            self::MatriculaEncerrada => 'Matrícula encerrada',
            self::MatriculaSuspensa => 'Matrícula trancada',
            self::NaoReconhecido => 'Não reconhecido pelo equipamento',
            self::UnidadeNaoPermitida => 'Plano não dá acesso a esta unidade',
            self::CredencialInativa => 'Credencial inativa',
            self::ExperienciaEsgotada => 'Período de experiência esgotado',
        };
    }

    /** O que o aluno vê na catraca. Sempre o mesmo, qualquer que seja o motivo. */
    public function mensagemNaCatraca(): string
    {
        return 'Procure a recepção';
    }
}
