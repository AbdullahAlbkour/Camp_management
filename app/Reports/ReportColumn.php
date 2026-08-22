<?php

namespace App\Reports;

use Closure;

/**
 * One column of a report: a heading plus how to read it off a model.
 */
final class ReportColumn
{
    /**
     * @param  Closure(mixed): (string|int|float|null)  $resolver
     */
    private function __construct(
        public readonly string $label,
        private readonly Closure $resolver,
    ) {}

    /**
     * @param  Closure(mixed): (string|int|float|null)  $resolver
     */
    public static function make(string $label, Closure $resolver): self
    {
        return new self($label, $resolver);
    }

    public function value(mixed $row): string|int|float|null
    {
        return ($this->resolver)($row);
    }

    /**
     * The printable form, used by CSV and by the HTML tables.
     */
    public function text(mixed $row): string
    {
        $value = $this->value($row);

        return $value === null || $value === '' ? '—' : (string) $value;
    }
}
