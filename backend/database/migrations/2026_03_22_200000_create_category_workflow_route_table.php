<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_workflow_route', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_category_id')->constrained('document_categories')->cascadeOnDelete();
            $table->foreignId('workflow_route_id')->constrained('workflow_routes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['document_category_id', 'workflow_route_id'], 'cat_route_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_workflow_route');
    }
};
