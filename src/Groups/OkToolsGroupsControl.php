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
use Dirst\OkTools\Exceptions\Group\OkToolsGroupGetFeedsException;
use Dirst\OkTools\Exceptions\Group\OkToolsGroupGetTopicDetailsException;

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
        
        // Page anchor.
        if ($pageAnchor) {
            $form['anchor'] = $pageAnchor;
        }

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
            $this->OkToolsClient->getMobileVersionUrl() . "/api/user_invite_to_group",
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
            $this->OkToolsClient->getMobileVersionUrl() . $groups->find("a", 0)->href,
            [],
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
            $this->OkToolsClient->getMobileVersionUrl() . $confirmInvitePage->find("form", 0)->action,
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
          'session_key' => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key'],
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

    /**
     * Get media topics feed.
     *
     * @param int $count
     *   Count to retrieve.
     * @param string $anchor
     *   Anchor of the page to get.
     *
     * @throws OkToolsGroupGetFeedsException
     *   Feeds get error.
     *
     * @return array
     *   Result with feeds.
     */
    public function getMediaTopicsFeed($count = 100, $anchor = null)
    {
        $form = [
          "anchor" => $anchor,
          "count" => $count,
          "direction" => "FORWARD",
          "features" => "PRODUCT.1",
          "fields" => "media_topic.*,user.*,app.*,group.*,group_album.*,group_photo.*,discussion.*,"
            . "like_summary.*,music_album.*,music_track.*,music_artist.*,music_playlist.*,video.*,poll.*,present.*,"
            . "present_type.*,status.*,album.*,photo.*,place.*,achievement.*,achievement_type.*,comment.*,"
            . "comment.attachments,attachment_photo.*,attachment_audio_rec.*,attachment_movie.*,attachment_topic.*,"
            . "comment.attachment_resources,mood.*,motivator.*",
          "filter" => "GROUP_THEMES",
          "gid" => $this->groupId,
          "application_key" => $this->OkToolsClient->getAppKey(),
          "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key']
        ];
        
        // Make request.
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . "/mediatopics/getTopics",
            $form
        );

        // Couldn't retrieve group topics feed.
        if (isset($result['error_code'])) {
            throw new OkToolsGroupGetFeedsException("Couldn't get group feed.", var_export($result, true));
        } else {
            return $result;
        }
    }

    
    
//    /**
//     * Get media topics feed.
//     *
//     * @param int $count
//     *   Count to retrieve.
//     * @param string $anchor
//     *   Anchor of the page to get.
//     *
//     * @throws OkToolsGroupGetFeedsException
//     *   Couldn't find anchor.
//     *
//     * @return array
//     *   Result with feeds.
//     */
//    public function getMediaTopicsFeed($count = 100, $anchor = null)
//    {
//        // Get first page anchor.
//        if (!$anchor) {
//            $result = $this->getFeed("stream.get-first");
//
//            // Get anchor.
//            if (isset($result['anchor'])) {
//                $anchor = ['anchor'];
//            } else {
//                throw new OkToolsGroupGetFeedsException("Couldn't find anchor", var_export($result, true));
//            }
//        }
//
//        //  Get more.
//        $result = $this->getFeed("stream.get-more", $count, $anchor);
//        
//        return $result;
//    }
// 
//    /**
//     * Get feeds of the group.
//     *
//     * @param string $id
//     *   Method id.
//     * @param int $count
//     *   Count to retrieve.
//     * @param string $anchor
//     *   Page anchor to use to retrieve feeds page.
//     *
//     * @throws OkToolsGroupGetFeedsException
//     *   Couldn't get feeds response.
//     *
//     * @return array
//     *   Feeds array.
//     */
//    protected function getFeed($id = "stream.get-first", $count = 20, $anchor = null)
//    {
//        $methods = [];
//        $methods[] = [
//            "method" => "stream.get",
//            "params" => [
//                "client" => $this->OkToolsClient->getAndroidClient(),
//                "count" => $count,
//                "anchor" => $anchor,
//                "direction" => "FORWARD",
//                "features" => "PRODUCT.1",
//                "fieldset" => "android.35",
//                "gid" => $this->groupId,
//                "mark_as_read" => "false",
//                "patternset" => "android.13",
//            ]
//        ];
//        $form = [
//            "application_key" => $this->OkToolsClient->getAppKey(),
//            "id" => $id,
//            'session_key' => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key'],
//            "methods" => json_encode($methods)
//        ];
//        
//        // Make request.
//        $result = $this->OkToolsClient->makeRequest(
//            $this->OkToolsClient->getApiEndpoint() . "/batch/execute",
//            $form,
//            "post"
//        );
//        
//        // Get feeds.
//        if (isset($result['stream_get_response'])) {
//            return $result['stream_get_response'];
//        } else {
//            throw new OkToolsGroupGetFeedsException("Couldn't get appropriate response", var_export($result, true));
//        }
//    }
//
//    /**
//     * Get topic details by id.
//     *
//     * @param int $topicId
//     *   Topic id.  
//     *
//     * @throws OkToolsGroupGetTopicDetailsException
//     *   Throws when couldn't get topic details.
//     *
//     * @return array
//     *   Topic details.
//     */
//    public function getTopicDetails($topicId)
//    {
//        $methods = [];
//        $methods[] = [
//            "method" => "discussions.get",
//            "params" => [
//                "discussionId" => $topicId,
//                "discussionType" => "GROUP_TOPIC",
//                "features" => "PRODUCT.1",
//                "fieldset" => "android.1"
//            ]
//        ];
//        $methods[] = [
//            "method" => "mediatopic.getByIds",
//            "params" => [
//                "features" => "PRODUCT.1",
//                "fields" => "media_topic.*,app.*,group_album.*,catalog.*,group_photo.*,group.*,music_track.*,poll.*,"
//                  . "present.*,present_type.*,status.*,album.*,photo.*, video.*,achievement_type.*,place.*,comment.*,"
//                  . "comment.attachments,attachment_photo.*,attachment_audio_rec.*,attachment_movie.*,"
//                  . "attachment_topic.*,comment.attachment_resources,mood.*,motivator.*,user.gender,user.pic190x190,"
//                  . "user.first_name,suser.name,user.uid,user.location,user.last_name,user.age,user.online",
//                "topic_ids" => [
//                  "supplier" => "discussions.get.topic_id"
//                ]
//            ]
//        ];
//        
//        $form = [
//            "application_key" => $this->OkToolsClient->getAppKey(),
//            "id" => "discussions.getComments",
//            'session_key' => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key'],
//            "format" => "json",
//            "methods" => $methods
//        ];
//
//        // Make request.
//        $result = $this->OkToolsClient->makeRequest(
//            $this->OkToolsClient->getApiEndpoint() . "/batch/execute",
//            $form,
//            "post"
//        );
//        
//        // Get response.
//        if (isset($result['discussions_get_response']) && isset($result['mediatopic_getByIds_response'])) {
//            return $result;
//        } else {
//            throw new OkToolsGroupGetTopicDetailsException("Couldn't get topic details.", var_export($result, true));
//        }
//    }
    
//    public function postMediaTopic()
//    {
//      
//    }
}
