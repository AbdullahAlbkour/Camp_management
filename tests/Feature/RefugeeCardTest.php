<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Refugee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefugeeCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_card_shows_the_badge_code_and_a_barcode(): void
    {
        $this->actingAsRole('registration_officer');
        $refugee = Refugee::factory()->create([
            'first_name' => 'سميرة',
            'father_name' => null,
            'last_name' => 'الأحمد',
        ]);

        $this->get(route('refugees.card', $refugee))
            ->assertOk()
            ->assertSee('سميرة الأحمد')
            ->assertSee($refugee->badge_code)
            ->assertSee('<svg', false);
    }

    public function test_printing_a_card_is_audited(): void
    {
        $this->actingAsRole('registration_officer');
        $refugee = Refugee::factory()->create();

        $this->get(route('refugees.card', $refugee))->assertOk();

        $this->assertTrue(
            AuditLog::where('action', 'print_card')->where('auditable_id', $refugee->id)->exists()
        );
    }

    public function test_the_badge_code_is_stable_and_padded(): void
    {
        $refugee = Refugee::factory()->create();

        $this->assertSame('REF-'.str_pad((string) $refugee->id, 6, '0', STR_PAD_LEFT), $refugee->badge_code);
    }

    public function test_the_card_requires_authentication(): void
    {
        $refugee = Refugee::factory()->create();

        $this->get(route('refugees.card', $refugee))->assertRedirect(route('login'));
    }
}
