<?php

namespace Dirst\OkTools;

use Dirst\OkTools\Exceptions\OkToolsException;
use Dirst\OkTools\Exceptions\OkToolsNotFoundException;
use Dirst\OkTools\Exceptions\OkToolsBlockedGroupException;
use Dirst\OkTools\Exceptions\OkToolsBlockedUserException;
use Dirst\OkTools\Exceptions\OkToolsNotPermittedException;
use Dirst\OkTools\Exceptions\OkToolsCaptchaAppearsException;

/**
 * Class for all ok tools.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkToolsBase
{
    const URL = "https://m.ok.ru/";
    
    // @var requestInterface object.
    private $requestBehaviour;

    // @var string.
    private $login;
    
    /**
     * OkTookls Constructor.
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
     *
     * @throws OkToolsBlockedUserException
     *   Will be thrown on User blocked/frozen marker and if response is not successfull.
     * @throws OkToolsNotFoundException
     *   Will be thrown if no user marker found.
     *
     * @return string
     *   Html page after login.
     */
    public function login($login, $pass)
    {
        $postData = [
            'fr.login' => $login,
            'fr.password' => $pass,
            'fr.posted' => 'set',
            'fr.proto' => 1
        ];

        $loggedInPage = $this->requestBehaviour->requestPost(self::URL . OkPagesEnum::LOGIN_PATH, $postData);

        // Check if user Frozen/Blocked.
        $loggedIn = str_get_html($loggedInPage);
        $box = $loggedIn->find("#boxPage", 0);
        if ($box) {
            $status = $box->{"data-logloc"};
            switch ($status) {
                case OkBlockedStatusEnum::USER_BLOCKED:
                    throw new OkToolsBlockedUserException("User has been blocked forever.", $login, $loggedIn->outertext);
                  break;
                case OkBlockedStatusEnum::USER_VERIFICATION:
                case OkBlockedStatusEnum::USER_FROZEN:
                    throw new OkToolsBlockedUserException("User has been frozen status = {$status}", $login, $loggedIn->outertext);
                  break;
                case "userMain":
                    // Set login and return html page.
                    $this->login = $login;
                    return $loggedInPage;
                case "main":
                    throw new OkToolsNotFoundException("User couldn't login.", $loggedIn->outertext);
            }
        } else {
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
        $this->requestBehaviour->requestPost(self::URL . OkPagesEnum::LOGOUT_PATH, $postData);
    }

    /**
     * Get current account ID.
     *
     * @throws OkToolsNotFoundException
     *   Thrown if no block with profile id has been found.
     *
     * @return string
     */
    public function getAccountId()
    {
        $accountPage = $this->attendPage(OkPagesEnum::ACCOUNT_SETTINGS);
        $accountPageDom = str_get_html($accountPage);
        
        $stamp = $accountPageDom->find(".stamp", 0);
        
        // Check if block with profile ID exists.
        if (!$stamp) {
            throw new OkToolsNotFoundException(
                "No profile ID block has been found.",
                $accountPageDom->outertext
            );
        }

        // Return digits only from block.
        return preg_replace("/[^\d]+/", "", $stamp->innertext);
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
     * Check all notifications.
     * - Accept friendship.
     * - Accept gifts.
     * - Close other notifications.
     *
     * @param float $delaySeconds
     *   Delay after one notification send.
     */
    public function checkAllNotifications($delaySeconds = 0)
    {
        // Check news.
        $this->attendPage(OkPagesEnum::NEWS_PATH);

        // Check guests.
        $this->attendPage(OkPagesEnum::GUESTS_PATH);
        
        // Submit notifications.
        $event = true;
        // @TODO prevent loop to be inifinte.
        while ($event) {
            $eventsPage = $this->attendPage(OkPagesEnum::EVENTS);
            $html = str_get_html($eventsPage);
            $event = $html->find("#events-list", 0);

            if ($event && $event = $event->find("li.notify", 0)) {
                $this->checkNotification($event);
            }
            
            // Delay.
            usleep($delaySeconds * 1000000);
        }
    }

    /**
     * Check one notification.
     *
     * @param HtmlDomNode $event
     *   Node of the DOM. li item of the event.
     *
     * @throws OkToolsNotFoundException
     *   Thrown if can't collect post data.
     */
    private function checkNotification(&$event)
    {
        // Post data collect.
        $postData = [];
        foreach ($event->find(".notify-actions", 0)->find("input[type=hidden]") as $parameter) {
            $postData[$parameter->name] = $parameter->value ? $parameter->value : "" ;
        }
        
        if (empty($postData)) {
            throw new OkToolsNotFoundException(
                "No form has been found on check Notification action.",
                $event->outertext
            );
        }
        
        // Check event type. Friendship and Gift - Yes, others - Close.
        $eventType = $event->{"data-type"};
        if (in_array($eventType, ['FriendshipWithRelationsRequest', 'Present', 'GroupNotificationFromAdmin', 'GroupUserInvitationDecision'])) {
            $buttonPos = 0;
        } else {
            $buttonPos = 1;
        }

        // Button clicked.
        $postData[$event->find(".base-button_target", $buttonPos)->name] =
            $event->find(".base-button_target", $buttonPos)->value;

        // Send request for notification close or accept.
        $requestUrl = ltrim($event->find("form", 0)->action, "/");
        $this->requestBehaviour->requestPost(self::URL . $requestUrl, $postData);
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
    public function assignGroupRole(OkGroupRoleEnum $role, $uid, $groupId)
    {
        // Check if group is available.
        if (!$this->isGroupAvailable($groupId)) {
            throw new OkToolsBlockedGroupException("Group {$groupId} is not available", $groupId);
        }

        // Replace placeholders with actual values.
        $moderAssignUrl = str_replace(
            ["GROUPID", "USERID", "RETURNPAGE"],
            [$groupId, $uid, self::URL . OkPagesEnum::EVENTS],
            OkPagesEnum::MODER_ASSIGN_PAGE
        );

        // Go to moderation control.
        $moderFormPage = str_get_html($this->attendPage($moderAssignUrl));
        $form = $moderFormPage->find(".uform form", 0);
        
        // Check if form shows up on the page.
        if (!$form) {
            throw new OkToolsNotFoundException(
                "No moderation form has been found on assign group Role.",
                $moderFormPage->outertext
            );
        }

        // Send request to assign user role i a group.
        $postData = [
          "fr.posted" => "set",
          "fr.ri" => $role->getValue(),
          "button_save" => "Сохранить"
        ];
        $this->requestBehaviour->requestPost(self::URL . ltrim($form->action, "/"), $postData);

        // Check if user has role.
        return $this->userHasRole($role, $uid, $groupId);
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
     * @return boolean
     *   True if role has been assigned.
     */
    public function userHasRole(OkGroupRoleEnum $role, $uid, $groupId)
    {
        return true;
    }

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
    public function getGroupUsers($groupId, $page = 1)
    {
        // Check if group is available.
        if (!$this->isGroupAvailable($groupId)) {
            throw new OkToolsBlockedGroupException("Group {$groupId} is not available", $groupId);
        }

        // Replace placeholders with actual values.
        $membersPageUrl = str_replace(["GROUPID", "PAGENUMBER"], [$groupId, $page], OkPagesEnum::GROUP_MEMBERS);

        // Get page with members list.
        $membersPage = str_get_html($this->attendPage($membersPageUrl));

        // Get all members block.
        $membersList = $membersPage->find("#member-list", 0);

        // If no members on the page - return empty array.
        if (!$membersList) {
            return [];
        }

        // Collect all members information.
        $usersArray = [];
        foreach ($membersList->find("li.item") as $oneMember) {
        // Get Id of a user.
            $out = null;
            preg_match("/friendId=(\d+)/", $oneMember->find("a.clickarea", 0)->href, $out);
            $usersArray[] = [
                    "id" => $out[1],
                    "name" => $oneMember->find("span.emphased", 0)->plaintext,
                    "online" => $oneMember->find("span.ic_w", 0) ? true : false
                ];
        }

        // Return users array.
        return $usersArray;
    }

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
    public function inviteUserToGroup($uid, $groupId)
    {
        // Check if user can be invited.
        if (!$this->canBeInvited($uid)) {
            throw new OkToolsNotPermittedException("User didn't grant permission to invite him.");
        }
        
        // Check if group is available.
        if (!$this->isGroupAvailable($groupId)) {
            throw new OkToolsBlockedGroupException("Group {$groupId} is not available", $groupId);
        }

        // Replace placeholders with actual values.
        $invitePageUrl = str_replace(
            ["GROUPID", "USERID", "RETURNPAGE"],
            [$groupId, $uid, self::URL . OkPagesEnum::EVENTS],
            OkPagesEnum::INVITE_TO_GROUP_PAGE
        );

        // Get page with invite form.
        $inviteFormPage = $this->attendPage($invitePageUrl);
        $inviteFormPage = str_get_html($inviteFormPage);
        $inviteForm = $inviteFormPage->find(".uform form", 0);

        // If no form on a page throw an exception.
        if (!$inviteForm) {
            throw new OkToolsNotFoundException(
                "User invite form doesn't exist on the page.",
                $inviteFormPage->outertext
            );
        }

        // Send request to invite user.
        $postData = [
          "fr.posted" => "set",
          "button_send" => "Отправить"
        ];
        $this->requestBehaviour->requestPost(self::URL . ltrim($inviteForm->action, "/"), $postData);

        // Check if user has been invited.
        return $this->isInvited($uid, $groupId);
    }
    
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
    public function canBeInvited($uid)
    {
        // Invite groups url.
        $inviteGroupsUrl = str_replace(["USERID", "PAGENUMBER"], [$uid, 1], OkPagesEnum::INVITE_LIST_PAGE);
        $inviteGroupsPage = str_get_html($this->attendPage($inviteGroupsUrl));
        
        if ($inviteGroupsPage->find("#boxPage > .dlist_top", 0)) {
            if ($inviteGroupsPage->find("#groups-list", 0)) {
                return true;
            } else {
                return false;
            }
        } else {
            throw new OkToolsNotFoundException(
                "Something went wrong on 'can be invited' checking. Can't find page marker.",
                $inviteGroupPage->outertext
            );
        }
    }

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
    public function isInvited($uid, $groupId)
    {
        // Check if group is available.
        if (!$this->isGroupAvailable($groupId)) {
            throw new OkToolsBlockedGroupException("Group {$groupId} is not available", $groupId);
        }

        $groupsList = true;
        $page = 1;
        while ($groupsList) {
            // Replace placeholders with actual values.
            $inviteGroupsUrl = str_replace(["USERID", "PAGENUMBER"], [$uid, $page], OkPagesEnum::INVITE_LIST_PAGE);
            $inviteGroupsPage = str_get_html($this->attendPage($inviteGroupsUrl));

            // If group items exists.
            if (($groupsList = $inviteGroupsPage->find("li.item", 0)) &&
                ($groupRow = $inviteGroupsPage->find("li#invite-id_{$uid}_{$groupId}", 0))) {
                // Check if group disabled.
                if (strpos($groupRow->find("a", 0)->class, "__disabled") !== false) {
                    return true;
                } else {
                    return false;
                }
            } elseif ($page == 1) {
                throw new OkToolsNotFoundException(
                    "Can't find groups list. Maybe user didn't grant permissions to invite himself",
                    $inviteGroupsPage->outertext
                );
            } else {
                throw new OkToolsNotFoundException(
                    "Group has not been found. Maybe account is not joined to group.",
                    $inviteGroupsPage->outertext
                );
            }
            
            $page++;
        }

        return false;
    }

    /**
     * Check if group is available and is not blocked.
     *
     * @param int $groupId
     *   Id of checked group.
     *
     * @return boolean
     *   Return true if group is not blocked.
     */
    public function isGroupAvailable($groupId)
    {
        $groupUrl = str_replace("GROUPID", $groupId, OkPagesEnum::GROUP_PAGE);

        // Check if group Blocked.
        $group = $this->attendPage($groupUrl);
        $group = str_get_html($group);
        
        if ($group->find("." . OkBlockedStatusEnum::GROUP_BLOCKED_CLASS, 0) ||
            $group->find("." . OkBlockedStatusEnum::ERROR_PAGE_CLASS, 0)) {
            return false;
        } else {
            return true;
        }
    }
    
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
    public function joinTheGroup($groupId)
    {    
        // Check if group is available.
        if (!$this->isGroupAvailable($groupId)) {
            throw new OkToolsBlockedGroupException("Group {$groupId} is not available", $groupId);
        }
 
        $joinForm = $this->joinGroupGetForm($groupId);
        
        // Send request to invite user.
        $postData = [
          "fr.posted" => "set",
          "button_join" => "Присоединиться"
        ];
        $this->requestBehaviour->requestPost(self::URL . ltrim($joinForm->action, "/"), $postData);
        
        return $this->isJoinedToGroup($groupId);
    }
    
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
    public function isJoinedToGroup($groupId)
    {
        // Check if group is available.
        if (!$this->isGroupAvailable($groupId)) {
            throw new OkToolsBlockedGroupException("Group {$groupId} is not available", $groupId);
        }

        $joinForm = $this->joinGroupGetForm($groupId);
        if ($joinForm->find(".tac", 0)) {
            return true;
        } else {
            return false;
        }
    }
    
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
    private function joinGroupGetForm($groupId)
    {
        $joinGroupUrl = str_replace("GROUPID", $groupId, OkPagesEnum::JOIN_GROUP_PAGE);
        $joinGroupPage = str_get_html($this->attendPage($joinGroupUrl));
        
        $joinForm = $joinGroupPage->find("form.confirm-form", 0);
        
         // If no form on a page throw an exception.
        if (!$joinForm) {
            throw new OkToolsNotFoundException(
                "No join form has been found.",
                $joinGroupPage->outertext
            );
        }
        
        return $joinForm;
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
     *   Relative url without slash.
     * @param int $minPauseSec
     *   Minimum pause before make a request.
     *
     * @throws OkToolsCaptchaAppearsException
     *   Thrown when captcha appeared and solved.
     *
     * @return string
     *   Html result.
     */
    public function attendPage($pageUrl, $minPauseSec = 3)
    {
        // Should always wait before next request to emulate human.
        sleep(rand(0, 3) + $minPauseSec);

        // Make a request.
        $page = $this->requestBehaviour->requestGet(self::URL . $pageUrl);
        
        // Wait after request.
        sleep(rand(0, 3) + $minPauseSec);
        
        $pageDom = str_get_html($page);
        
        // Check if Captcha
        if ($pageDom->find(".captcha_content", 0)) {
            throw new OkToolsCaptchaAppearsException("Captcha has appeared", $this->login, $pageDom->outertext);
        }

        return $page;
    }
}
