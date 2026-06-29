<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->unsignedSmallInteger('page')->nullable()->change();
            $table->decimal('x_percent', 5, 2)->nullable()->change();
            $table->decimal('y_percent', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->unsignedSmallInteger('page')->nullable(false)->change();
            $table->decimal('x_percent', 5, 2)->nullable(false)->change();
            $table->decimal('y_percent', 5, 2)->nullable(false)->change();
        });
    }
};
