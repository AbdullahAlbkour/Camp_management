<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role_id' => Role::factory(),
            'status' => 'active',
        ];
    }

    /**
     * Attach the user to a role by name, reusing the role when it already exists.
     */
    public function role(string $name): self
    {
        return $this->state(fn () => [
            'role_id' => Role::firstOrCreate(
                ['name' => $name],
                ['display_name' => $name]
            )->id,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
