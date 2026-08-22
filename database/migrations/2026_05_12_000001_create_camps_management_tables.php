<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('camps', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('location')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('shelters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('camp_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('type')->default('tent');
            $table->unsignedInteger('capacity');
            $table->enum('status', ['active', 'maintenance', 'inactive'])->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['camp_id', 'code']);
            $table->index(['camp_id', 'type', 'status']);
        });

        Schema::create('households', function (Blueprint $table): void {
            $table->id();
            $table->string('household_code')->unique();
            $table->unsignedBigInteger('head_of_household_id')->nullable()->index();
            $table->enum('status', ['active', 'archived'])->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('refugees', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name')->index();
            $table->string('father_name')->nullable()->index();
            $table->string('last_name')->index();
            $table->enum('gender', ['male', 'female', 'other']);
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('document_number')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('marital_status')->nullable();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active')->index();
            $table->foreignId('current_camp_id')->constrained('camps')->restrictOnDelete();
            $table->foreignId('current_shelter_id')->nullable()->constrained('shelters')->nullOnDelete();
            $table->enum('housing_status', ['assigned', 'unassigned'])->default('unassigned')->index();
            $table->enum('presence_status', ['inside', 'outside'])->default('inside')->index();
            $table->foreignId('household_id')->nullable()->constrained('households')->nullOnDelete();
            $table->string('relation_to_head')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['current_camp_id', 'current_shelter_id']);
            $table->index(['household_id', 'status']);
        });

        Schema::table('households', function (Blueprint $table): void {
            $table->foreign('head_of_household_id')->references('id')->on('refugees')->nullOnDelete();
        });

        Schema::create('residency_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refugee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_camp_id')->nullable()->constrained('camps')->nullOnDelete();
            $table->foreignId('to_camp_id')->nullable()->constrained('camps')->nullOnDelete();
            $table->foreignId('from_shelter_id')->nullable()->constrained('shelters')->nullOnDelete();
            $table->foreignId('to_shelter_id')->nullable()->constrained('shelters')->nullOnDelete();
            $table->enum('transfer_type', ['initial', 'assignment', 'unassignment', 'shelter_transfer', 'camp_transfer']);
            $table->text('reason')->nullable();
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transferred_at')->useCurrent();
            $table->timestamps();
            $table->index(['refugee_id', 'transferred_at']);
            $table->index(['to_camp_id', 'to_shelter_id']);
        });

        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('aid_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('unit')->default('item');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('aid_distributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aid_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('refugee_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('camp_id')->constrained('camps')->restrictOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->date('distribution_date');
            $table->foreignId('distributed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['distribution_date', 'camp_id']);
            $table->index(['refugee_id', 'aid_type_id', 'distribution_date'], 'aid_dist_refugee_type_date_idx');
            $table->index(['household_id', 'aid_type_id', 'distribution_date'], 'aid_dist_household_type_date_idx');
        });

        Schema::create('medical_services', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
        });

        Schema::create('medical_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refugee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medical_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('camp_id')->constrained('camps')->restrictOnDelete();
            $table->date('record_date');
            $table->text('diagnosis');
            $table->text('notes')->nullable();
            $table->boolean('needs_follow_up')->default(false)->index();
            $table->date('follow_up_date')->nullable()->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['record_date', 'camp_id']);
            $table->index(['refugee_id', 'record_date']);
        });

        Schema::create('checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('camp_id')->constrained('camps')->cascadeOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
            $table->unique(['camp_id', 'name']);
        });

        Schema::create('entry_exit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refugee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('camp_id')->constrained('camps')->restrictOnDelete();
            $table->foreignId('checkpoint_id')->constrained()->restrictOnDelete();
            $table->enum('movement_type', ['entry', 'exit']);
            $table->timestamp('movement_datetime');
            $table->text('reason')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['movement_datetime', 'camp_id']);
            $table->index(['refugee_id', 'movement_datetime']);
        });

        Schema::create('security_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refugee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('camp_id')->constrained('camps')->restrictOnDelete();
            $table->string('incident_type');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('low')->index();
            $table->date('report_date');
            $table->text('description');
            $table->text('action_taken')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['report_date', 'camp_id']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('target_role')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->enum('status', ['unread', 'read', 'resolved'])->default('unread')->index();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('module')->index();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('description');
            $table->enum('sensitivity', ['low', 'medium', 'high', 'critical'])->default('medium')->index();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['auditable_type', 'auditable_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE shelters ADD CONSTRAINT shelters_capacity_positive CHECK (capacity > 0)');
            DB::statement('ALTER TABLE aid_distributions ADD CONSTRAINT aid_distributions_quantity_positive CHECK (quantity > 0)');
            DB::statement('ALTER TABLE aid_distributions ADD CONSTRAINT aid_distributions_one_target CHECK ((refugee_id IS NOT NULL AND household_id IS NULL) OR (refugee_id IS NULL AND household_id IS NOT NULL))');
            DB::statement('ALTER TABLE medical_records ADD CONSTRAINT medical_records_followup_date_required CHECK (needs_follow_up = 0 OR follow_up_date IS NOT NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('security_reports');
        Schema::dropIfExists('entry_exit_logs');
        Schema::dropIfExists('checkpoints');
        Schema::dropIfExists('medical_records');
        Schema::dropIfExists('medical_services');
        Schema::dropIfExists('aid_distributions');
        Schema::dropIfExists('aid_types');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('residency_transfers');
        Schema::table('households', function (Blueprint $table): void {
            $table->dropForeign(['head_of_household_id']);
        });
        Schema::dropIfExists('refugees');
        Schema::dropIfExists('households');
        Schema::dropIfExists('shelters');
        Schema::dropIfExists('camps');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};
