<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

// Keep business operations and data access separate from HTTP responses.
class HrEmployeeService
{
    public function employees(User $user): Collection
    {

        abort_unless($user->role === 'hr', 403);
        abort_if($user->company_id === null, 403);

        $employees = User::where('company_id', $user->company_id)
            ->where('role', 'employee')
            ->get(['id', 'name', 'email', 'company_id']);

        return $employees;
    }

    public function visits(User $hr, int $employeeId): array
    {

        abort_unless($hr->role === 'hr', 403);
        abort_if($hr->company_id === null, 403);

        $employee = User::where('company_id', $hr->company_id)
            ->where('role', 'employee')
            ->findOrFail($employeeId);

        $visits = $employee->visits()
            ->with('medications')
            ->orderBy('visited_on', 'desc')
            ->get();

        return ['employee' => ['id' => $employee->id, 'name' => $employee->name], 'visits' => $visits];

    }
}
