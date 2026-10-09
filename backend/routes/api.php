<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\BenefitController;
use App\Http\Controllers\HrEmployeeController;
use App\Http\Controllers\HrBenefitPlanController;
use App\Http\Controllers\HrBenefitAssignmentController;
use App\Http\Controllers\HelpdeskVisitController;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('api.login');

Route::get('/me', [AuthController::class, 'me'])
    ->middleware('auth:api')
    ->name('api.me');

Route::get('/visits', [VisitController::class, 'index'])
    ->middleware('auth:api')
    ->name('api.visits.index');

Route::get('/benefits', [BenefitController::class, 'summary'])
    ->middleware('auth:api')
    ->name('api.benefits.summary');

Route::post('/visits', [VisitController::class, 'storeApi'])
    ->middleware('auth:api')
    ->name('api.visits.store');

Route::get('/hr/employees', [HrEmployeeController::class, 'index'])
    ->middleware('auth:api')
    ->name('api.hr.employees.index');

Route::get('/hr/benefit-plans', [HrBenefitPlanController::class, 'index'])
    ->middleware('auth:api')
    ->name('api.hr.benefit-plans.index');

Route::post('/hr/benefit-assignments', [HrBenefitAssignmentController::class, 'store'])
    ->middleware('auth:api')
    ->name('api.hr.benefit-assignments.store');

Route::get('/hr/employees/{employeeId}/visits', [HrEmployeeController::class, 'visits'])
    ->middleware('auth:api')
    ->whereNumber('employeeId')
    ->name('api.hr.employees.visits');

Route::get('/helpdesk/visits', [HelpdeskVisitController::class, 'index'])
    ->middleware('auth:api')
    ->name('api.helpdesk.visits.index');

Route::patch('/helpdesk/visits/{visitId}/status', [HelpdeskVisitController::class, 'updateStatus']) // patch mean only modify part of the data
    ->middleware('auth:api')
    ->whereNumber('visitId')
    ->name('api.helpdesk.visits.status');