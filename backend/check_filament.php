<?php
require __DIR__.'/vendor/autoload.php';

$classes = [
    'Filament\\Tables\\Filters\\SelectFilter',
    'Filament\\Tables\\Columns\\TextColumn',
    'Filament\\Tables\\Columns\\IconColumn',
    'Filament\\Tables\\Table',
];

foreach ($classes as $c) {
    echo $c . ': ' . (class_exists($c) ? 'EXISTS' : 'NOT FOUND') . "\n";
}
