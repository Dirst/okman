<?php

namespace Dirst\OkTools\Groups;

use Dirst\OkTools\OkToolsBaseControl;
use Dirst\OkTools\OkToolsClient;
use Dirst\OkTools\OkToolsPhotoUploader;

use Dirst\OkTools\Exceptions\OkToolsGroupRoleAssignException;
use Dirst\OkTools\Exceptions\Group\OkToolsGroupJoinException;
use Dirst\OkTools\Exceptions\Group\OkToolsGroupLoadException;
use Dirst\OkTools\Exceptions\Group\OkToolsGroupMembersLoadException;
use Dirst\OkTools\Exceptions\Group\OkToolsGroupGetFeedsException;
use Dirst\OkTools\Exceptions\Group\OkToolsGroupPostTopicException;
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
    
    // @var boolean is joined to group.
    private $isJoined;

    /**
     * Init Group control object.
     *
     * @param OkToolsClient $okTools
     *   Ok Tools Base object.
     * @param int $groupId
     *   Group Id to init the group. Website format.
     * @param boolean $convertId
     *   Convert group Id to mobile application number.
     *
     * @throws OkToolsGroupLoadException
     *   When group load has been failed.
     */
    public function __construct(OkToolsClient $okTools, $groupId, $convertId = true)
    {
        // Init client and group id.
        parent::__construct($okTools);

        // Calculate api group ID.
        $this->groupId = $groupId = $convertId ? $this->OkToolsClient->convertId($groupId) : $groupId;

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
        
         $this->isJoined = false;
   
        // Check subscription.
        if (isset($groupInfo['group_getUserGroupsByIds_response']) && isset($groupInfo['group_getUserGroupsByIds_response'][0])) {
            if (in_array($groupInfo['group_getUserGroupsByIds_response'][0]['status'], ["ACTIVE", "MODERATOR", "ADMIN"]) ) {
                $this->isJoined = true;
            }
        }

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
     * @param string $statuses
     *
     * @throws OkToolsGroupMembersLoadException
     *   On members load exception.
     *
     * @return array
     *   Array of users
     */
    public function getMembers($pageAnchor = null, $direction = "FORWARD", $count = 30, $statuses = null)
    {
        $form = [
          "application_key" => $this->OkToolsClient->getAppKey(),
          "count" => $count,
          "direction" => $direction,
          "fields" => "*,user.*",
          "gid" => $this->groupId,
          "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key']
        ];
        
        // Get user by group status.
        if ($statuses) {
          $form['statuses'] = $statuses;
        }

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
        if ($this->isJoined == false) {
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
                $this->isJoined = true;
            }
        }
    }

    /**
     * Leave the group.
     *
     * @throws OkToolsGroupJoinException
     *   If couldn't leave the group.
     */
    public function leaveTheGroup()
    {
        if ($this->isJoined == true) {
            // Send join request.
            $form = [
             "application_key" => $this->OkToolsClient->getAppKey(),
             "group_id" => $this->groupId,
             "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key']
            ];
            $groupLeft = $this->OkToolsClient->makeRequest(
                $this->OkToolsClient->getApiEndpoint() . "/group/leave",
                $form
            );
    
            // Check on success.
            if (!@$groupLeft['success']) {
                throw new OkToolsGroupJoinException(
                    "Couldn't leave the group. ",
                    var_export($this->groupInfo + $groupLeft, true)
                );
            } else {
                $this->isJoined = false;
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
        return $this->isJoined;
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
     * Revoke moderator status from the user in the current group.
     *
     * @param int $userId
     *   User id to remove role from.
     *
     * @throws OkToolsGroupRoleAssignException
     *   If no success on status revoke.
     */
    public function revokeModeratorStatus($userId)
    {
        $form = [
          "application_key" => $this->OkToolsClient->getAppKey(),
          "gid" => $this->groupId,
          'session_key' => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key'],
          "uid" => $userId
        ];

      // Send request.
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . "/group/revokeModeratorStatus",
            $form
        );

      // Check success status.
        if (!(isset($result['success']) && $result['success'])) {
            throw new OkToolsGroupRoleAssignException("Couldn't revoke moderator status", var_export($result, true));
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
            $this->OkToolsClient->getApiEndpoint() . "/mediatopic/getTopics",
            $form
        );

        // Couldn't retrieve group topics feed.
        if (isset($result['error_code'])) {
            throw new OkToolsGroupGetFeedsException("Couldn't get group feed.", var_export($result, true));
        } else {
            return $result;
        }
    }

    /**
     * Get topic details.
     *
     * @param string $topicId
     *   Topic Id.
     *
     * @return array
     *   Array of topic details.
     * 
     * @throws OkToolsGroupGetTopicDetailsException
     *   Thrown when couldn't get appropriate response.
     */
    public function getTopicDetails($topicId)
    {
        $methods = [];
        $methods[] = [
            "method" => "discussions.get",
            "params" => [
                "discussionId" => $topicId,
                "discussionType" => "GROUP_TOPIC",
                "features" => "PRODUCT.1",
                "fieldset" => "android.2"
            ]
        ];

        $methods[] = [
            "method" => "mediatopic.getByIds",
            "params" => [
                "features" => "PRODUCT.1",
                "fields" => "media_topic.*,app.*,group_album.*,catalog.*,group_photo.*,group.*,music_track."
                . "*,poll.*,present.*,present_type.*,present_type.has_surprise,status.*,album.*,photo.*, video.*,"
                . "achievement_type.*,place.*,comment.*,comment.attachments,attachment_photo.*,attachment_audio_rec.*,"
                . "attachment_movie.*,attachment_topic.*,comment.attachment_resources,mood.*,motivator.*,user.gender,"
                . "user.pic190x190,user.first_name,user.name,user.uid,user.location,user.last_name,user.age,"
                . "user.online",
                "topic_ids" => [
                    "supplier" => "discussions.get.topic_id"
                ]
            ]
        ];
        
        $form = [
            "application_key" => $this->OkToolsClient->getAppKey(),
            "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key'],
            "id" => "discussions.getComments",
            "format" => "json",
            "methods" => json_encode($methods),
        ];

        // Send request.
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . "/batch/execute",
            $form,
            "post"
        );

        // Check result.
        if (isset($result['mediatopic_getByIds_response'])) {
            return $result['mediatopic_getByIds_response'];
        } else {
            throw new OkToolsGroupGetTopicDetailsException(
              "Couldn't get details of topic",
              var_export($result, true)
            );
        }
    }
    
    /**
     * Post media topic.
     *
     * @param array $mediaData
     *   Array of medias to post. Example.
     *   [
     *      ["type" => "text", "text" => "SOME TEDT OR LINK"],
     *      [
     *        "type" => "photo",
     *        "list" => [
     *          ["id" => "UPLOADED_PHOTO_ID"],
     *          ...
     *        ]
     *      ],
     *      [
     *        "type" => "movie", // video
     *        "list" => [
     *          ["id" => "UPLOADED_VIDEO_ID"],
     *          ...
     *        ]
     *      ],
     *      
     *   ]
     * @param boolean $disableComments
     *   Should comments be disabled.
     * @param boolean $onBehalfOfGroup
     *   On user or group behalf to post.
     * @param boolean $previewLink
     *   Show link preview in post.
     *   Will work if there is only one link has been attached.
     * @param boolen $promo
     *   Will it be promo post or not.
     *
     * @throws OkToolsGroupPostTopicException
     *   Post topic problem.
     * 
     * @return string
     *   Post Id.
     */
    public function postMediaTopic(
        array $mediaData,
        $disableComments = false,
        $onBehalfOfGroup = true,
        $previewLink = false,
        $promo = false
    ) {
        // Check what of topic it will be. PROMO OR NOT.
        if ($promo) {
          $type = "GROUP_THEME_PROMO";
        } else {
          $type = "GROUP_THEME";
        }

        // Set up post data.
        $attachments = [
            "disableComments" => $disableComments,
            "media" => $mediaData,
            "onBehalfOfGroup" => $onBehalfOfGroup
        ];
        $form = [
            "application_key" => $this->OkToolsClient->getAppKey(),
            "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key'],
            "gid" => $this->groupId,
            "text_link_preview" => $previewLink,
            "type" => $type,
            "attachment" => json_encode($attachments)
        ];

        // Send request.
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . "/mediatopic/post",
            $form,
            "post"
        );

        // Check if post id has been returned.
        if (is_numeric($result)) {
            return $result;
        } else {
            throw new OkToolsGroupPostTopicException(
                "Post topic problem.",
                var_export($result, true)
            );
        }
    }
    
    /**
     * Upload photo by url and retrieve it's uploaded ID.
     *
     * @param string $imagePath
     *   Path to image to upload.
     *
     * @return string
     *   Photo Id.
     */
    public function uploadAndGetPhotoId($imagePath)
    {
        $photoUploader = new OkToolsPhotoUploader($this->OkToolsClient);
        $uploadData = $photoUploader->getPhotoUploadUrl($this->groupId);
        $result = $photoUploader->uploadPhotoViaRetrievedUrl($uploadData['upload_url'], $imagePath);
        return current($result['photos'])['token'];
    }    
}
