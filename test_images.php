<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$products = \App\Models\Product::where('active', true)
    ->with('inventoryMovements.images')
    ->get();

foreach ($products as $p) {
    $img = $p->inventoryMovements->flatMap->images->first();
    echo "Product {$p->id} ({$p->name}): " . ($img ? $img->path : 'NO IMAGE') . PHP_EOL;
}
