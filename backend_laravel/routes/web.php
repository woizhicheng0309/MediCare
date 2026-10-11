<?php // starting of PHP codes

use Illuminate\Support\Facades\Route; // import Route, it decides who to handle this request
use App\Http\Controllers\BenefitController;
use App\Http\Controllers\AuthController;

Route::get('/benefits', [BenefitController::class, 'index'])
    ->name('benefits.index');

Route::get('/login', [AuthController::class, 'showLogin'])
    ->name('login');
