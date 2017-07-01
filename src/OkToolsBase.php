<?php

namespace Dirst\OkTools;

/**
 * Class for all ok tools.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 * 
 * @TODO errors and exceptions. Remove Code doubling. Code refactoring.
 *   Methods for actual check of the results of current methods.
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

        $this->requestBehaviour->requestPost(self::URL . OkPagesEnum::LOGIN_PATH, $postData);
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
        $this->requestBehaviour->requestPost(self::URL . OkPagesEnum::LOGOUT_PATH, $postData);
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
        while ($event)
        {
            $eventsPage = $this->attendPage(OkPagesEnum::EVENTS);
            $html = str_get_html($eventsPage);
            $event = $html->find("#events-list", 0);
            if ($event) 
            {
                $event = $event->find("li.notify", 0);
                $this->checkNotification($event);
            }
            
            // Clear memory. Delay.
            $html->clear();
            usleep($delaySeconds * 1000000);
        }
    }

    /**
     * Check one notification.
     *
     * @param HtmlDomNode $event
     *   Node of the DOM. li item of the event.
     */
    private function checkNotification(&$event)
    {
        // Post data collect.
        $postData = [];
        foreach($event->find(".notify-actions", 0)->find("input[type=hidden]") as $parameter)
        {
            $postData[$parameter->name] = $parameter->value ? $parameter->value : "" ;
        }

        // Check event type. Friendship and Gift - Yes, others - Close.
        $eventType = $event->{"data-type"};
        if (in_array($eventType, ['FriendshipWithRelationsRequest', 'Present']))
        {
            $buttonPos = 0;
        } 
        else 
        {
            $buttonPos = 1;
        }

        // Button clicked.
        $postData[$event->find(".base-button_target", $buttonPos)->name] = $event->find(".base-button_target", $buttonPos)->value;

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
     * @return boolean
     *   True if role has been assigned.
     * 
     * @TODO decouple http codes in Enum class. Check if user has role.
     */
    public function assignGroupRole(OkGroupRoleEnum $role, $uid, $groupId)
    {
        // Replace placeholders with actual values.
        $moderAssignUrl = str_replace(["GROUPID", "USERID", "RETURNPAGE"], [$groupId, $uid, self::URL . OkPagesEnum::EVENTS], OkPagesEnum::MODER_ASSIGN_PAGE);

        // Go to moderation control.
        $moderFormPage = str_get_html($this->attendPage($moderAssignUrl));
        $form = $moderFormPage->find(".uform form", 0);
        
        // Check if form shows up on the page.
        if (!$form)
        {
            $moderFormPage->clear();
            return false;
        }

        // Send request to assign user role i a group.
        $postData = [
          "fr.posted" => "set",
          "fr.ri" => $role->getValue(),
          "button_save" => "Сохранить"
        ];
        $this->requestBehaviour->requestPost(self::URL . ltrim($form->action, "/"), $postData);
        
        // If code 200 then all went ok - return true, if no return false.
        $moderFormPage->clear();
        return $this->checkResponseCode();
    }

    /**
     * Get all users of the OK group.
     *
     * @param int $groupId
     *   Id of an OK group.
     * @param int $page
     *   Pager position where users will be getted.
     *
     * @return mixed
     *   User data array or false if no users has been found.
     *
     * @TODO define user properties to return.
     */
    public function getGroupUsers($groupId, $page = 1)
    {
        // Replace placeholders with actual values.
        $membersPageUrl = str_replace(["GROUPID", "PAGENUMBER"], [$groupId, $page], OkPagesEnum::GROUP_MEMBERS);

        // Get page with members list.
        $membersPage = str_get_html($this->attendPage($membersPageUrl));

        // Get all members block.
        $membersList = $membersPage->find("#member-list", 0);

        // If no members on the page return false.
        if (!$membersList) 
        {
            $membersPage->clear();
            return false;
        }

        // Collect all members information.
        $usersArray = [];
        foreach ($membersList->find("li.item") as $oneMember)
        {
            // Get Id of a user.
            $out = null;
            preg_match("/friendId=(\d+)/", $oneMember->find("a.clickarea", 0)->href, $out);
            $usersArray[] = 
                [
                    "id" => $out[1],
                    "name" => $oneMember->find("span.emphased", 0)->plaintext
                ];
        }

        $membersPage->clear();
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
     * @return boolean
     *   True if invite has been send.
     */
    public function inviteUserToGroup($uid, $groupId)
    {
        // If user invited to group.
        if ($this->isInvited($uid, $groupId))
        {
            return false;
        }

        // Replace placeholders with actual values.
        $invitePageUrl = str_replace(["GROUPID", "USERID", "RETURNPAGE"], [$groupId, $uid, self::URL . OkPagesEnum::EVENTS], OkPagesEnum::INVITE_TO_GROUP_PAGE);

        // Get page with invite form.
        $inviteFormPage = $this->attendPage($invitePageUrl);
        $inviteFormPage = str_get_html($inviteFormPage);
        $inviteForm = $inviteFormPage->find(".uform form", 0);

        // If no members on the page return false.
        if (!$inviteForm) 
        {
            $inviteFormPage->clear();
            return false;
        }

        // Send request to invite user.
        $postData = [
          "fr.posted" => "set",
          "button_send" => "Отправить"
        ];
        $this->requestBehaviour->requestPost(self::URL . ltrim($inviteForm->action, "/"), $postData);

        // Clear memory.
        $inviteFormPage->clear();

        // Check if user invited.
        return $this->isInvited($uid, $groupId);
    }

    /**
     * Check if user already invited.
     *
     * @param int $uid
     *   Id of the user in OK.
     * @param int groupId
     *   Id of the group in OK.
     * 
     * @return boolean
     *   True if user already invited.
     */
    public function isInvited($uid, $groupId)
    {    
        $groupsList = true;
        $page = 1;
        while ($groupsList)
        {
            // Replace placeholders with actual values.
            $inviteGroupsUrl = str_replace(["USERID", "PAGENUMBER"], [$uid, $page], OkPagesEnum::INVITE_LIST_PAGE);
            $inviteGroupPage = str_get_html($this->attendPage($inviteGroupsUrl));

            // If group items exists.
            if (($groupsList = $inviteGroupPage->find("li.item", 0)) & ($groupRow = $inviteGroupPage->find("li#invite-id_{$uid}_{$groupId}", 0))) 
            {
                // Check if group disabled.
                if (strpos($groupRow->find("a", 0)->class, "__disabled") !== false)
                {
                    return true;
                }
            }
            
            $page++;
            $inviteGroupPage->clear();
        }

        return false;
    }

    /**
     * Check last response code.
     * 
     * @return boolean
     *   True if code == 200
     */
    private function checkResponseCode()
    {
        if ($this->requestBehaviour->getResponseCode() == 200)
        {
            return true;
        } 
        else
        {
            return false;
        }
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
     * 
     * @return string
     *   Html result.
     */
    public function attendPage($pageUrl)
    {
        return $this->requestBehaviour->requestGet(self::URL . $pageUrl);
    }
}
