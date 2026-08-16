<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\ApenasDigitos;
use App\Casts\CaixaDeTitulo;
use App\Enums\SituacaoMatricula;
use App\Models\Concerns\PertenceAAcademia;
use App\Support\Documentos;
use Carbon\CarbonImmutable;
use Database\Factories\AlunoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Quem treina. Cadastrado uma vez por academia, mesmo numa rede.
 *
 * Exclusão é lógica: o aluno some das listas, mas as mensalidades que ele
 * pagou continuam existindo. Já o template biométrico é apagado de verdade —
 * ver CredencialAcesso::apagarTemplate().
 */
final class Aluno extends Model
{
    /** @use HasFactory<AlunoFactory> */
    use HasFactory;

    use PertenceAAcademia;
    use SoftDeletes;

    protected $table = 'alunos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'immutable_date',

            // Normalização ao gravar. Ver App\Casts.
            'nome' => CaixaDeTitulo::class,
            'responsavel_nome' => CaixaDeTitulo::class,
            'cpf' => ApenasDigitos::class,
            'responsavel_cpf' => ApenasDigitos::class,
            'whatsapp' => ApenasDigitos::class,
            'telefone' => ApenasDigitos::class,
            'responsavel_telefone' => ApenasDigitos::class,
            'cep' => ApenasDigitos::class,
        ];
    }

    // ---------------------------------------------------------------------
    // Relacionamentos
    // ---------------------------------------------------------------------

    /** @return HasMany<Matricula, $this> */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /**
     * A matrícula em vigor — ativa ou em experiência.
     *
     * Existe para a lista de alunos mostrar a situação sem uma consulta por
     * linha: com `with('matriculaVigente')`, 300 alunos custam duas consultas
     * em vez de trezentas e uma.
     *
     * @return HasOne<Matricula, $this>
     */
    public function matriculaVigente(): HasOne
    {
        return $this->hasOne(Matricula::class)
            ->whereIn('situacao', [
                SituacaoMatricula::Ativa->value,
                SituacaoMatricula::Experiencia->value,
            ])
            ->latestOfMany('inicio_em');
    }

    /** @return HasMany<Mensalidade, $this> */
    public function mensalidades(): HasMany
    {
        return $this->hasMany(Mensalidade::class);
    }

    /** @return HasMany<CredencialAcesso, $this> */
    public function credenciais(): HasMany
    {
        return $this->hasMany(CredencialAcesso::class);
    }

    /** @return HasMany<Acesso, $this> */
    public function acessos(): HasMany
    {
        return $this->hasMany(Acesso::class);
    }

    /** @return HasMany<ConsentimentoLgpd, $this> */
    public function consentimentos(): HasMany
    {
        return $this->hasMany(ConsentimentoLgpd::class);
    }

    // ---------------------------------------------------------------------
    // Regras
    // ---------------------------------------------------------------------

    public function idade(): int
    {
        return (int) $this->data_nascimento->diffInYears(CarbonImmutable::now());
    }

    /** Abaixo de 18, os dados do responsável passam a ser obrigatórios. */
    public function ehMenorDeIdade(): bool
    {
        return $this->idade() < 18;
    }

    public function cpfFormatado(): string
    {
        return Documentos::formatarCpf($this->cpf);
    }

    /** Aniversariantes do dia — a lista que a recepção abre de manhã. */
    /**
     * @param  Builder<Aluno>  $consulta
     * @return Builder<Aluno>
     */
    public function scopeAniversariantesDe(Builder $consulta, CarbonImmutable $dia): Builder
    {
        return $consulta
            ->whereRaw('EXTRACT(MONTH FROM data_nascimento) = ?', [$dia->month])
            ->whereRaw('EXTRACT(DAY FROM data_nascimento) = ?', [$dia->day]);
    }

    /** Busca tolerante a erro de digitação, via pg_trgm. */
    /**
     * @param  Builder<Aluno>  $consulta
     * @return Builder<Aluno>
     */
    public function scopeBuscandoPorNome(Builder $consulta, string $termo): Builder
    {
        return $consulta
            ->whereRaw('nome % ?', [$termo])
            ->orderByRaw('similarity(nome, ?) DESC', [$termo]);
    }
}
