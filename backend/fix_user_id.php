<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

Illuminate\Support\Facades\Schema::table('task_activities', function (Illuminate\Database\Schema\Blueprint $table) {
    $table->unsignedBigInteger('user_id')->nullable()->change();
});

echo "Column user_id is now nullable\n";
