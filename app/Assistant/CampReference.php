<?php

namespace App\Assistant;

use App\Models\Camp;

/**
 * What a question said about a camp — which is three states, not two.
 *
 * Collapsing "no camp mentioned" and "a camp was named but does not exist" into
 * a single null was a real defect: asking about a camp the system has never
 * heard of silently widened the query to every camp, and the answer looked
 * authoritative. The caller now has to handle the unknown case explicitly.
 */
final class CampReference
{
    private function __construct(
        public readonly ?Camp $camp,
        public readonly ?string $unknownName,
    ) {}

    public static function none(): self
    {
        return new self(null, null);
    }

    public static function of(Camp $camp): self
    {
        return new self($camp, null);
    }

    public static function unknown(?string $name): self
    {
        return new self(null, $name ?? '');
    }

    /** A camp was named and it is not in the system. */
    public function isUnknown(): bool
    {
        return $this->unknownName !== null;
    }

    /** A camp was named and resolved to a record. */
    public function isResolved(): bool
    {
        return $this->camp !== null;
    }

    /**
     * The camp's name with "مخيم" in front of it — unless the name already
     * starts with it, as most do, which would read "في مخيم مخيم السلام".
     */
    public function label(): string
    {
        if ($this->camp === null) {
            return '';
        }

        $name = trim($this->camp->name);

        return str_starts_with($name, 'مخيم') ? $name : 'مخيم '.$name;
    }
}
