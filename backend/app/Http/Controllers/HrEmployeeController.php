<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrEmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('api');

        abort_unless($user->role === 'hr', 403);
        abort_if($user->company_id === null, 403);

        $employees = User::where('company_id', $user->company_id)
            ->where('role', 'employee')
            ->get(['id', 'name', 'email', 'company_id']);

        return response()->json([
            'employees' => $employees,
        ]);
    }

    public function visits(Request $request, int $employeeId): JsonResponse
    {
        $hr = $request->user('api');

        abort_unless($hr->role === 'hr', 403);
        abort_if($hr->company_id === null, 403);

        $employee = User::where('company_id', $hr->company_id)
            ->where('role', 'employee')
            ->findOrFail($employeeId);

        $visits = $employee->visits()
            ->with('medications')
            ->orderBy('visited_on', 'desc')
            ->get();

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
            ],
            'visits' => $visits,
        ]);
    }
}