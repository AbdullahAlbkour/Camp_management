<?php

use App\Support\RefugeeSearchIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A pre-folded copy of the fields people search a refugee by.
     *
     * Arabic search only works if the same folding is applied to the stored text
     * and to the term ("احمد" must match "أحمد"). Folding the column inside the
     * query was tried first and does not survive: expressing the fold set as
     * nested REPLACE() calls overflows SQLite's parser stack, and would re-run
     * per row on every search besides. Folding once on write costs one column
     * and makes the comparison a plain LIKE.
     */
    public function up(): void
    {
        Schema::table('refugees', function (Blueprint $table): void {
            $table->text('search_text')->nullable()->after('notes');
        });

        RefugeeSearchIndex::rebuild();
    }

    public function down(): void
    {
        Schema::table('refugees', function (Blueprint $table): void {
            $table->dropColumn('search_text');
        });
    }
};
