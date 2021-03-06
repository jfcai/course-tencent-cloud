<?php

namespace App\Console\Tasks;

use App\Models\User as UserModel;
use Phalcon\Mvc\Model\Resultset;
use Phalcon\Mvc\Model\ResultsetInterface;

class UnlockUserTask extends Task
{

    public function mainAction()
    {
        $users = $this->findUsers();

        if ($users->count() == 0) return;

        foreach ($users as $user) {
            $user->update(['locked' => 0]);
        }
    }

    /**
     * 查找待解锁用户
     *
     * @param int $limit
     * @return ResultsetInterface|Resultset|UserModel[]
     */
    protected function findUsers($limit = 1000)
    {
        $time = time() - 6 * 3600;

        return UserModel::query()
            ->where('locked = 1')
            ->andWhere('lock_expiry_time < :time:', ['time' => $time])
            ->limit($limit)
            ->execute();
    }

}
