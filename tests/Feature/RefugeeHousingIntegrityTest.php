<?php

namespace Tests\Feature;

use App\Models\Refugee;
use App\Models\ResidencyTransfer;
use App\Models\Shelter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefugeeHousingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_refugee_into_a_shelter_records_a_residency_transfer(): void
    {
        $this->actingAsRole('registration_officer');

        $shelter = Shelter::factory()->capacity(2)->create();
        $refugee = Refugee::factory()->create(['current_camp_id' => $shelter->camp_id]);

        $this->put(route('refugees.update', $refugee), $this->payload($refugee, [
            'current_camp_id' => $shelter->camp_id,
            'current_shelter_id' => $shelter->id,
        ]))->assertRedirect();

        $refugee->refresh();

        $this->assertSame($shelter->id, $refugee->current_shelter_id);
        $this->assertSame('assigned', $refugee->housing_status);
        $this->assertTrue(
            ResidencyTransfer::where('refugee_id', $refugee->id)
                ->where('to_shelter_id', $shelter->id)
                ->exists(),
            'A residency transfer should be written whenever housing changes.'
        );
    }

    public function test_editing_a_refugee_into_a_full_shelter_is_rejected(): void
    {
        $this->actingAsRole('registration_officer');

        $shelter = Shelter::factory()->capacity(1)->create();
        Refugee::factory()->inShelter($shelter->id, $shelter->camp_id)->create();

        $refugee = Refugee::factory()->create(['current_camp_id' => $shelter->camp_id]);

        $this->from(route('refugees.edit', $refugee))
            ->put(route('refugees.update', $refugee), $this->payload($refugee, [
                'current_camp_id' => $shelter->camp_id,
                'current_shelter_id' => $shelter->id,
            ]))
            ->assertSessionHasErrors('current_shelter_id');

        $this->assertNull($refugee->refresh()->current_shelter_id);
    }

    public function test_shelter_from_another_camp_is_rejected(): void
    {
        $this->actingAsRole('registration_officer');

        $shelter = Shelter::factory()->capacity(4)->create();
        $refugee = Refugee::factory()->create();

        $this->from(route('refugees.edit', $refugee))
            ->put(route('refugees.update', $refugee), $this->payload($refugee, [
                'current_camp_id' => $refugee->current_camp_id,
                'current_shelter_id' => $shelter->id,
            ]))
            ->assertSessionHasErrors('current_shelter_id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Refugee $refugee, array $overrides = []): array
    {
        return array_merge([
            'first_name' => $refugee->first_name,
            'last_name' => $refugee->last_name,
            'gender' => $refugee->gender,
            'current_camp_id' => $refugee->current_camp_id,
        ], $overrides);
    }
}
