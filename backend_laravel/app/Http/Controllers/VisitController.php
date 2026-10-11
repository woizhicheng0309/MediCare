<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisitRequest;
use App\Services\VisitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    // Laravel injects the service; controllers coordinate HTTP input and output.
    public function __construct(private readonly VisitService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['visits' => $this->service->ownVisits($request->user('api'))]);
    }

    public function storeApi(StoreVisitRequest $request): JsonResponse
    {
        $visit = $this->service->submit($request->user('api'), $request->validated());

        return response()->json(['message' => 'Visit recorded successfully.', 'visit' => $visit], 201);
    }
}
