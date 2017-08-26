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
    // @var string group page url constant.
    const GROUP_BASE_URL = "group";
    
    // @var string members page url.
    const GROUP_MEMBERS_BASE_URL = "members";
    
    // @var string html of group page
    protected $groupFrontPage;
    
    // @var int
    protected $groupId;
    
    // @var string
    protected $invitePopup;

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
        
        // Init group page.
        $this->groupFrontPage = $this->initGroupPage();
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
     * @param int $page
     *   Page number to request members.
     *
     * @throws OkToolsDomItemNotFoundException
     *   If no users Block is found.
     *
     * @return array
     *   Array of users [id, name, online]
     */
    public function getMembers($page = 1)
    {
        $usersArray = [];
        if ($page == 1) {
            $membersUrl = $this->getFirstPageGroupMembersUrl();
            $postData = [];
        } else {
            // ADd post parameters.
            $postData = [
                "fetch" => "false",
                "st.page" => $page,
                "st.loaderid" => "GroupMembersResultsBlockLoader"
            ];
            $membersUrl = $this->getSecondaryPageGroupMembersUrl();
        }
        
        // Request members html.
        $membersPage = $this->OkToolsClient->sendForm($membersUrl, $postData, true);
        
        // Convert members page to DOM.
        $membersPageDom = str_get_html($membersPage);

        // Get users block from response.
        $usersBlock = $membersPageDom->find("li.cardsList_li");
        
        // Exception if users block is not exists.
        if (!$usersBlock) {
            throw new OkToolsDomItemNotFoundException("Couldn't find Groups members on $page page for $this->groupId group", $membersPage);
        }

        // Return users array.
        return $this->getUsersArray($usersBlock);
    }
 
    /**
     * Get first page members url.
     *
     * @notice To reduce the risk to be banned 1 page request for members should be done before next pages requests.
     *
     * @throws OkToolsDomItemNotFoundException
     *   Thrown if members link is not found.
     *
     * @return string
     *   Url for the initial page with users.
     */
    protected function getFirstPageGroupMembersUrl()
    {
        // Get members link from group page.
        $groupPageDom = str_get_html($this->groupFrontPage);
        $membersLink = $groupPageDom->find("ul.u-menu > .group_members > a.ucard-v_list_i", 0);
        
        // Check if link exists.
        if (!$membersLink) {
            throw new OkToolsDomItemNotFoundException("Couldn't find members link for $this->groupId group", $this->groupFrontPage);
        }
        
        // Get ajax request prameters.
        $url = $membersLink->href . "&" . $this->getAjaxRequestUrlParams();
        
        // Add previous page parameter.
        $urlPrevious = [
            "st.cmd" => "altGroupMain",
            "st.groupId" => $this->groupId,
            "st.fFeed" => "on",
            "st.vpl.mini" => "false"
        ];

        // Add previous url.
        $url .= "&gwt.previous=" . urlencode(http_build_query($urlPrevious));

        // Return url.
        return $url;
    }

    /**
     * Get url for secondary page members request.
     *
     * @return string
     *   Url for users block secondary page request.
     */
    protected function getSecondaryPageGroupMembersUrl()
    {
        // Parameters to send.
        $params = [
            "cmd" => "GroupMembersResultsBlock",
            "st.cmd" => "altGroupMembers",
            "st.groupId" => $this->groupId,
        ];
        
        // Construct url.
        $url = self::GROUP_BASE_URL . "/" . $this->groupId . "/" . self::GROUP_MEMBERS_BASE_URL;
        $url .= "?" . http_build_query($params) . "&" . $this->getAjaxRequestUrlParams($noPsid = true);
        
        return $url;
    }

    /**
     * Returns users array.
     *
     * @param simple_html_dom_node $usersBlock
     *   List of li elements with users.
     *
     * @return array
     *   Array of users.
     */
    protected function getUsersArray($usersBlock)
    {
        $usersArray = [];
        foreach ($usersBlock as $userBlock) {
            if ($userBlock->find(".add-stub", 0)) {
                continue;
            }

            $usersArray[] = [
                'id' => preg_replace("/.+st\.friendId=(\d+)&.+/", "$1", $userBlock->find("a.photoWrapper", 0)->href),
                'name' => $userBlock->find(".card_add a.o", 0)->plaintext,
                'online' => $userBlock->find("span.ic-online", 0) ? true : false,
                'link' => $userBlock->find("a.photoWrapper", 0)->href
            ];
        }

        return $usersArray;
    }

    /**
     * Join the group.
     *
     * @throws OkToolsDomItemNotFoundException
     *   If no join link is found.
     */
    public function joinTheGroup()
    {
        // Convert to dom & find join button.
        $groupPageDom = str_get_html($this->groupFrontPage);
        $joinLink = $groupPageDom->find('a[href*="AltGroupTopCardButtonsJoin"]', 0);
        
        // If no join link is found.
        if (!$joinLink) {
            throw new OkToolsDomItemNotFoundException("Couldn't find join link in group - $this->groupId", $groupPageDom->outertext);
        }
        
        // Set up url and send request.
        $jsUrl = $joinLink->href . "&" . $this->getAjaxRequestUrlParams();
        $this->OkToolsClient->sendForm($jsUrl, [], true);
        
        // Reinit group page.
        $this->groupFrontPage = $this->initGroupPage();
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
        // Convert Group front page.
        $groupPageDom = str_get_html($this->groupFrontPage);
        
        // retrieve links that soulg exists for each case.
        $exitLink = $groupPageDom->find("a[href*='GroupJoinDropdownBlock&amp;st.jn.act=EXIT']", 0);
        $joinLink = $groupPageDom->find('a[href*="AltGroupTopCardButtonsJoin"]', 0);
        
        // Check if case link exists.
        switch (true) {
            // True if joined.
            case $exitLink:
                return true;
              break;
            
            // False if join link exists.
            case $joinLink:
                return false;
              break;
            
            // Throw exception if no links are found.
            default:
                throw new OkToolsDomItemNotFoundException("Couldn't Grooup exit/join links - $this->groupId", $groupPageDom->outertext);
        }
    }

    /**
     * Init Group front page.
     *
     * @throws OkToolsBlockedGroupException
     *   Thrown if group is blocked.
     * @throws OkToolsResponseException
     *   Thrown if request error and it is not 404.
     * @throws OkToolsDomItemNotFoundException
     *   Thrown when Disabled marker not found.
     * @throws OkToolsPageNotFoundException
     *   Thrown when group page not found.
     *
     * @return  string
     *   Group page html.
     */
    protected function initGroupPage()
    {
        // Make a desktop request to group page.
        try {
            $groupPage = $this->OkToolsClient->attendPage(self::GROUP_BASE_URL . "/$this->groupId", true);
        } catch (OkToolsResponseException $ex) {
            // If responded with NOT FOUND throw not found exception.
            if ($ex->responseCode == RequestersHttpCodesEnum::HTTP_NOT_FOUND) {
                throw new OkToolsPageNotFoundException("Couldn't find the group", $this->groupId, $ex->html);
            } // If another code throw request exception to up level.
            else {
                throw $ex;
            }
        }

        // Wrap page to dom and Check for group disabled marker.
        $groupPageDom = str_get_html($groupPage);
        $disabledMarker = $groupPageDom->find("#hook_Block_DisabledGroupTeaserBlock", 0);

        // Throw exception if there is no disable item is found.
        if (!$disabledMarker) {
            throw new OkToolsDomItemNotFoundException("Couldn't find disabled marker in the $this->groupId group", $groupPage);
        }

        // If disabled marker found and is not empty then group is blocked.
        if ($disabledMarker->innertext != null) {
            throw new OkToolsBlockedGroupException("Group is disabled.", $this->groupId, $groupPage);
        }

        return $groupPage;
    }

    /**
     * Cancel group membership for account.
     *
     * @throws OkToolsDomItemNotFoundException
     *   If no exit link found.
     */
    public function leftTheGroup()
    {
        // Convert to dom & find exit button.
        $groupPageDom = str_get_html($this->groupFrontPage);
        $exitLink = $groupPageDom->find("a[href*='GroupJoinDropdownBlock&amp;st.jn.act=EXIT']", 0);

        // If no exit link is exists.
        if (!$exitLink) {
            throw new OkToolsDomItemNotFoundException("Couldn't find exit link in group - $this->groupId", $groupPageDom->outertext);
        }

        // Set up url and send request.
        $jsUrl = $exitLink->href . "&" . $this->getAjaxRequestUrlParams();
        $this->OkToolsClient->sendForm($jsUrl, [], true);
  
        // Reinit group page.
        $this->groupFrontPage = $this->initGroupPage();
    }

    /**
     * Get ajax requred url parameters.
     *
     * @param boolean $noPsid
     *   True if no p_sid should be returned.
     *
     * @return string
     *   Url paramters as string to append to url.
     */
    protected function getAjaxRequestUrlParams($noPsid = false)
    {
        // Get parameters for request.
        $gwtHash = $this->OkToolsClient->getGwtDesktopHash();

        // Set up URL & Send request.
        $params =  [
            "st.vpl.mini" => "false",
            "gwt.requested" => $gwtHash,
        ];
        
        // If paramters requested without p_sid.
        if (!$noPsid) {
            $periodicManagerData = $this->OkToolsClient->getPeriodicManagerData();
            $params["p_sId"] = $periodicManagerData['p_sId'];
        }
        
        return http_build_query($params);
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
        // Retrieve popup.
        $this->invitePopup = $this->retrieveMemberPopup(
            $userId,
            $this->getInviteRequestParameters($userId)
        );
        
        // Convert to dom.
        $popupDom = str_get_html($this->invitePopup);
        
        // Check if user can be invited according to returned in popup.
        if ($popupDom->plaintext == "Пользователь не принимает приглашения в группы") {
            return false;
        } elseif ($popupDom->find("#hook_Modal_popLayerModal", 0)) {
            return true;
        } else {
            throw new OkToolsDomItemNotFoundException("Couldn't find popup to check if user $userId can be invited from $this->groupId group", $popup);
        }
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
        // Retrieve members popup.
        if (!$this->invitePopup) {
            $inviteCanvas = $this->retrieveMemberPopup(
                $userId,
                $this->getInviteRequestParameters($userId)
            );
        } else {
            $inviteCanvas = $this->invitePopup;
        }

        $inviteCanvasDom = str_get_html($inviteCanvas)->find(".portlet_b", 0);

        // Throw if no invite canvas is found.
        if (!$inviteCanvasDom) {
            throw new OkToolsDomItemNotFoundException("Couldn't find invite popup canvas - $this->groupId", "");
        }

        // Check if user is already invited.
        $groupItem = $inviteCanvasDom->find("a[href*=$groupId]", 0);
        if (!$groupItem) {
            throw new OkToolsInviteGroupNotFoundException("Couldn't find group $groupId to invite from $this->groupId", $inviteCanvas);
        }

        // Construct url.
        $url = ltrim($groupItem->href, "/");
        $url .=  "&" . $this->getDeviceSizeParams() . "&" . $this->getAjaxRequestUrlParams();
        
        // Send request.
        $inviteResult = $this->OkToolsClient->sendForm($url, [], true);
        
        // Check invite result.
        $inviteResultDom = str_get_html($inviteResult);
        if (!$inviteResultDom->find(".tip_cnt", 0)) {
            throw new OkToolsInviteFailedException("Couldn't find appropriate response for user invite", $inviteResult);
        }
        
        // Clear invite popup.
        $this->invitePopup = null;
    }
