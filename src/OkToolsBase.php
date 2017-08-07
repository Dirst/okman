<?php

namespace Dirst\OkTools;

use Dirst\OkTools\Exceptions\OkToolsException;
use Dirst\OkTools\Exceptions\OkToolsNotFoundException;
use Dirst\OkTools\Exceptions\OkToolsBlockedGroupException;
use Dirst\OkTools\Exceptions\OkToolsBlockedUserException;
use Dirst\OkTools\Exceptions\OkToolsNotPermittedException;
use Dirst\OkTools\Exceptions\OkToolsCaptchaAppearsException;

use Dirst\OkTools\Requesters\RequestersFactory;
use Dirst\OkTools\Requesters\RequestersTypesEnum;

/**
 * Base class used with other Ok tools.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkToolsBase
{
    // Ok urls.
    const M_URL = "https://m.ok.ru/";
    const D_URL = "https://ok.ru/";

    // @var requestInterface object.
    private $requestBehaviour;

    // @var string.
    private $login;
    
    // @var string
    private $requestPauseSec;

    // @var string
    private $lastPage;

    /**
     * Construct OkToolsBase. Login to OK.RU. Define parameters.
     *
     * @param string $login
     *   User phone number.
     * @param string $pass
     *   Password.
     * @param string $proxy
     *   Proxy settings to use with request. type:ip:port:login:pass.
     *   Possible types are socks5, http.
     * @param int $requestPauseSec
     *   Pause before any request to emulate human behaviour.
     *
     * @throws OkToolsBlockedUserException
     *   Will be thrown on User blocked/frozen marker and if response is not successfull.
     * @throws OkToolsNotFoundException
     *   Will be thrown if no user marker found.
     *
     * @return string
     *   Html page after login.
     */
    public function __construct($login, $pass, RequestersTypesEnum $requesterType, $proxy = null, $requestPauseSec = 1)
    {
        // Create requester and define login and pause.
        $factory = new RequestersFactory();
        $this->requestBehaviour = $factory->createRequester($proxy, $requesterType);

        $this->requestPauseSec = $requestPauseSec;
        $this->login = $login;

        // User authorization.
        $postData = [
            'fr.login' => $login,
            'fr.password' => $pass,
            'fr.posted' => 'set',
            'fr.proto' => 1
        ];

        // Make login attempt.
        $loggedInPage = $this->sendForm(OkPagesEnum::LOGIN_PATH, $postData);

        // Check if user Frozen/Blocked.
        $loggedIn = str_get_html($loggedInPage);
        $box = $loggedIn->find("#boxPage", 0);
        if ($box) {
            $status = $box->{"data-logloc"};
            switch ($status) {
                // User Blocked.
                case OkBlockedStatusEnum::USER_BLOCKED:
                    throw new OkToolsBlockedUserException("User has been blocked forever.", $login, $loggedIn->outertext);
                  break;
                // User Frozen.
                case OkBlockedStatusEnum::USER_VERIFICATION:
                case OkBlockedStatusEnum::USER_FROZEN:
                    throw new OkToolsBlockedUserException("User has been frozen status = {$status}", $login, $loggedIn->outertext);
                  break;
                // User is authorized.
                case "userMain":
                    // Return html page.
                    return $loggedInPage;
                // Couldn't login.
                case "main":
                    throw new OkToolsNotFoundException("User couldn't login.", $loggedIn->outertext);
            }
        } else {
            // Some unexpected result.
            throw new OkToolsNotFoundException("Can't find user logged in marker.", $loggedIn->outertext);
        }
    }

    /**
     * Logout from OK.RU.
     */
    public function logout()
    {
        $postData = [
            'fr.posted' => 'set',
            'button_logoff' => 'Выйти'
        ];
        $this->sendForm(OkPagesEnum::LOGOUT_PATH, $postData);
    }

    /**
     * Last attended page getter.
     *
     * @return string
     *   Last page html string.
     */
    public function getLastAttendedPage()
    {
        return $this->lastPage;
    }

    /**
     * Get request behaviour.
     *
     * @return RequestInterface
     *   Return Object that is used to send requests.
     */
    public function getRequestBehaviour() {
        return $this->requestBehaviour;
    }

    /**
     * Returns account login. login() method should be called before.
     * 
     * @return string
     *   Account Login string. 
     */
    public function getAccountLogin() {
        return $this->login;
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
     *
     * @throws OkToolsNotFoundException
     *   Thrown if no form has been found.
     * @throws OkToolsBlockedGroupException
     *   Thrown if group is not available.
     *
     * @return boolean
     *   True if role has been assigned.
     */
//    public function assignGroupRole(OkGroupRoleEnum $role, $uid, $groupId)
//    {
//        // Check if group is available.
//        if (!$this->isGroupAvailable($groupId)) {
//            throw new OkToolsBlockedGroupException("Group {$groupId} is not available", $groupId);
//        }
//
//        // Replace placeholders with actual values.
//        $moderAssignUrl = str_replace(
//            ["GROUPID", "USERID", "RETURNPAGE"],
//            [$groupId, $uid, self::URL . OkPagesEnum::EVENTS],
//            OkPagesEnum::MODER_ASSIGN_PAGE
//        );
//
//        // Go to moderation control.
//        $moderFormPage = str_get_html($this->attendPage($moderAssignUrl));
//        $form = $moderFormPage->find(".uform form", 0);
//        
//        // Check if form shows up on the page.
//        if (!$form) {
//            throw new OkToolsNotFoundException(
//                "No moderation form has been found on assign group Role.",
//                $moderFormPage->outertext
//            );
//        }
//
//        // Send request to assign user role i a group.
//        $postData = [
//          "fr.posted" => "set",
//          "fr.ri" => $role->getValue(),
//          "button_save" => "Сохранить"
//        ];
//        $this->sendForm(ltrim($form->action, "/"), $postData);
//
//        // Check if user has role.
//        return $this->userHasRole($role, $uid, $groupId);
//    }
    
    /**
     * Assign group role to passed user id.
     *
     * @param OkGroupRoleEnum $role
     *   Enum group role object.
     * @param int $uid
     *   Id of the user in OK.
     * @param int groupId
     *   Id of the group in OK.
     *
     * @return boolean
     *   True if role has been assigned.
     */
//    public function userHasRole(OkGroupRoleEnum $role, $uid, $groupId)
//    {
//        return true;
//    }

    /**
     * Get all users of the OK group.
     *
     * @param int $groupId
     *   Id of an OK group.
     * @param int $page
     *   Pager position where users will be getted.
     *
     * @throws OkToolsBlockedGroupException
     *   Thrown if group is not available.
     *
     * @return array
     *   User data array or empty array if no users on a page.
     *
     * @TODO define user properties to return.
     */
//    public function getGroupUsers($groupId, $page = 1)
//    {
//        // Check if group is available.
//        if (!$this->isGroupAvailable($groupId)) {
//            throw new OkToolsBlockedGroupException("Group {$groupId} is not available", $groupId);
//        }
//
//        // Replace placeholders with actual values.
//        $membersPageUrl = str_replace(["GROUPID", "PAGENUMBER"], [$groupId, $page], OkPagesEnum::GROUP_MEMBERS);
//
//        // Get page with members list.
//        $membersPage = str_get_html($this->attendPage($membersPageUrl));
//
//        // Get all members block.
//        $membersList = $membersPage->find("#member-list", 0);
//
//        // If no members on the page - return empty array.
//        if (!$membersList) {
//            return [];
//        }
//
//        // Collect all members information.
//        $usersArray = [];
//        foreach ($membersList->find("li.item") as $oneMember) {
//        // Get Id of a user.
//            $out = null;
//            preg_match("/friendId=(\d+)/", $oneMember->find("a.clickarea", 0)->href, $out);
//            $usersArray[] = [
//                    "id" => $out[1],
//                    "name" => $oneMember->find("span.emphased", 0)->plaintext,
//                    "online" => $oneMember->find("span.ic_w", 0) ? true : false
//                ];
//        }
//
//        // Return users array.
//        return $usersArray;
//    }

    /**
     * Invite user to a group.
     *
     * @param int $uid
     *   User to invite to a group.
     * @param int $groupId
     *   Group Id to invite to.
     *
     * @throws OkToolsNotFoundException
     *   Thrown if no invite form has been found.
     * @throws OkToolsBlockedGroupException
     *   Thrown if group is not available.
     *
     * @return boolean
     *   True if invite has been send, False if not.
     */
//    public function inviteUserToGroup($uid, $groupId)
//    {
//        // Check if user can be invited.
//        if (!$this->canBeInvited($uid)) {
//            throw new OkToolsNotPermittedException("User didn't grant permission to invite him.");
//        }
//        
//        // Check if group is available.
//        if (!$this->isGroupAvailable($groupId)) {
//            throw new OkToolsBlockedGroupException("Group {$groupId} is not available", $groupId);
//        }
//
//        // Replace placeholders with actual values.
//        $invitePageUrl = str_replace(
//            ["GROUPID", "USERID", "RETURNPAGE"],
//            [$groupId, $uid, self::URL . OkPagesEnum::EVENTS],
//            OkPagesEnum::INVITE_TO_GROUP_PAGE
//        );
//
//        // Get page with invite form.
//        $inviteFormPage = $this->attendPage($invitePageUrl);
//        $inviteFormPage = str_get_html($inviteFormPage);
//        $inviteForm = $inviteFormPage->find(".uform form", 0);
//
//        // If no form on a page throw an exception.
//        if (!$inviteForm) {
//            throw new OkToolsNotFoundException(
//                "User invite form doesn't exist on the page.",
//                $inviteFormPage->outertext
//            );
//        }
//
//        // Send request to invite user.
//        $postData = [
//          "fr.posted" => "set",
//          "button_send" => "Отправить"
//        ];
//        $this->sendForm(ltrim($inviteForm->action, "/"), $postData);
//
//        // Check if user has been invited.
//        return $this->isInvited($uid, $groupId);
//    }
    
    /**
     * Check if user grant permission for invite or there are groups to invite him to.
     *
     * @param int $uid
     *
     * @throws OkToolsNotFoundException
     *   If no markers have been found.
     * 
     * @return boolean
     *   True if user can be invited. False if not
     */
//    public function canBeInvited($uid)
//    {
//        // Invite groups url.
//        $inviteGroupsUrl = str_replace(["USERID", "PAGENUMBER"], [$uid, 1], OkPagesEnum::INVITE_LIST_PAGE);
//        $inviteGroupsPage = str_get_html($this->attendPage($inviteGroupsUrl));
//        
//        if ($inviteGroupsPage->find("#boxPage > .dlist_top", 0)) {
//            if ($inviteGroupsPage->find("#groups-list", 0)) {
//                return true;
//            } else {
//                return false;
//            }
//        } else {
//            throw new OkToolsNotFoundException(
//                "Something went wrong on 'can be invited' checking. Can't find page marker.",
//                $inviteGroupPage->outertext
//            );
//        }
//    }

    /**
     * Check if user already invited to group.
     *
     * @param int $uid
     *   Id of the user in OK.
     * @param int groupId
     *   Id of the group in OK.
     *
     * @throws OkToolsNotFoundException
     *   If no groups has been found.
     * @throws OkToolsBlockedGroupException
     *   Thrown if group is not available.
     *
     * @return boolean
     *   True if user already invited.
     */
//    public function isInvited($uid, $groupId)
//    {
//        // Check if group is available.
//        if (!$this->isGroupAvailable($groupId)) {
//            throw new OkToolsBlockedGroupException("Group {$groupId} is not available", $groupId);
//        }
//
//        $groupsList = true;
//        $page = 1;
//        while ($groupsList) {
//            // Replace placeholders with actual values.
//            $inviteGroupsUrl = str_replace(["USERID", "PAGENUMBER"], [$uid, $page], OkPagesEnum::INVITE_LIST_PAGE);
//            $inviteGroupsPage = str_get_html($this->attendPage($inviteGroupsUrl));
//
//            // If group items exists.
//            if (($groupsList = $inviteGroupsPage->find("li.item", 0)) &&
//                ($groupRow = $inviteGroupsPage->find("li#invite-id_{$uid}_{$groupId}", 0))) {
//                // Check if group disabled.
//                if (strpos($groupRow->find("a", 0)->class, "__disabled") !== false) {
//                    return true;
//                } else {
//                    return false;
//                }
//            } elseif ($page == 1) {
//                throw new OkToolsNotFoundException(
//                    "Can't find groups list. Maybe user didn't grant permissions to invite himself",
//                    $inviteGroupsPage->outertext
//                );
//            } else {
//                throw new OkToolsNotFoundException(
//                    "Group has not been found. Maybe account is not joined to group.",
//                    $inviteGroupsPage->outertext
//                );
//            }
//            
//            $page++;
//        }
//
//        return false;
//    }

    /**
     * Check if group is available and is not blocked.
     *
     * @param int $groupId
     *   Id of checked group.
     *
     * @return boolean
     *   Return true if group is not blocked.
     */
//    public function isGroupAvailable($groupId)
//    {
//        $groupUrl = str_replace("GROUPID", $groupId, OkPagesEnum::GROUP_PAGE);
//
//        // Check if group Blocked.
//        $group = $this->attendPage($groupUrl);
//        $group = str_get_html($group);
//        
//        if ($group->find("." . OkBlockedStatusEnum::GROUP_BLOCKED_CLASS, 0) ||
//            $group->find("." . OkBlockedStatusEnum::ERROR_PAGE_CLASS, 0)) {
//            return false;
//        } else {
//            return true;
//        }
//    }
    
    /**
     * Join the group.
     *
     * @param int $groupId
     *   Id of the group to join.
     *
     * @throws OkToolsBlockedGroupException
     *   Thrown if group has not been found.
     * 
     * @return boolean
     *   If account is in the group.
     */
//    public function joinTheGroup($groupId)
//    {    
//        // Check if group is available.
//        if (!$this->isGroupAvailable($groupId)) {
//            throw new OkToolsBlockedGroupException("Group {$groupId} is not available", $groupId);
//        }
// 
//        $joinForm = $this->joinGroupGetForm($groupId);
//        
//        // Send request to invite user.
//        $postData = [
//          "fr.posted" => "set",
//          "button_join" => "Присоединиться"
//        ];
//        $this->sendForm(ltrim($joinForm->action, "/"), $postData);
//        
//        return $this->isJoinedToGroup($groupId);
//    }
    
    /**
     * Check if account already in a group.
     *
     * @param int $groupId
     *   Group Id.
     * 
     * @throws OkToolsBlockedGroupException
     *   Thrown if group has not been found.
     *
     * @return boolean
     *   True if account already in group.
     */
//    public function isJoinedToGroup($groupId)
//    {
//        // Check if group is available.
//        if (!$this->isGroupAvailable($groupId)) {
//            throw new OkToolsBlockedGroupException("Group {$groupId} is not available", $groupId);
//        }
//
//        $joinForm = $this->joinGroupGetForm($groupId);
//        if ($joinForm->find(".tac", 0)) {
//            return true;
//        } else {
//            return false;
//        }
//    }
    
    /**
     * Get join group form.
     *
     * @param int $groupId
     *   Group Id.
     *
     * @return simple_html_dom_node
     *   Form Dom object.
     *
     * @throws OkToolsNotFoundException
     *   Thrown if no form has been found.
     */
//    private function joinGroupGetForm($groupId)
//    {
//        $joinGroupUrl = str_replace("GROUPID", $groupId, OkPagesEnum::JOIN_GROUP_PAGE);
//        $joinGroupPage = str_get_html($this->attendPage($joinGroupUrl));
//        
//        $joinForm = $joinGroupPage->find("form.confirm-form", 0);
//        
//         // If no form on a page throw an exception.
//        if (!$joinForm) {
//            throw new OkToolsNotFoundException(
//                "No join form has been found.",
//                $joinGroupPage->outertext
//            );
//        }
//        
//        return $joinForm;
//    }

    /**
     * @TODO define content parsing/posting methods.
     */
//    public function getGroupContent();
//    public function postContentToGroup();

    /**
     * Requests a page with passed relative url.
     *
     * @param string $pageUrl.
     *   Relative url without slash.
     * @param boolean $desktop
     *   Use desktop base url or mobile flag.
     *
     * @throws OkToolsCaptchaAppearsException
     *   Thrown when captcha appeared and solved.
     *
     * @return string
     *   Html result.
     */
    public function attendPage($pageUrl, $desktop = false)
    {
        // Define url.
        $baseUrl = $desktop ? self::D_URL : self:: M_URL;

        // Should always wait before next request to emulate human. Delay 1-2 sec.
        $delay = ( rand(0, 100) / 100 ) + (float) $this->requestPauseSec;
        usleep($delay * 1000000);

        // Make a request And convert to DOM.
        $page = $this->requestBehaviour->requestGet($baseUrl . $pageUrl);
        $pageDom = str_get_html($page);
        
        // Check if Captcha
        if ($pageDom->find(".captcha_content", 0)) {
            throw new OkToolsCaptchaAppearsException("Captcha has appeared", $this->login, $pageDom->outertext);
        }

        // Save last page.
        $this->lastPage = $page;

        return $page;
    }

    /**
     * Sends post request.
     *
     * @param string $url
     *   Url to sned request to.
     * @param array $data
     *   Post data.
     * @param boolean $desktop
     *   Use desktop base url or mobile flag.
     *
     * @return string
     *   Html page.
     */
    public function sendForm($url, $data, $desktop = false) {
        // Define url.
        $baseUrl = $desktop ? self::D_URL : self:: M_URL;
      
        // Should always wait before next request to emulate human. Delay 1-2 sec.
        $delay = ( rand(0, 100) / 100 ) + (float) $this->requestPauseSec;
        usleep($delay * 1000000);
        
        // Send request and remember last page.
        $page =  $this->requestBehaviour->requestPost($baseUrl . $url, $data);
        $this->lastPage = $page;

        return $page;
    }
}
