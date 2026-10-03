<?php

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
    protected static ?string $password = null;

    /**
     * Le rang du prochain matricule fabrique.
     *
     * Un tirage au hasard entre 1000 et 9999 finissait par tomber sur un
     * matricule ecrit en dur dans un essai, et la suite echouait sans
     * rapport avec ce qu'elle verifiait. Une suite sur six chiffres ne peut
     * croiser ni les quatre chiffres des essais, ni le format du groupe.
     */
    protected static int $rang = 0;

    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'matricule' => 'LM-'.str_pad((string) ++static::$rang, 6, '0', STR_PAD_LEFT),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'employee',
            'status' => 'active',
            'locale' => 'fr',
            'remember_token' => Str::random(10),
        ];
    }

    /** Administrateur du portail : tout, sauf l'administration elle-meme. */
    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    /** Super administrateur : le seul a ouvrir /admin. */
    public function superadmin(): static
    {
        return $this->state(fn () => ['role' => 'superadmin']);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }
}
