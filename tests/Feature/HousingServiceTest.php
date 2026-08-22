<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Refugee;
use App\Models\ResidencyTransfer;
use App\Models\Shelter;
use App\Services\HousingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HousingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): HousingService
    {
        return app(HousingService::class);
    }

    public function test_assigning_an_unhoused_refugee_records_an_assignment(): void
    {
        $shelter = Shelter::factory()->capacity(2)->create();
        $refugee = Refugee::factory()->create(['current_camp_id' => $shelter->camp_id]);

        $this->service()->transferRefugee($refugee, $shelter->camp_id, $shelter->id);

        $transfer = ResidencyTransfer::where('refugee_id', $refugee->id)->latest('id')->first();

        $this->assertSame('assignment', $transfer->transfer_type);
        $this->assertSame('assigned', $refugee->refresh()->housing_status);
    }

    public function test_moving_between_shelters_in_one_camp_is_a_shelter_transfer(): void
    {
        $first = Shelter::factory()->capacity(2)->create();
        $second = Shelter::factory()->capacity(2)->create(['camp_id' => $first->camp_id]);
        $refugee = Refugee::factory()->inShelter($first->id, $first->camp_id)->create();

        $this->service()->transferRefugee($refugee, $first->camp_id, $second->id);

        $this->assertSame(
            'shelter_transfer',
            ResidencyTransfer::where('refugee_id', $refugee->id)->latest('id')->first()->transfer_type
        );
    }

    public function test_moving_to_another_camp_is_a_camp_transfer(): void
    {
        $origin = Shelter::factory()->capacity(2)->create();
        $destination = Shelter::factory()->capacity(2)->create();
        $refugee = Refugee::factory()->inShelter($origin->id, $origin->camp_id)->create();

        $this->service()->transferRefugee($refugee, $destination->camp_id, $destination->id);

        $transfer = ResidencyTransfer::where('refugee_id', $refugee->id)->latest('id')->first();

        $this->assertSame('camp_transfer', $transfer->transfer_type);
        $this->assertSame($origin->camp_id, $transfer->from_camp_id);
        $this->assertSame($destination->camp_id, $transfer->to_camp_id);
    }

    public function test_clearing_the_shelter_is_an_unassignment(): void
    {
        $shelter = Shelter::factory()->capacity(2)->create();
        $refugee = Refugee::factory()->inShelter($shelter->id, $shelter->camp_id)->create();

        $this->service()->transferRefugee($refugee, $shelter->camp_id, null);

        $refugee->refresh();

        $this->assertSame('unassigned', $refugee->housing_status);
        $this->assertNull($refugee->current_shelter_id);
        $this->assertSame(
            'unassignment',
            ResidencyTransfer::where('refugee_id', $refugee->id)->latest('id')->first()->transfer_type
        );
    }

    public function test_a_transfer_that_changes_nothing_writes_no_history(): void
    {
        $shelter = Shelter::factory()->capacity(2)->create();
        $refugee = Refugee::factory()->inShelter($shelter->id, $shelter->camp_id)->create();

        $this->service()->transferRefugee($refugee, $shelter->camp_id, $shelter->id);

        $this->assertSame(0, ResidencyTransfer::where('refugee_id', $refugee->id)->count());
    }

    public function test_a_full_shelter_refuses_another_occupant(): void
    {
        $shelter = Shelter::factory()->capacity(1)->create();
        Refugee::factory()->inShelter($shelter->id, $shelter->camp_id)->create();
        $refugee = Refugee::factory()->create(['current_camp_id' => $shelter->camp_id]);

        $this->expectException(ValidationException::class);

        $this->service()->transferRefugee($refugee, $shelter->camp_id, $shelter->id);
    }

    public function test_an_inactive_occupant_does_not_count_towards_capacity(): void
    {
        $shelter = Shelter::factory()->capacity(1)->create();
        Refugee::factory()->inShelter($shelter->id, $shelter->camp_id)->create(['status' => 'archived']);
        $refugee = Refugee::factory()->create(['current_camp_id' => $shelter->camp_id]);

        $this->service()->transferRefugee($refugee, $shelter->camp_id, $shelter->id);

        $this->assertSame($shelter->id, $refugee->refresh()->current_shelter_id);
    }

    public function test_a_shelter_under_maintenance_refuses_an_assignment(): void
    {
        $shelter = Shelter::factory()->capacity(4)->create(['status' => 'maintenance']);
        $refugee = Refugee::factory()->create(['current_camp_id' => $shelter->camp_id]);

        $this->expectException(ValidationException::class);

        $this->service()->transferRefugee($refugee, $shelter->camp_id, $shelter->id);
    }

    public function test_a_shelter_belonging_to_another_camp_is_refused(): void
    {
        $shelter = Shelter::factory()->capacity(4)->create();
        $refugee = Refugee::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service()->transferRefugee($refugee, $refugee->current_camp_id, $shelter->id);
    }

    public function test_a_whole_household_moves_together(): void
    {
        $shelter = Shelter::factory()->capacity(5)->create();
        $household = Household::factory()->create();
        $members = Refugee::factory()->count(3)->create(['household_id' => $household->id]);

        $this->service()->transferHousehold($household, $shelter->camp_id, $shelter->id);

        foreach ($members as $member) {
            $this->assertSame($shelter->id, $member->refresh()->current_shelter_id);
        }
    }

    public function test_a_household_larger_than_the_shelter_is_refused_atomically(): void
    {
        $shelter = Shelter::factory()->capacity(2)->create();
        $household = Household::factory()->create();
        $members = Refugee::factory()->count(3)->create(['household_id' => $household->id]);

        try {
            $this->service()->transferHousehold($household, $shelter->camp_id, $shelter->id);
            $this->fail('Expected the oversized household transfer to be refused.');
        } catch (ValidationException) {
            // The whole move runs in one transaction, so no member may be left behind
            // in the destination shelter after the rollback.
            foreach ($members as $member) {
                $this->assertNull($member->refresh()->current_shelter_id);
            }
        }
    }
}
