<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiator_id')->constrained('users')->cascadeOnDelete();
            $table->string('route_type', 100)->default('standard');
            $table->string('status', 50)->default('draft'); // draft, pending_manager, pending_lawyer, pending_initiator, approved, archived
            $table->unsignedTinyInteger('current_step')->default(0);
            $table->timestamp('deadline')->nullable();
            $table->text('commercial_terms')->nullable();
            $table->date('validity_from')->nullable();
            $table->date('validity_to')->nullable();
            $table->string('registration_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
