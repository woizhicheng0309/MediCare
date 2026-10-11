<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewVisitRequest;
use App\Services\HelpdeskVisitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpdeskVisitController extends Controller
{
    // Laravel injects the service; controllers coordinate HTTP input and output.
    public function __construct(private readonly HelpdeskVisitService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['visits' => $this->service->pendingVisits($request->user('api'))]);
    }

    public function updateStatus(ReviewVisitRequest $request, int $visitId): JsonResponse
    {
        $visit = $this->service->review($request->user('api'), $visitId, $request->validated());

        return response()->json(['message' => 'Visit status updated successfully.', 'visit' => $visit]);
    }
}
