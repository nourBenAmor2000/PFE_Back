<?php

namespace Modules\Visit\App\Policies;

use Modules\Visit\App\Models\Visit;
use Modules\Admin\App\Models\Admin;
use Modules\Agent\App\Models\Agent;
use Modules\Client\App\Models\Client;
use Illuminate\Auth\Access\HandlesAuthorization;

class VisitPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny($user): bool
    {
        // Admin global can view all
        if ($user instanceof Admin) {
            return true;
        }

        // Agents can view visits for their agency
        if ($user instanceof Agent) {
            return !is_null($user->agency_id);
        }

        // Clients can view their own visits
        if ($user instanceof Client) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view($user, Visit $visit): bool
    {
        // Admin global can view all
        if ($user instanceof Admin) {
            return true;
        }

        // Agents can view visits for their agency
        if ($user instanceof Agent) {
            $logement = $visit->logement;
            return $logement && $logement->agency_id === $user->agency_id;
        }

        // Clients can view their own visits
        if ($user instanceof Client) {
            return $visit->client_id === $user->_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create($user): bool
    {
        // Admin global can create
        if ($user instanceof Admin) {
            return true;
        }

        // Agents (personnel) can create visits
        if ($user instanceof Agent) {
            return in_array($user->role, ['admin_agence', 'agent'], true);
        }

        // Clients can create their own visits
        if ($user instanceof Client) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update($user, Visit $visit): bool
    {
        // Admin global can update all
        if ($user instanceof Admin) {
            return true;
        }

        // Agents can update visits for their agency
        if ($user instanceof Agent) {
            $logement = $visit->logement;
            return $logement && $logement->agency_id === $user->agency_id 
                && in_array($user->role, ['admin_agence', 'agent'], true);
        }

        // Clients can update their own visits
        if ($user instanceof Client) {
            return $visit->client_id === $user->_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete($user, Visit $visit): bool
    {
        // Admin global can delete all
        if ($user instanceof Admin) {
            return true;
        }

        // Agents can delete visits for their agency
        if ($user instanceof Agent) {
            $logement = $visit->logement;
            return $logement && $logement->agency_id === $user->agency_id 
                && in_array($user->role, ['admin_agence', 'agent'], true);
        }

        // Clients can delete their own visits
        if ($user instanceof Client) {
            return $visit->client_id === $user->_id;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore($user, Visit $visit): bool
    {
        return $this->delete($user, $visit);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete($user, Visit $visit): bool
    {
        // Only admin global can force delete
        return $user instanceof Admin;
    }
}
