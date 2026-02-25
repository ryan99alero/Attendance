<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsEmployee
{
    /**
     * Ensure the authenticated user has an employee_id (is linked to an employee record).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->employee_id) {
            // User is not linked to an employee - redirect to admin or show error
            if ($user?->hasAnyRole(['super_admin', 'admin', 'manager'])) {
                return redirect('/admin');
            }

            abort(403, 'You do not have access to the Employee Portal. Please contact your administrator.');
        }

        return $next($request);
    }
}
