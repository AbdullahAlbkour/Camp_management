<?php

namespace App\Assistant;

/**
 * What the assistant sends back for one question.
 *
 * An answer is always a sentence first. Rows, figures and links are optional
 * supporting detail, so a reply degrades to plain readable Arabic rather than
 * to an empty bubble when a query returns nothing.
 */
final class Answer
{
    /**
     * @param  list<array{title: string, subtitle?: string, meta?: string, url?: string}>  $items
     * @param  list<array{label: string, value: string}>  $figures
     * @param  list<array{label: string, url: string, icon?: string}>  $links
     * @param  list<string>  $followUps
     */
    private function __construct(
        public readonly string $intent,
        public readonly string $text,
        public readonly array $items = [],
        public readonly array $figures = [],
        public readonly array $links = [],
        public readonly array $followUps = [],
        public readonly string $tone = 'normal',
    ) {}

    /**
     * @param  list<array{title: string, subtitle?: string, meta?: string, url?: string}>  $items
     * @param  list<array{label: string, value: string}>  $figures
     * @param  list<array{label: string, url: string, icon?: string}>  $links
     * @param  list<string>  $followUps
     */
    public static function make(
        string $intent,
        string $text,
        array $items = [],
        array $figures = [],
        array $links = [],
        array $followUps = [],
    ): self {
        return new self($intent, $text, $items, $figures, $links, $followUps);
    }

    /**
     * A question understood but answered by "nothing matched", which reads
     * differently from a failure and is styled differently in the widget.
     *
     * @param  list<array{title: string, subtitle?: string, meta?: string, url?: string}>  $items
     * @param  list<string>  $followUps
     */
    public static function empty(string $intent, string $text, array $items = [], array $followUps = []): self
    {
        return new self($intent, $text, items: $items, followUps: $followUps, tone: 'empty');
    }

    /**
     * The role is not allowed this area. Said plainly, with no hint about what
     * the withheld numbers are.
     */
    public static function denied(string $intent, string $text): self
    {
        return new self($intent, $text, tone: 'denied');
    }

    /**
     * @param  list<string>  $followUps
     */
    public static function unknown(string $text, array $followUps = []): self
    {
        return new self('fallback', $text, followUps: $followUps, tone: 'unknown');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'tone' => $this->tone,
            'text' => $this->text,
            'items' => $this->items,
            'figures' => $this->figures,
            'links' => $this->links,
            'follow_ups' => $this->followUps,
        ];
    }
}
