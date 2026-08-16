<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\ApenasDigitos;
use App\Casts\CaixaDeTitulo;
use App\Models\Concerns\PertenceAAcademia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Professores, instrutores e personais.
 *
 * Separado de User porque nem todo profissional faz login: muita academia
 * cadastra o professor só para vinculá-lo ao aluno.
 */
final class Profissional extends Model
{
    use PertenceAAcademia;
    use SoftDeletes;

    protected $table = 'profissionais';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'nome' => CaixaDeTitulo::class,
            'cpf' => ApenasDigitos::class,
            'telefone' => ApenasDigitos::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
