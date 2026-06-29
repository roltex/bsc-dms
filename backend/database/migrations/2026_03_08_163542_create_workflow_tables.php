<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_routes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('canvas_data')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_route_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role', 30);
            $table->string('action_type', 30)->default('review');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_step_id')->nullable()->constrained('workflow_steps')->cascadeOnDelete();
            $table->foreignId('to_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->string('condition')->nullable();
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('workflow_route_id')->nullable()->after('route_type')->constrained('workflow_routes')->nullOnDelete();
            $table->unsignedBigInteger('current_workflow_step_id')->nullable()->after('workflow_route_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['workflow_route_id']);
            $table->dropColumn(['workflow_route_id', 'current_workflow_step_id']);
        });

        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflow_routes');
    }
};
