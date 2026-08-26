<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the folded `refugees.search_text` blob.
 *
 * The model keeps the blob current for ordinary writes, but Eloquent events do
 * not fire for bulk inserts — which is how a seeded or imported batch ends up
 * invisible to search while every form-created record works fine. Anything that
 * writes refugees without going through the model must run this afterwards.
 */
final class RefugeeSearchIndex
{
    /**
     * Recompute the blob for every refugee, including archived ones.
     *
     * @param  callable(int):void|null  $progress  Called with the row count after each chunk.
     * @return int Number of rows rewritten.
     */
    public static function rebuild(?callable $progress = null, int $chunkSize = 500): int
    {
        $rewritten = 0;

        DB::table('refugees')
            ->select(['id', 'first_name', 'father_name', 'last_name', 'document_number', 'phone'])
            ->orderBy('id')
            ->chunk($chunkSize, function ($rows) use (&$rewritten, $progress): void {
                foreach ($rows as $row) {
                    DB::table('refugees')->where('id', $row->id)->update([
                        'search_text' => self::blobFor($row),
                    ]);
                }

                $rewritten += count($rows);

                if ($progress) {
                    $progress($rewritten);
                }
            });

        return $rewritten;
    }

    /**
     * How many rows are currently missing a blob — a cheap health check.
     */
    public static function missingCount(): int
    {
        return DB::table('refugees')
            ->whereNull('search_text')
            ->orWhere('search_text', '')
            ->count();
    }

    private static function blobFor(object $row): string
    {
        return ArabicText::searchable([
            $row->first_name,
            $row->father_name,
            $row->last_name,
            $row->document_number,
            $row->phone,
        ]);
    }
}
