<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reference data can be archived; the transactional trail (distributions,
     * medical records, movements, incidents, transfers, audit logs) deliberately
     * cannot, because those rows are the record of what actually happened.
     */
    private const ARCHIVABLE = [
        'camps',
        'shelters',
        'refugees',
        'households',
        'organizations',
        'aid_types',
        'medical_services',
        'checkpoints',
    ];

    public function up(): void
    {
        foreach (self::ARCHIVABLE as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->softDeletes()->index();
            });
        }

        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->morphs('attachable');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 191);
            $table->unsignedBigInteger('size');
            $table->string('category')->default('document')->index();
            $table->string('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');

        foreach (self::ARCHIVABLE as $table) {
            // The index has to go first: dropSoftDeletes() only removes the column,
            // and dropping a column an index still references fails.
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropIndex(['deleted_at']);
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
