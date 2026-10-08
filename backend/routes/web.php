<?php // starting of PHP codes

use Illuminate\Support\Facades\Route; // import Route, it decides who to handle this request
use App\Http\Controllers\BenefitController;
use App\Http\Controllers\VisitController;

Route::get('/benefits', [BenefitController::class, 'index'])
    ->name('benefits.index');

Route::get('/visits/create', [VisitController::class, 'create'])
    ->name('visits.create');

Route::post('/visits', [VisitController::class, 'store'])
    ->name('visits.store');
