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
        Schema::table('task_documents', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('is_signed');
            $table->foreignId('signed_by')->nullable()->after('signature_path')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_documents', function (Blueprint $table) {
            $table->dropForeign(['signed_by']);
            $table->dropColumn(['signature_path', 'signed_by']);
        });
    }
};
