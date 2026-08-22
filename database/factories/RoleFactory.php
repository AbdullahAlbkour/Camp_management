<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->slug(2),
            'display_name' => $this->faker->words(2, true),
            'description' => null,
        ];
    }

    public function named(string $name, ?string $displayName = null): self
    {
        return $this->state(fn () => [
            'name' => $name,
            'display_name' => $displayName ?? $name,
        ]);
    }
}
