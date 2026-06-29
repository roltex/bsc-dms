<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->nullable()->after('commercial_terms');
        });

        Schema::table('workflow_transitions', function (Blueprint $table) {
            $table->unsignedSmallInteger('priority')->default(0)->after('condition');
        });

        // Change condition from string to json
        if (Schema::hasColumn('workflow_transitions', 'condition')) {
            Schema::table('workflow_transitions', function (Blueprint $table) {
                $table->json('condition')->nullable()->change();
            });
        }

        Schema::create('task_step_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->string('status', 20)->default('active'); // active, completed, skipped
            $table->string('outcome', 30)->nullable(); // approved, rejected, needs_revision
            $table->string('actor_type', 10)->nullable(); // user, partner
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'status']);
            $table->index(['task_id', 'workflow_step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_step_completions');

        Schema::table('workflow_transitions', function (Blueprint $table) {
            $table->dropColumn('priority');
        });

        Schema::table('workflow_transitions', function (Blueprint $table) {
            $table->string('condition')->nullable()->change();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};