//
//    public function isInvited($userId)
//    {
//    }      
    
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
        // Get popup and retrieve form from it.
        $assignRolePopup = $this->retrieveMemberPopup(
            $userId,
            $this->getAssignModeratorRequestParameters($userId)
        );
        $form = str_get_html($assignRolePopup)->find("form", 0);

        // THrow if no moderator assign form is found.
        if (!$form) {
            throw new OkToolsDomItemNotFoundException("Couldn't find assign role form - $this->groupId", "");
        }

        // Pack post parameters.
        $postData = [
            "gwt.requested" => $this->OkToolsClient->getGwtDesktopHash(),
            "st.layer.posted" => "set",
            "st.layer.index" => (string)$role->getValue(),
            "button_grant" => "clickOverGWT"
        ];

        // Construct url and send request.
        $url = ltrim($form->action, "/") . "&" . $this->getAjaxRequestUrlParams();
        $this->OkToolsClient->sendForm($url, $postData, true);
    }

    /**
     * Get popup for user.
     *
     * @param int $userId
     *   User id to retrieve popup for.
     *
     * @param array $params
     *   Parameters to set for request.
     *
     * @throws OkToolsDomItemNotFoundException
     *   Thrown if no html has been receieved.
     */
    protected function retrieveMemberPopup($userId, $params)
    {
        // Construct url.
        $url = self::GROUP_BASE_URL . "/" . $this->groupId . "/" . self::GROUP_MEMBERS_BASE_URL;
        $url .= "?" . http_build_query($params) . "&" . $this->getAjaxRequestUrlParams();
        $url .= "&" . $this->getDeviceSizeParams();

        // Retrieve popup layer.
        $popup = $this->OkToolsClient->sendForm($url, [], true);

        // Throw if no popup is recieved.
        if (!$popup) {
            throw new OkToolsDomItemNotFoundException("Couldn't retrieve popup - $this->groupId", "");
        }

        // Log popup action.
        $this->logPopupAction();

        return $popup;
    }

    /**
     * Returns parameters for invite popup request.
     *
     * @param int $userId
     *   User id to get parameters for.
     *
     * @return array
     *   Return invite popup request paramerers.
     */
    protected function getInviteRequestParameters($userId)
    {
        // Parameters for assign role poplayer.
        $params = [
            "cmd" => "PopLayer",
            "st.cmd" => "altGroupMembers",
            "st.groupId" => $this->groupId,
            "st.layer.cmd" => "InviteUserToGroup2",
            "st.layer.friendId" => $userId,
            "st._aid" => "SM_AltGroup_Invite"
        ];
        return $params;
    }
    
    /**
     * Returns parameters fpr Assign moderator popup request.
     *
     * @param invite $userId
     *   User id to get parameters for.
     *
     * @return array
     *   Return Moderator assign popup request paramerers.
     */
    protected function getAssignModeratorRequestParameters($userId)
    {
        // Parameters for assign role poplayer.
        $params = [
            "cmd" => "PopLayer",
            "st.cmd" => "altGroupMembers",
            "st.groupId" => $this->groupId,
            "st.layer.cmd" => "PopLayerGrantAltGroupModerators",
            "st.layer.groupId" => $this->groupId,
            "st.layer.id" => $userId,
            "st._aid" => "SM_Group_MakeModerator"
        ];
        return $params;
    }
    
    /**
     * Return device size params.
     *
     * @return string
     *   Query params for device size.
     */
    protected function getDeviceSizeParams()
    {
        return http_build_query([
            "st.layer._bw" => rand(1300, 1905),
            "st.layer._bh" => rand(300, 600)
        ]);
    }

    /**
     * Log popup actions.
     */
    protected function logPopupAction()
    {
        // Close popup, open popup, shortcut menu open.
        $data = [
            'layerManager' => [
                'unregister' => [
                    'modal_hook' => 1,
                ],
                'register' => [
                    'modal_hook' => 1,
                ],
            ],
            'feed' => [
                'shortcutMenu' => [
                    'second' => rand(3, 11),
                ],
            ],
        ];

        // Send log request.
        $this->OkToolsClient->gwtLog($data);
    }
}
