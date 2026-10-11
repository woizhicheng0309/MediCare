<?php

namespace App\Http\Controllers;

use App\Services\BenefitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BenefitController extends Controller
{
    // Laravel injects the service; controllers coordinate HTTP input and output.
    public function __construct(private readonly BenefitService $service) {}

    public function summary(Request $request): JsonResponse
    {
        $summary = $this->service->summary($request->user('api'));
        if ($summary === null) {
            return response()->json(['message' => 'No benefit plan assigned for this year.'], 404);
        }

        return response()->json($summary);
    }
}
