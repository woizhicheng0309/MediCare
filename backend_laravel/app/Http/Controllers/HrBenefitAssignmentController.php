<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignBenefitPlanRequest;
use App\Services\HrBenefitAssignmentService;
use Illuminate\Http\JsonResponse;

class HrBenefitAssignmentController extends Controller
{
    // Laravel injects the service; controllers coordinate HTTP input and output.
    public function __construct(private readonly HrBenefitAssignmentService $service) {}

    public function store(AssignBenefitPlanRequest $request): JsonResponse
    {
        $assignment = $this->service->assign($request->user('api'), $request->validated());

        return response()->json(['message' => 'Benefit plan assigned successfully.', 'assignment' => $assignment]);
    }
}
