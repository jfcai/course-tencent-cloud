<?php

namespace App\Http\Web\Services;

use App\Builders\ImMessageList as ImMessageListBuilder;
use App\Caches\ImHotGroupList as ImHotGroupListCache;
use App\Caches\ImHotUserList as ImHotUserListCache;
use App\Library\Paginator\Query as PagerQuery;
use App\Models\ImFriendMessage as ImFriendMessageModel;
use App\Models\ImFriendUser as ImFriendUserModel;
use App\Repos\ImChatGroup as ImChatGroupRepo;
use App\Repos\ImFriendMessage as ImFriendMessageRepo;
use App\Repos\ImFriendUser as ImFriendUserRepo;
use App\Repos\ImGroupMessage as ImGroupMessageRepo;
use App\Repos\User as UserRepo;
use App\Validators\ImChatGroup as ImChatGroupValidator;
use App\Validators\ImMessage as ImMessageValidator;
use App\Validators\User as UserValidator;
use GatewayClient\Gateway;

class Messenger extends Service
{

    public function init()
    {
        $user = $this->getLoginUser();

        $mine = [
            'id' => $user->id,
            'username' => $user->name,
            'sign' => $user->sign,
            'avatar' => $user->avatar,
            'status' => 'online',
        ];

        $friend = $this->handleFriendList($user->id);

        $group = $this->handleGroupList($user->id);

        return [
            'mine' => $mine,
            'friend' => $friend,
            'group' => $group,
        ];
    }

    public function searchUsers($query)
    {

    }

    public function searchGroups($query)
    {

    }

    public function getHotUsers()
    {
        $cache = new ImHotUserListCache();

        $items = $cache->get();

        $pager = new \stdClass();

        $pager->total_items = count($items);
        $pager->total_pages = 1;
        $pager->items = $items;

        return $pager;
    }

    public function getHotGroups()
    {
        $cache = new ImHotGroupListCache();

        $items = $cache->get();

        $pager = new \stdClass();

        $pager->total_items = count($items);
        $pager->total_pages = 1;
        $pager->items = $items;

        return $pager;
    }

    public function getGroupUsers()
    {
        $id = $this->request->getQuery('id');

        $validator = new ImChatGroupValidator();

        $group = $validator->checkGroupCache($id);

        $groupRepo = new ImChatGroupRepo();

        $users = $groupRepo->findGroupUsers($group->id);

        if ($users->count() == 0) {
            return [];
        }

        $baseUrl = kg_ci_base_url();

        $result = [];

        foreach ($users->toArray() as $user) {
            $user['avatar'] = $baseUrl . $user['avatar'];
            $result[] = [
                'id' => $user['id'],
                'username' => $user['name'],
                'avatar' => $user['avatar'],
                'sign' => $user['sign'],
            ];
        }

        return $result;
    }

    public function getChatLog()
    {
        $user = $this->getLoginUser();

        $pagerQuery = new PagerQuery();

        $params = $pagerQuery->getParams();

        $validator = new ImMessageValidator();

        $validator->checkType($params['type']);

        $sort = $pagerQuery->getSort();
        $page = $pagerQuery->getPage();
        $limit = $pagerQuery->getLimit();

        if ($params['type'] == 'friend') {

            $params['chat_id'] = ImFriendMessageModel::getChatId($user->id, $params['id']);

            $messageRepo = new ImFriendMessageRepo();

            $pager = $messageRepo->paginate($params, $sort, $page, $limit);

            return $this->handleChatLog($pager);

        } elseif ($params['type'] == 'group') {

            $params['group_id'] = $params['id'];

            $messageRepo = new ImGroupMessageRepo();

            $pager = $messageRepo->paginate($params, $sort, $page, $limit);

            return $this->handleChatLog($pager);
        }
    }

    public function bindUser()
    {
        $user = $this->getLoginUser();

        $clientId = $this->request->getPost('client_id');

        Gateway::$registerAddress = '127.0.0.1:1238';

        Gateway::bindUid($clientId, $user->id);

        $userRepo = new UserRepo();

        $chatGroups = $userRepo->findImChatGroups($user->id);

        if ($chatGroups->count() > 0) {
            foreach ($chatGroups as $group) {
                Gateway::joinGroup($clientId, $this->getGroupName($group->id));
            }
        }

        /**
         * @todo 发送未读消息
         */

        /**
         * @todo 发送盒子消息
         */
    }

    public function sendMessage()
    {
        $user = $this->getLoginUser();

        $from = $this->request->getPost('from');
        $to = $this->request->getPost('to');

        $content = [
            'username' => $from['username'],
            'avatar' => $from['avatar'],
            'content' => $from['content'],
            'fromid' => $from['id'],
            'id' => $from['id'],
            'type' => $to['type'],
            'timestamp' => 1000 * time(),
            'mine' => false,
        ];

        if ($to['type'] == 'group') {
            $content['id'] = $to['id'];
        }

        $message = json_encode([
            'type' => 'show_message',
            'content' => $content,
        ]);

        Gateway::$registerAddress = '127.0.0.1:1238';

        if ($to['type'] == 'friend') {

            /**
             * 不推送自己给自己发送的消息
             */
            if ($user->id != $to['id']) {
                Gateway::sendToUid($to['id'], $message);
            }

        } elseif ($to['type'] == 'group') {

            $excludeClientId = null;

            /**
             * 不推送自己在群组中发的消息
             */
            if ($user->id == $from['id']) {
                $excludeClientId = Gateway::getClientIdByUid($user->id);
            }

            $groupName = $this->getGroupName($to['id']);

            Gateway::sendToGroup($groupName, $message, $excludeClientId);
        }
    }

