<?php
namespace App\Managers;

use App\Models\User;
use App\Repositories\UserRepository;

class UserManager
{
    /** @var UserRepository */
    private $userRepository;

    /** @var User[] */
    private $users = [];

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @param int $uid
     * @return User
     */
    public function getUser($uid)
    {
        if ($uid && isset($this->users[$uid])) {
            return $this->users[$uid];
        }

        $user = $this->userRepository->get($uid);

        if ($user) {
            $this->users[$user->getUid()] = $user;
            return $user;
        }

        return new User();
    }

    /**
     * @param User $user
     */
    public function setUser(User $user)
    {
        $this->users[$user->getUid()] = $user;
    }

    /**
     * @param string $login
     * @param string $password
     * @return User|null
     */
    public function getUserByLogin($login, $password)
    {
        $user = $this->userRepository->findByPassword($login, $password);

        if ($user) {
            $this->users[$user->getUid()] = $user;
            return $user;
        }

        return null;
    }
}
