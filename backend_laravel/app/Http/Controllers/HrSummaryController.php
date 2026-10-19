<?php

namespace App\Http\Controllers;

use App\Services\HrSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrSummaryController extends Controller
{
    public function __construct(
        private readonly HrSummaryService $service,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->summary($request->user('api'))
        );
    }
}