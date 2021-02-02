<?php
namespace App\Service;

use App\Models\Service;
use App\Models\User;

class UserServiceAccessService
{
    /**
     * Check if user has access to groups required by a service
     *
     * @param Service $service
     * @param User|null $user
     * @return bool
     */
    public function canUserUseService(Service $service, User $user = null): bool
    {
        $combined = array_intersect($service->getGroups(), $user ? $user->getGroups() : []);
        return empty($service->getGroups()) || !empty($combined);
    }
}
