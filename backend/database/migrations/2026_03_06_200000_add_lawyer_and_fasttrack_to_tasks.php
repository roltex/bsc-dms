<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('assigned_lawyer_id')->nullable()->after('initiator_id')->constrained('users')->nullOnDelete();
            $table->boolean('fast_tracked')->default(false)->after('registration_number');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['assigned_lawyer_id']);
            $table->dropColumn(['assigned_lawyer_id', 'fast_tracked']);
        });
    }
};
