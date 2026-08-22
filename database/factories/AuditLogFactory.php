<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'action' => 'create',
            'module' => 'refugees',
            'auditable_type' => null,
            'auditable_id' => null,
            'description' => $this->faker->sentence(),
            'sensitivity' => 'medium',
            'metadata' => [],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'created_at' => now(),
        ];
    }
}
