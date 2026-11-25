<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->text('description');
            $table->string('priority')->default('medium');
            $table->string('status')->default('open');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('sla_warning_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('warning_notified_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('last_assigned_at')->nullable();
            $table->string('external_ref')->nullable()->unique();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'assigned_to']);
            $table->index(['due_at', 'status']);
            $table->index('priority');
            $table->index('category_id');
            $table->index('created_at');
            $table->index('created_by');
            $table->index('assigned_to');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS tickets_ticket_number_trgm_idx ON tickets USING GIN (ticket_number gin_trgm_ops)');
            DB::statement('CREATE INDEX IF NOT EXISTS tickets_subject_trgm_idx ON tickets USING GIN (subject gin_trgm_ops)');

            DB::statement("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS search_vector tsvector GENERATED ALWAYS AS (to_tsvector('english', coalesce(description, ''))) STORED");
            DB::statement('CREATE INDEX IF NOT EXISTS tickets_description_fts_idx ON tickets USING GIN (search_vector)');

            DB::statement('CREATE INDEX IF NOT EXISTS tickets_active_status_created_idx ON tickets (status, created_at DESC) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS tickets_assigned_status_created_idx ON tickets (assigned_to, status, created_at DESC) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS tickets_unassigned_status_created_idx ON tickets (status, created_at DESC) WHERE deleted_at IS NULL AND assigned_to IS NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS tickets_status_due_at_idx ON tickets (status, due_at) WHERE deleted_at IS NULL');

            DB::statement("ALTER TABLE tickets ADD COLUMN IF NOT EXISTS priority_rank smallint GENERATED ALWAYS AS (CASE priority WHEN 'low' THEN 1 WHEN 'medium' THEN 2 WHEN 'high' THEN 3 WHEN 'critical' THEN 4 ELSE 0 END) STORED");
            DB::statement('CREATE INDEX IF NOT EXISTS tickets_priority_rank_idx ON tickets (priority_rank)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
