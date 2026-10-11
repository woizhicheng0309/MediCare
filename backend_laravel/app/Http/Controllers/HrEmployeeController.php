<?php

namespace App\Http\Controllers;

use App\Services\HrEmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrEmployeeController extends Controller
{
    // Laravel injects the service; controllers coordinate HTTP input and output.
    public function __construct(private readonly HrEmployeeService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['employees' => $this->service->employees($request->user('api'))]);
    }

    public function visits(Request $request, int $employeeId): JsonResponse
    {
        return response()->json($this->service->visits($request->user('api'), $employeeId));
    }
}
