<?php

namespace Tests\Unit;

use App\Support\ArabicText;
use PHPUnit\Framework\TestCase;

class ArabicTextTest extends TestCase
{
    public function test_alef_variants_fold_to_bare_alef(): void
    {
        foreach (['أحمد', 'إحمد', 'آحمد', 'ٱحمد'] as $spelling) {
            $this->assertSame('احمد', ArabicText::normalize($spelling));
        }
    }

    public function test_ta_marbuta_and_alef_maksura_fold(): void
    {
        $this->assertSame('فاطمه', ArabicText::normalize('فاطمة'));
        $this->assertSame('يحيي', ArabicText::normalize('يحيى'));
    }

    public function test_diacritics_and_tatweel_are_stripped(): void
    {
        $this->assertSame('محمد', ArabicText::normalize('مُحَمَّد'));
        $this->assertSame('علي', ArabicText::normalize('عــلي'));
    }

    public function test_arabic_indic_digits_become_ascii(): void
    {
        $this->assertSame('1234567890', ArabicText::normalize('١٢٣٤٥٦٧٨٩٠'));
        $this->assertSame('1234567890', ArabicText::normalize('۱۲۳۴۵۶۷۸۹۰'));
    }

    public function test_whitespace_is_collapsed_and_latin_lowercased(): void
    {
        $this->assertSame('id-55 ab', ArabicText::normalize('  ID-55    AB '));
    }

    public function test_an_empty_term_normalises_to_an_empty_string(): void
    {
        $this->assertSame('', ArabicText::normalize(null));
        $this->assertSame('', ArabicText::normalize('   '));
    }

    public function test_searchable_joins_parts_and_drops_empties(): void
    {
        $blob = ArabicText::searchable(['سميرة', null, 'الأحمد', '', 'ID-9']);

        $this->assertSame('سميره الاحمد id-9', $blob);
    }

    public function test_a_folded_term_is_a_substring_of_the_folded_blob(): void
    {
        // This is the invariant the whole search rests on: whatever the clerk
        // types, folded, must appear inside the stored blob, folded.
        $blob = ArabicText::searchable(['أحمد', 'إبراهيم', 'الحسنى']);

        foreach (['احمد', 'ابراهيم', 'الحسني', 'احمد ابراهيم'] as $term) {
            $this->assertStringContainsString(ArabicText::normalize($term), $blob);
        }
    }
}
