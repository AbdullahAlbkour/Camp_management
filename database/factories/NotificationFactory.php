<?php

namespace Database\Factories;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'target_role' => 'admin',
            'user_id' => null,
            'type' => 'generic',
            'title' => $this->faker->sentence(3),
            'body' => $this->faker->sentence(),
            'status' => 'unread',
            'related_type' => null,
            'related_id' => null,
            'created_by' => null,
        ];
    }
}
