<?php

namespace App\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Base for the list-screen filters.
 *
 * Each subclass declares its controls once in fields(); the shared filter bar
 * renders from that declaration and apply() consumes the same keys, so a control
 * can never drift out of step with the query behind it — which is how the
 * shelter camp filter ended up implemented in the controller but absent from the
 * screen, and the refugee status filter the other way round.
 */
abstract class Filter
{
    /**
     * Columns the list may be sorted by, mapped to the SQL column.
     * Whitelisted because the sort key arrives from the query string.
     *
     * @return array<string, string>
     */
    abstract public function sortable(): array;

    /**
     * @return array<string, string> default sort: [column => direction]
     */
    abstract public function defaultSort(): array;

    /**
     * UI declaration for the filter bar.
     *
     * @return list<array<string, mixed>>
     */
    abstract public function fields(): array;

    abstract public function apply(Builder $query, Request $request): Builder;

    /**
     * Apply filters, then sorting, then return the paginated result.
     */
    public function paginate(Builder $query, Request $request)
    {
        $query = $this->apply($query, $request);
        $this->sort($query, $request);

        return $query->paginate($this->perPage($request))->withQueryString();
    }

    /**
     * Sort by a whitelisted column, falling back to the default.
     */
    protected function sort(Builder $query, Request $request): void
    {
        $sortable = $this->sortable();
        $key = (string) $request->get('sort', '');
        $direction = $request->get('dir') === 'asc' ? 'asc' : 'desc';

        if ($key !== '' && isset($sortable[$key])) {
            $query->orderBy($sortable[$key], $direction);

            return;
        }

        foreach ($this->defaultSort() as $column => $dir) {
            $query->orderBy($column, $dir);
        }
    }

    /**
     * Page size, bounded so a crafted query string cannot ask for everything.
     */
    public function perPage(Request $request): int
    {
        $allowed = [20, 50, 100];
        $requested = (int) $request->get('per_page', 20);

        return in_array($requested, $allowed, true) ? $requested : 20;
    }

    /**
     * The filter keys this screen understands, used to build the reset link and
     * the "applied filters" chips.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_merge(
            array_map(fn (array $field) => $field['name'], $this->fields()),
            ['sort', 'dir', 'per_page']
        );
    }

    /**
     * Filters actually in effect, as [key => ['label' => .., 'value' => ..]].
     *
     * @return array<string, array{label: string, value: string}>
     */
    public function active(Request $request): array
    {
        $active = [];

        foreach ($this->fields() as $field) {
            $value = $request->get($field['name']);

            if ($value === null || $value === '') {
                continue;
            }

            $active[$field['name']] = [
                'label' => $field['label'],
                'value' => $this->describe($field, $value),
            ];
        }

        return $active;
    }

    /**
     * Human-readable form of an applied value (option label rather than its key).
     *
     * @param  array<string, mixed>  $field
     */
    protected function describe(array $field, mixed $value): string
    {
        if (($field['type'] ?? 'text') === 'select') {
            $options = $field['options'] ?? [];

            foreach ($options as $key => $label) {
                if ((string) $key === (string) $value) {
                    return (string) $label;
                }
            }
        }

        return (string) $value;
    }
}
