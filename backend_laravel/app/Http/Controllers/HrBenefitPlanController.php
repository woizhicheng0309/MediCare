<?php

namespace App\Http\Controllers;

use App\Services\HrBenefitPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrBenefitPlanController extends Controller
{
    // Laravel injects the service; controllers coordinate HTTP input and output.
    public function __construct(private readonly HrBenefitPlanService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['benefit_plans' => $this->service->plans($request->user('api'))]);
    }
}
