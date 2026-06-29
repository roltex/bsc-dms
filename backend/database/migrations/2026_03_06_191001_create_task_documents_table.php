<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('approved_at')->nullable();
            $table->string('registration_number')->nullable();
            $table->boolean('is_signed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_documents');
    }
};
