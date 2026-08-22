<?php

namespace Tests\Unit;

use App\Support\Code128;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class Code128Test extends TestCase
{
    public function test_the_symbol_starts_with_start_b_and_ends_with_stop(): void
    {
        $values = Code128::values('REF-000123');

        $this->assertSame(104, $values[0], 'Code 128 subset B starts with value 104.');
        $this->assertSame(106, end($values), 'The symbol must end with the stop value.');
    }

    public function test_the_checksum_is_the_weighted_modulo_103_sum(): void
    {
        $values = Code128::values('AB');

        // Payload values: 'A' => 33, 'B' => 34, weighted by position 1 and 2.
        $expected = (104 + (33 * 1) + (34 * 2)) % 103;

        $this->assertSame($expected, $values[count($values) - 2]);
    }

    public function test_every_symbol_character_is_eleven_modules_wide_except_the_stop(): void
    {
        $modules = Code128::modules('CAMP-42');
        $stop = array_slice($modules, -7);

        $this->assertSame([2, 3, 3, 1, 1, 1, 2], $stop);

        $body = array_slice($modules, 0, -7);
        $this->assertSame(0, count($body) % 6);

        foreach (array_chunk($body, 6) as $character) {
            $this->assertSame(11, array_sum($character));
            // Code 128 patterns always contain an even number of bar modules.
            $this->assertSame(0, ($character[0] + $character[2] + $character[4]) % 2);
        }
    }

    public function test_the_svg_is_well_formed_and_labelled(): void
    {
        $svg = Code128::svg('REF-000123');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringEndsWith('</svg>', $svg);
        $this->assertStringContainsString('aria-label="REF-000123"', $svg);
        $this->assertNotFalse(simplexml_load_string($svg), 'The SVG must parse as XML.');
    }

    public function test_characters_outside_subset_b_are_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Code128::svg('سميرة');
    }

    public function test_an_empty_payload_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Code128::values('');
    }
}
