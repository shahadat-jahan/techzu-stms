<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->timestamp('escalated_approaching_at')->nullable();
            $table->timestamp('escalated_breached_at')->nullable();
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
