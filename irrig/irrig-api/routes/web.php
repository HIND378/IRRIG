<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'application' => 'IRRIG Connect API',
    'documentation' => 'Voir README.md et la collection Postman (docs/IRRIG.postman_collection.json)',
]));
