<?php

namespace Dirst\OkTools\Groups;

use Dirst\OkTools\OkToolsBaseControl;
use Dirst\OkTools\Exceptions\OkToolsResponseException;
use Dirst\OkTools\Exceptions\OkToolsDomItemNotFoundException;
use Dirst\OkTools\Exceptions\OkToolsPageNotFoundException;
use Dirst\OkTools\Exceptions\OkToolsBlockedGroupException;
use Dirst\OkTools\Exceptions\OkToolsNotFoundException;
use Dirst\OkTools\Requesters\RequestersTypesEnum;
use Dirst\OkTools\OkToolsClient;
use Dirst\OkTools\Requesters\RequestersHttpCodesEnum;
use Dirst\OkTools\Groups\OkToolsGroupRoleEnum;
use Dirst\OkTools\Exceptions\OkToolsInviteGroupNotFoundException;
use Dirst\OkTools\Exceptions\OkToolsInviteFailedException;

/**
 * Groups control for account.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkToolsGroupsControl extends OkToolsBaseControl
{
    // @var int
    protected $groupId;

    /**
     * Init Account control object.
     *
     * @param OkToolsClient $okTools
     *   Ok Tools Base object.
     * @param int $groupId
     *   Group Id to init the group.
     */
    public function __construct(OkToolsClient $okTools, $groupId)
    {
        // Init client and group id.
        parent::__construct($okTools);
        $this->groupId = $groupId;
    }

    /**
     * Construct New object with new OktoolsClient insides.
     *
     * @param string $login
     *   User phone number.
     * @param string $pass
     *   Password.
     * @param RequestersTypesEnum $requesterType
     *   Requester to chose.
     * @param string $proxy
     *   Proxy settings to use with request. type:ip:port:login:pass.
     *   Possible types are socks5, http.
     * @param string $userAgent
     *   User agent to be used in requests.
     * @param string $cookiesDir. No Ended slash.
     *   Cookies dir path on a server.
     * @param int $requestPauseSec
     *   Pause before any request to emulate human behaviour.
     *
     * @return OkToolsBaseControl
     *   Control object with Client initialized inside.
     */
    public static function initWithClient(
        $login,
        $pass,
        RequestersTypesEnum $requesterType,
        $groupId,
        $proxy = null,
        $userAgent = null,
        $cookiesDir = null,
        $requestPauseSec = 1
    ) {
        $okToolsClient = new OkToolsClient($login, $pass, $requesterType, $proxy, $userAgent, $cookiesDir, $requestPauseSec);
        return new static($okToolsClient, $groupId);
    }

    /**
     * Get group members.
     *
     * @param string $pageAnchor
     *   Page anchor string to request members.
     * @param string $direction
     *   Direction to get users.
     *
     * @throws OkToolsDomItemNotFoundException
     *   If no users Block is found.
     *
     * @return array
     *   Array of users [id, name, online]
     */
    public function getMembers($pageAnchor = null, $direction = "FORWARD")
    {
    }

    /**
     * Join the group.
     *
     * @throws OkToolsDomItemNotFoundException
     *   If no join link is found.
     */
    public function joinTheGroup()
    {
    }

    /**
     * Check if account already joined to group.
     *
     * @throws OkToolsDomItemNotFoundException
     *   Couldn't find exit/join links.
     *
     * @return boolean
     *   Joined/not joined flag.
     */
    public function isJoinedToGroup()
    {
    }

    /**
     * Cancel group membership for account.
     *
     * @throws OkToolsDomItemNotFoundException
     *   If no exit link found.
     */
    public function leftTheGroup()
    {
    }

    /**
     * Check if it is possible to ivite user.
     *
     * @param int $userId
     *   User to check.
     *
     * @throws OkToolsDomItemNotFoundException
     *   Thrown if unexpected popup data has been returned.
     *
     * @return boolean
     *   Whether or not user can be invited.
     */
    public function canBeInvited($userId)
    {
    }

    /**
     * Perform invite operation.
     *
     * @notice Currently assume that account is moderator for group to invite to.
     *
     * @param int $userId
     *   User id to invite.
     * @param int $groupId
     *   Group Id for invite to.
     *
     * @throws OkToolsDomItemNotFoundException
     *   If couldn't find popup canvas with invites.
     * @throws OkToolsInviteGroupNotFoundException
     *   If no invite group found. Possible issues - account is not in invite acceptor OR already invited.
     * @throws OkToolsInviteFailedException
     *   No successfull response after invite.
     */
    public function inviteUserToGroup($userId, $groupId)
    {
    }

    public function isInvited($userId, $groupId)
    {
    }

    /**
     * Assign role to the user in the current group.
     *
     * @param OkToolsGroupRoleEnum $role
     *   Group role object.
     *
     * @param int $userId
     *   User id to assign role to.
     *
     * @throws OkToolsDomItemNotFoundException
     *   If moderator assign form is not found.
     */
    public function assignGroupRole(OkToolsGroupRoleEnum $role, $userId)
    {
    }

}
