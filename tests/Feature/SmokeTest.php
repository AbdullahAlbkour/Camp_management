<?php

namespace Tests\Feature;

use App\Models\Refugee;
use App\Models\Shelter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_factories_build_a_consistent_graph(): void
    {
        $shelter = Shelter::factory()->capacity(2)->create();
        $refugee = Refugee::factory()->inShelter($shelter->id, $shelter->camp_id)->create();

        $this->assertSame('assigned', $refugee->housing_status);
        $this->assertSame(1, $shelter->occupiedCount());
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk();
    }
}
