<?php

namespace Tests\Unit;

use App\Assistant\AssistantQuery;
use PHPUnit\Framework\TestCase;

class AssistantQueryTest extends TestCase
{
    public function test_a_question_is_folded_before_it_is_matched(): void
    {
        $query = new AssistantQuery('أَيْنَ يسكن أحمد؟');

        $this->assertSame('اين يسكن احمد؟', $query->text);
    }

    public function test_short_needles_match_whole_words_only(): void
    {
        // "كم" as a substring would fire on "تراكم", which asks nothing.
        $this->assertFalse((new AssistantQuery('تراكم الطلبات'))->hasAny(['كم']));
        $this->assertTrue((new AssistantQuery('كم عدد السكان'))->hasAny(['كم']));
    }

    public function test_longer_needles_match_inside_a_word(): void
    {
        // One needle has to cover المخيم، بمخيم and مخيمات.
        $this->assertTrue((new AssistantQuery('كم شخص في المخيم'))->hasAny(['مخيم']));
        $this->assertTrue((new AssistantQuery('عدد المخيمات'))->hasAny(['مخيم']));
    }

    public function test_a_word_matches_through_the_article_and_glued_prefixes(): void
    {
        $this->assertTrue((new AssistantQuery('ما العدد الكلي'))->hasAny(['عدد']));
        $this->assertTrue((new AssistantQuery('وكم الباقي'))->hasAny(['كم']));
    }

    public function test_arabic_indic_digits_are_read_as_numbers(): void
    {
        $this->assertSame([12345], (new AssistantQuery('من هو ١٢٣٤٥'))->numbers());
    }

    public function test_the_subject_is_what_is_left_after_trigger_words(): void
    {
        $query = new AssistantQuery('ابحث عن أحمد الحسن');

        $this->assertSame('احمد الحسن', $query->subject(['ابحث']));
    }

    public function test_the_subject_drops_digits_so_a_document_number_is_not_read_as_a_name(): void
    {
        $query = new AssistantQuery('ابحث عن الوثيقة 12345');

        $this->assertSame('الوثيقه', $query->subject(['ابحث']));
    }

    public function test_codes_are_the_tokens_carrying_digits(): void
    {
        $this->assertSame(['doc12345'], (new AssistantQuery('ملف DOC12345 من فضلك'))->codes());
    }
}
