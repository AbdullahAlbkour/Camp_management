<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Case-insensitive "contains" matching for columns that hold Latin text.
 *
 * Arabic text is not folded here: doing that in SQL means one REPLACE() per
 * character fold, nested, which overflows SQLite's parser stack and re-runs for
 * every row scanned. Arabic-bearing columns are folded once on write instead —
 * see `refugees.search_text`. What is left for this class is codes, document
 * numbers and notes, where lowercasing is the whole job.
 */
final class SearchExpression
{
    /**
     * SQL that lowercases a column for comparison against a lowercased term.
     */
    public static function lower(string $column): string
    {
        return 'LOWER('.self::quote($column).')';
    }

    private static function quote(string $column): string
    {
        return DB::connection()->getQueryGrammar()->wrap($column);
    }
}
