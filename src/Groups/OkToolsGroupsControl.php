<?php

namespace Dirst\OkTools\Groups;

use Dirst\OkTools\OkToolsBaseControl;
use Dirst\OkTools\OkToolsClient;

use Dirst\OkTools\Exceptions\Invite\OkToolsInviteCantBeDoneException;
use Dirst\OkTools\Exceptions\Invite\OkToolsInviteDoneBeforeException;
use Dirst\OkTools\Exceptions\Invite\OkToolsInviteAcceptorNotFoundException;
use Dirst\OkTools\Exceptions\OkToolsDomItemNotFoundException;
use Dirst\OkTools\Exceptions\Invite\OkToolsInviteFailedException;
use Dirst\OkTools\Exceptions\OkToolsGroupRoleAssignException;
use Dirst\OkTools\Exceptions\Group\OkToolsGroupJoinException;
use Dirst\OkTools\Exceptions\Group\OkToolsGroupLoadException;
use Dirst\OkTools\Exceptions\Group\OkToolsGroupMembersLoadException;

/**
 * Groups control for account.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkToolsGroupsControl extends OkToolsBaseControl
{
    // @var array group info.
    private $groupInfo;
    
    // @var string group id converted to api format.
    private $groupId;

    /**
     * Init Group control object.
     *
     * @param OkToolsClient $okTools
     *   Ok Tools Base object.
     * @param int $groupId
     *   Group Id to init the group. Website format.
     *
     * @throws OkToolsGroupLoadException
     *   When group load has been failed.
     */
    public function __construct(OkToolsClient $okTools, $groupId)
    {
        // Init client and group id.
        parent::__construct($okTools);

        // Calculate api group ID.
        $this->groupId = $groupId = $this->OkToolsClient->convertId($groupId);

        // Retrieve group.
        $form = [
          "application_key" => $this->OkToolsClient->getAppKey(),
          "id" => 'group.getInfo',
          "methods" => json_encode($this->getGroupParams($groupId)),
          "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key']
        ];
        $groupInfo = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . "/batch/execute",
            $form,
            "post"
        );

        // Check if group has been retrieved.
        if (isset($groupInfo['group_getInfo_response']) && isset($groupInfo['group_getInfo_response'][0])) {
            $this->groupInfo = $groupInfo['group_getInfo_response'][0];
        } else {
            throw new OkToolsGroupLoadException("Couldn't load a group.", var_export($groupInfo, true));
        }
    }

    /**
     * Get group members.
     *
     * @param string $pageAnchor
     *   Page anchor string to request members.
     * @param string $direction
     *   Direction to get users.
     *
     * @throws OkToolsGroupMembersLoadException
     *   On members load exception.
     *
     * @return array
     *   Array of users
     */
    public function getMembers($pageAnchor = null, $direction = "FORWARD", $count = 30)
    {
        $form = [
          "application_key" => $this->OkToolsClient->getAppKey(),
          "count" => $count,
          "direction" => $direction,
          "fields" => "*,user.*",
          "gid" => $this->groupId,
          "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key']
        ];
        $members = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . "/group/getMembersV2",
            $form,
            "post"
        );
        
        // Check if members have been retrieved.
        if (!isset($members['members'])) {
            throw new OkToolsGroupMembersLoadException(
                "Couldn't load members of the group.",
                var_export($this->groupInfo + $members, true)
            );
        }
        
        return $members;
    }

    /**
     * Join the group.
     *
     * @throws OkToolsGroupJoinException
     *   If couldn't join the group.
     */
    public function joinTheGroup()
    {
        // Join the group if not already.
        if ($this->groupInfo['feed_subscription'] == false) {
          // Send join request.
            $form = [
            "application_key" => $this->OkToolsClient->getAppKey(),
            "group_id" => $this->groupId,
            "maybe" => "false",
            "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key']
            ];
            $groupJoined = $this->OkToolsClient->makeRequest(
                $this->OkToolsClient->getApiEndpoint() . "/group/join",
                $form,
                "get"
            );
    
            // Check on success.
            if (!@$groupJoined['success']) {
                throw new OkToolsGroupJoinException(
                    "Couldn't join the group. ",
                    var_export($this->groupInfo + $groupJoined, true)
                );
            } else {
                $this->groupInfo['feed_subscription'] = true;
            }
        }
    }

    /**
     * Get form paramaters to get the group.
     *
     * @param int $groupId
     *   Group id.
     * @return array
     *   Parameters array to send with form.
     */
    protected function getGroupParams($groupId)
    {
        $methods = [];
        $methods[] = [
          "method" => "group.getInfo",
          "params" => [
            "fields" => "city,end_date,premium,messaging_allowed,product_create_allowed,products_tab_hidden,"
              . "phone,photo_id,location_latitude,invite_allowed,transfers_allowed,name,"
              . "group.status,main_photo,country,description,admin_id,homepage_url,members_count,"
              . "publish_delayed_theme_allowed,product_create_zero_lifetime_allowed,pic_avatar,uid,address,"
              . "category,suggest_theme_allowed,private,product_create_suggested_allowed,"
              . "group_photo.*,subcategory_id,notifications_subscription,"
              . "business,stats_allowed,start_date,scope_id,location_longitude,created_ms,"
              . "catalog_create_allowed,feed_subscription,add_theme_allowed",
            "move_to_top" => "true",
            "uids" => $groupId
          ]
        ];

        $methods[] = [
          "method" => "group.getCounters",
          "params" => [
            "counterTypes" => "THEMES,PHOTOS,MEMBERS,VIDEOS,LINKS,BLACK_LIST,"
            . "JOIN_REQUESTS,PRODUCTS,OWN_PRODUCTS,SUGGESTED_PRODUCTS",
            "group_id" => $groupId
          ],
          "onError" => "SKIP"
        ];

        $methods[] = [
          "method" => "group.getUserGroupsByIds",
          "params" => [
            "group_id" => $groupId,
            "uids" => $this->OkToolsClient->getLoginData()['auth_login_response']['uid']
          ],
          "onError" => "SKIP"
        ];

        $methods[] = [
          "method" => "stream.isSubscribed",
          "params" => [
            "gid" => $groupId,
            "uids" => $this->OkToolsClient->getLoginData()['auth_login_response']['uid']
          ],
          "onError" => "SKIP"
        ];

        $methods[] = [
          "method" => "users.getInfo",
          "params" => [
            "client" => "android_8_15.2.1",
            "emptyPictures" => "false",
            "fields" => "last_name,first_name,name",
            "uids" => [
              "supplier" => "group.getInfo.admin_ids"
            ]
          ],
          "onError" => "SKIP"
        ];

        $methods[] = [
          "method" => "translations.get",
          "params" => [
            "keys" => [
              "supplier" => "group.getInfo.scope_id"
            ],
            "package" => "altgroup.category"
          ],
          "onError" => "SKIP"
        ];

        $methods[] = [
          "method" => "group.getFriendMembers",
          "params" => [
            "group_id" => $groupId
          ],
          "onError" => "SKIP"
        ];

        $methods[] = [
          "method" => "photos.getPhotoInfo",
          "params" => [
            "fields" => "group_photo.picmp4",
            'gid' => $groupId,
            "photo_id" => [
              "supplier" => "group.getInfo.photo_id"
            ]
          ],
          "onError" => "SKIP"
        ];

        $methods[] = [
          "method" => "group.getInstalledApps",
          "params" => [
            "fields" => "*",
            'gid' => $groupId
          ],
          "onError" => "SKIP"
        ];

        return $methods;
    }

    /**
     * Check if account already joined to group.
     *
     * @return boolean
     *   Joined/not joined flag.
     */
    public function isJoinedToGroup()
    {
        return $this->groupInfo['feed_subscription'];
    }

    /**
     * Perform invite operation.
     *
     * @notice Currently assume that account is moderator for group to invite to.
     *
     * @param int $userId
     *   User id to invite. Id should be in api format.
     * @param int $groupId
     *   Group Id to invite to.
     *
     * @throws OkToolsInviteCantBeDoneException
     *   Invite couldn't be done as user blocked this ability or account is blocked.
     * @throws OkToolsInviteAcceptorNotFoundException
     *   Couldn't find acceptor group. Group has been blocked or there is no group on this page.
     * @throws OkToolsInviteDoneBeforeException
     *   Invite has been done before for this user.
     * @throws OkToolsDomItemNotFoundException
     *   Couldn't find needed dom element.
     * @throws OkToolsInviteFailedException
     *   Invite confirm request has not been finalyzed correctly.
     */
    public function inviteUserToGroup($userId, $groupId)
    {
        // Form send.
        $form = [
          "application_key" => $this->OkToolsClient->getAppKey(),
          "fid" => $userId,
          "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key'],
          "app.params" => "x"
        ];

        // Get mobile page to invite user.
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . "/user_invite_to_group",
            $form,
            "get",
            true
        );

        // Convert to dom.
        $mobilePage = str_get_html($result);
        
        // Get groups list.
        if (!($list = $mobilePage->find("#groups-list", 0))) {
            throw new OkToolsInviteCantBeDoneException("User couldn't be invited or account is blocked.", $result);
        }

        // Check if already invited.
        if (!($groups = $list->find("li[id*=$groupId]", 0))) {
            throw new OkToolsInviteAcceptorNotFoundException("Couldn't find acceptor group.", $result);
        }

        // Check if already invited.
        if ($groups->find('a[class*="group-select __disabled"]', 0)) {
            throw new OkToolsInviteDoneBeforeException("User has been already invited before.", $result);
        }

        // Sleep pause.
        sleep(rand(0, 3));
        
        // Confirm invite page
        $confirmInvitePage = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . $groups->find("a", 0)->href,
            "get",
            true
        );
        $confirmInvitePage = str_get_html($confirmInvitePage);

        // Check if form exists.
        if (!$confirmInvitePage->find("form", 0)) {
            throw new OkToolsDomItemNotFoundException("Couldn't find invite html form", $confirmInvitePage);
        }
        
        // Sleep pause.
        sleep(rand(1, 3));

        // Send invite request.
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . $confirmInvitePage->find("form", 0)->action,
            ["fr.posted" => "set", "button_send" => "Отправить"],
            "post",
            true
        );
        $mobilePage = str_get_html($result);

        // Check groups lits - success criteria.
        if (!$mobilePage->find("#groups-list", 0)) {
            throw new OkToolsInviteFailedException(
                "Couldn't finalize invite. No groups-list fiund as it should.",
                $result
            );
        }
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
     * @throws OkToolsGroupRoleAssignException
     *   If no success on role assign.
     */
    public function assignGroupRole(OkToolsGroupRoleEnum $role, $userId)
    {
        $form = [
        "application_key" => $this->OkToolsClient->getAppKey(),
        "gid" => $this->groupId,
        "role" => $role->getValue(),
        "uid" => $userId
        ];

      // Send request.
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . "/group/grantModeratorStatus",
            $form
        );

      // Check success status.
        if (!(isset($result['success']) && $result['success'])) {
            throw new OkToolsGroupRoleAssignException("Couldn't assign role to user", var_export($result, true));
        }
    }
}
