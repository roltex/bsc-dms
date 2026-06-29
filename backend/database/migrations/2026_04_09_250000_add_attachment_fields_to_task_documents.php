<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_documents', function (Blueprint $table) {
            $table->boolean('is_attachment')->default(false)->after('version');
            $table->string('original_name')->nullable()->after('is_attachment');
        });
    }

    public function down(): void
    {
        Schema::table('task_documents', function (Blueprint $table) {
            $table->dropColumn(['is_attachment', 'original_name']);
        });
    }
};
