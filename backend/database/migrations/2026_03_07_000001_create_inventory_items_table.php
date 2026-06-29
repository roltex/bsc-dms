<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category');
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 10)->default('EUR');
            $table->string('serial_number')->nullable()->unique();
            $table->string('model_number')->nullable();
            $table->string('image_path')->nullable();
            $table->string('status')->default('available');
            $table->timestamps();

            $table->index('category');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
