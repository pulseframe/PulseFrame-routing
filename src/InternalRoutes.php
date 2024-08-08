<?php

use PulseFrame\Facades\Route;
use PulseFrame\Http\Controllers\MaintenanceController;

Route::get('/activate/{uuid}', [MaintenanceController::class, 'index'])
  ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
