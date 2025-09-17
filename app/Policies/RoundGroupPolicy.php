<?php

namespace App\Policies;

use App\Models\User;
use App\Models\RoundGroup;
use Illuminate\Auth\Access\HandlesAuthorization;

class RoundGroupPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_round::group');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RoundGroup $roundGroup): bool
    {
        return $user->can('view_round::group');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_round::group');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RoundGroup $roundGroup): bool
    {
        return $user->can('update_round::group');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RoundGroup $roundGroup): bool
    {
        return $user->can('delete_round::group');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_round::group');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, RoundGroup $roundGroup): bool
    {
        return $user->can('force_delete_round::group');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_round::group');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, RoundGroup $roundGroup): bool
    {
        return $user->can('restore_round::group');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_round::group');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, RoundGroup $roundGroup): bool
    {
        return $user->can('replicate_round::group');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_round::group');
    }
}
