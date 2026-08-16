<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'preferencias'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Preferências de interface: tema, barra lateral, itens por
            // página. Documento JSON porque a lista cresce, e cada item novo
            // viraria uma migration se fosse coluna.
            'preferencias' => 'array',
        ];
    }

    /** Lê uma preferência, com valor padrão quando ainda não foi definida. */
    public function preferencia(string $chave, mixed $padrao = null): mixed
    {
        return data_get($this->preferencias, $chave, $padrao);
    }
}
