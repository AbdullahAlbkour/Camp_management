<?php

namespace App\Reports;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * A named report: its heading, the roles allowed to open it, its columns and its query.
 */
final class ReportDefinition
{
    /**
     * @param  array<string, ReportColumn>  $columns
     * @param  list<string>  $roles  Roles allowed to run the report; empty means everyone signed in.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $columns,
        public readonly Builder $query,
        public readonly array $roles = [],
    ) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_values(array_map(fn (ReportColumn $column) => $column->label, $this->columns));
    }

    /**
     * @return list<string>
     */
    public function rowText(mixed $row): array
    {
        return array_values(array_map(fn (ReportColumn $column) => $column->text($row), $this->columns));
    }

    /**
     * Raw values, so numbers reach Excel as numbers rather than text.
     *
     * @return list<string|int|float|null>
     */
    public function rowValues(mixed $row): array
    {
        return array_values(array_map(fn (ReportColumn $column) => $column->value($row), $this->columns));
    }

    public function filename(): string
    {
        return 'report_'.$this->key.'_'.now()->format('Ymd_His');
    }
}
