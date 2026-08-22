<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Refugee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        return [
            'attachable_type' => Refugee::class,
            'attachable_id' => Refugee::factory(),
            'disk' => 'local',
            'path' => 'attachments/'.$this->faker->uuid().'.pdf',
            'original_name' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
            'category' => 'document',
            'description' => null,
            'uploaded_by' => null,
        ];
    }
}
