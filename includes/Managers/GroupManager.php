<?php
namespace App\Managers;

use App\Models\Group;
use App\Repositories\GroupRepository;

class GroupManager
{
    /** @var GroupRepository */
    private $groupRepository;

    /** @var Group[] */
    private $groups = [];
    private $groupsFetched = false;

    public function __construct(GroupRepository $groupRepository)
    {
        $this->groupRepository = $groupRepository;
    }

    /**
     * @return Group[]
     */
    public function getGroups()
    {
        if (!$this->groupsFetched) {
            $this->fetchGroups();
        }

        return $this->groups;
    }

    /**
     * @param $id
     * @return Group|null
     */
    public function getGroup($id)
    {
        if (!$this->groupsFetched) {
            $this->fetchGroups();
        }

        return array_get($this->groups, $id, null);
    }

    private function fetchGroups()
    {
        foreach ($this->groupRepository->all() as $group) {
            $this->groups[$group->getId()] = $group;
        }

        $this->groupsFetched = true;
    }
}