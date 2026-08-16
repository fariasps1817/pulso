<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Estado padrao do usuario gerado.
     *
     * @return array<model-property<User>, mixed>
     */
    public function definition(): array
    {
        return [
            // Nulo faria dele super administrador. Quem quiser um usuário de
            // academia passa academia_id explicitamente ou usa ->daAcademia().
            'academia_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'sessao_unica' => true,
            'ativo' => true,
        ];
    }

    /** Usuário pertencente a uma academia. */
    public function daAcademia(int $academiaId): static
    {
        return $this->state(fn (array $atributos): array => [
            'academia_id' => $academiaId,
        ]);
    }

    /** Equipe do Pulso: sem academia, e sem enxergar dado de academia alguma. */
    public function superAdministrador(): static
    {
        return $this->state(fn (array $atributos): array => [
            'academia_id' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
