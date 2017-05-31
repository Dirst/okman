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

    // @var string of cookies
    private $cookies;

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
     * @param string $proxy
     *   Proxy string type:ip:port:login:pass.
     */
    public function login($login, $pass, $proxy = null)
    {
      
    }

    /**
     * Logout from OK.RU.
     */
    public function logout()
    {
      
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
     * @param OkGroupRole $role
     *   Enum group role object.
     * @param int $uid
     *   Id of the user in OK.
     */
    public function assignGroupRole(OkGroupRole $role, $uid)
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
     * @TODO define content parsing/posting methods.s
     */
    public function getGroupContent();
    public function postContentToGroup();

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
