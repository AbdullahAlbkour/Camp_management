<?php

namespace Tests\Unit;

use App\Models\Refugee;
use PHPUnit\Framework\TestCase;

class RefugeeNameTest extends TestCase
{
    public function test_the_full_name_has_no_double_space_without_a_father_name(): void
    {
        $refugee = new Refugee(['first_name' => 'سميرة', 'father_name' => null, 'last_name' => 'الأحمد']);

        $this->assertSame('سميرة الأحمد', $refugee->full_name);
    }

    public function test_the_full_name_joins_all_three_parts(): void
    {
        $refugee = new Refugee(['first_name' => 'سميرة', 'father_name' => 'آدم', 'last_name' => 'الأحمد']);

        $this->assertSame('سميرة آدم الأحمد', $refugee->full_name);
    }

    public function test_surrounding_whitespace_is_trimmed_from_each_part(): void
    {
        $refugee = new Refugee(['first_name' => '  سميرة ', 'father_name' => '   ', 'last_name' => ' الأحمد ']);

        $this->assertSame('سميرة الأحمد', $refugee->full_name);
    }
}
