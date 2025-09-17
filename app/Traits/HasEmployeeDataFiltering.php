<?php

namespace App\Traits;

trait HasEmployeeDataFiltering
{
    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getTableQuery();

        $user = auth()->user();

        if (!$user) {
            return $query;
        }

        // Super admin sees everything
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // Manager sees managed employees' data
        if ($user->hasRole('manager')) {
            $managedEmployeeIds = $user->getManagedEmployeeIds();
            if (!empty($managedEmployeeIds)) {
                $query->whereIn('employee_id', $managedEmployeeIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Viewer sees only their own data
        elseif ($user->hasRole('viewer') && $user->employee_id) {
            $query->where('employee_id', $user->employee_id);
        }

        // Default: no access for other roles
        else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}