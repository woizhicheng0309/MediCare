<?php

namespace App\Http\Controllers;

use App\Services\HrSummaryService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\HrSummaryRequest;

class HrSummaryController extends Controller
{
    public function __construct(
        private readonly HrSummaryService $service,
    ) {}

    public function summary(HrSummaryRequest $request): JsonResponse
    {
        $year = (int) $request->validated('year', now()->year);

        return response()->json(
            $this->service->summary($request->user('api'), $year)
        );
    }
    public function clinicStatistics(HrSummaryRequest $request): JsonResponse
    {
        $year = (int) $request->validated('year', now()->year);

        return response()->json(
            $this->service->clinicStatistics($request->user('api'), $year)
        );
    }
}