<?php

namespace Dirst\OkTools;

/**
 * Base abstract class for all ok tools.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkToolsBase
{
    const URL = "https://m.ok.ru/";
    
    // @var requestInterface object.
    private $requestBehaviour;

    /**
     *
     * @param RequestInterface $requestBehaviour
     *   Request interface object.
     */
    public function __construct(RequestInterface $requestBehaviour)
    {
        $this->requestBehaviour = $requestBehaviour;
    }

    /**
     * Login to OK.RU.
     *
     * @param string $login
     *   User phone number.
     * @param string $pass
     *   Password.
     */
    public function login($login, $pass)
    {
        $postData = 
        [
            'fr.login' => $login,
            'fr.password' => $pass,
            'fr.posted' => 'set',
            'fr.proto' => 1
        ];

        print $this->requestBehaviour->requestPost(self::URL . OkPagesEnum::LOGIN_PATH, $postData);
    }

    /**
     * Logout from OK.RU.
     */
    public function logout()
    {
        $postData = 
        [
            'fr.posted' => 'set',
            'button_logoff' => 'Выйти'
        ];
        print $this->requestBehaviour->requestPost(self::URL . OkPagesEnum::LOGOUT_PATH, $postData);
    }

    /**
     * Check all notifications.
     * - Accept friendship.
     * - Accept gifts.
     * - Close other notifications.
     */
    public function checkNotifications()
    {
      
    }

    /**
     * Assign group role to passed user id.
     *
     * @param OkGroupRoleEnum $role
     *   Enum group role object.
     * @param int $uid
     *   Id of the user in OK.
     * @param int groupId
     *   Id of the group in OK.
     */
    public function assignGroupRole(OkGroupRoleEnum $role, $uid, $groupId)
    {
      
    }

    /**
     * Get all users of the OK group.
     *
     * @param int $groupId
     *   Id of an OK group.
     * @param int $page
     *   Pager position where users will be getted.
     *
     * @return array
     *   User data.
     *
     * @TODO define user properties to return.
     */
    public function getGroupUsers($groupId, $page = 0)
    {
      
    }

    /**
     * Invite user to a group.
     *
     * @param int $groupId
     *   Group Id to invite to.
     * @param int $uid
     *   User to invite to a group.
     */
    public function inviteUserToGroup($groupId, $uid)
    {
      
    }

    /**
     * @TODO define content parsing/posting methods.
     */
//    public function getGroupContent();
//    public function postContentToGroup();

    /**
     * Requests a page with passed relative url.
     *
     * @param string $pageUrl.
     *   Check ok page.
     */
    public function attendPage($pageUrl)
    {
      
    }
}