    public function updateSignature()
    {
        $sign = $this->request->getPost('sign');

        $user = $this->getLoginUser();

        $validator = new UserValidator();

        $validator->checkSign($sign);

        $user->update(['sign' => $sign]);

        return $user;
    }

    public function applyFriend()
    {
        $friendId = $this->request->getPost('friend_id');

        $user = $this->getLoginUser();

        $userValidator = new UserValidator();

        $friend = $userValidator->checkUser($friendId);

        $friendUserRepo = new ImFriendUserRepo();

        $friendUser = $friendUserRepo->findFriendUser($user->id, $friend->id);

        if (!$friendUser) {
            $model = new ImFriendUserModel();
            $model->user_id = $user->id;
            $model->friend_id = $friend->id;
            $model->create();
        }

        /**
         * @todo 向对方发好友申请的系统消息
         */
    }

    public function approveFriend()
    {
        $friendId = $this->request->getPost('friend_id');

        $user = $this->getLoginUser();

        $userValidator = new UserValidator();

        $friend = $userValidator->checkUser($friendId);

        $friendUserRepo = new ImFriendUserRepo();

        $friendUser = $friendUserRepo->findFriendUser($user->id, $friend->id);

        if (!$friendUser) {
            $model = new ImFriendUserModel();
            $model->user_id = $user->id;
            $model->friend_id = $friend->id;
            $model->create();
        }

        /**
         * @todo 向对方发通过好友申请的系统消息
         */
    }

    public function refuseFriend()
    {
        $friendId = $this->request->getPost('friend_id');

        $user = $this->getLoginUser();

        $userValidator = new UserValidator();

        $friend = $userValidator->checkUser($friendId);

        /**
         * @todo 向对方发拒绝添加好友的系统消息
         */
    }

    public function applyGroup()
    {
    }

    public function approveGroup()
    {
    }

    public function refuseGroup()
    {
    }

    protected function handleFriendList($userId)
    {
        $userRepo = new UserRepo();

        $friendGroups = $userRepo->findImFriendGroups($userId);
        $friendUsers = $userRepo->findImFriendUsers($userId);

        $items = [];

        $items[] = ['id' => 0, 'groupname' => '我的好友', 'list' => []];

        if ($friendGroups->count() > 0) {
            foreach ($friendGroups as $group) {
                $items[] = ['id' => $group->id, 'groupname' => $group->name, 'online' => 0, 'list' => []];
            }
        }

        if ($friendUsers->count() == 0) {
            return $items;
        }

        $userIds = kg_array_column($friendUsers->toArray(), 'friend_id');

        $users = $userRepo->findByIds($userIds);

        $userMappings = [];

        foreach ($users as $user) {
            $userMappings[$user->id] = [
                'id' => $user->id,
                'username' => $user->name,
                'avatar' => $user->avatar,
                'sign' => $user->sign,
                'status' => 'online',
            ];
        }

        foreach ($items as $key => $item) {
            foreach ($friendUsers as $friendUser) {
                $userId = $friendUser->friend_id;
                if ($item['id'] == $friendUser->group_id) {
                    $items[$key]['list'][] = $userMappings[$userId];
                } else {
                    $items[0]['list'][] = $userMappings[$userId];
                }
            }
        }

        return $items;
    }

    protected function handleGroupList($userId)
    {
        $userRepo = new UserRepo();

        $groups = $userRepo->findImChatGroups($userId);

        if ($groups->count() == 0) {
            return [];
        }

        $baseUrl = kg_ci_base_url();

        $result = [];

        foreach ($groups->toArray() as $group) {
            $group['avatar'] = $baseUrl . $group['avatar'];
            $result[] = [
                'id' => $group['id'],
                'groupname' => $group['name'],
                'avatar' => $group['avatar'],
            ];
        }

        return $result;
    }

    protected function handleChatLog($pager)
    {
        if ($pager->total_items == 0) {
            return $pager;
        }

        $messages = $pager->items->toArray();

        $builder = new ImMessageListBuilder();

        $users = $builder->getUsers($messages);

        $items = [];

        foreach ($messages as $message) {

            $user = $user = $users[$message['user_id']] ?? new \stdClass();

            $items[] = [
                'id' => $message['id'],
                'content' => $message['content'],
                'create_time' => $message['create_time'],
                'timestamp' => $message['create_time'] * 1000,
                'user' => $user,
            ];
        }

        $pager->items = $items;

        return $pager;
    }

    protected function getGroupName($groupId)
    {
        return "group_{$groupId}";
    }

}