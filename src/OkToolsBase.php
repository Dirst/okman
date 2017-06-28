<?php

namespace Dirst\OkTools;

/**
 * Base abstract class for all ok tools.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 * 
 * @TODO errors and exceptions.
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
     */
    public function checkAllNotifications($delaySeconds = 0)
    {

        // Check news.
        $this->attendPage(OkPagesEnum::NEWS_PATH);

        // Check guests.
        $this->attendPage(OkPagesEnum::GUESTS_PATH);
        
        // Check notifications.
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
     * @TODO decouple http codes in Enum class. 
     */
    public function assignGroupRole(OkGroupRoleEnum $role, $uid, $groupId)
    {
        // Replace placeholders with actual values.
        $moderAssignAddr = str_replace("GROUPID", $groupId, OkPagesEnum::MODER_ASSIGN_PAGE);
        $moderAssignAddr = str_replace("USERID", $uid, $moderAssignAddr);
        $moderAssignAddr = str_replace("RETURNPAGE", self::URL . OkPagesEnum::EVENTS, $moderAssignAddr);
        
        
        // Go to moderation control.
        $moderPage = $this->attendPage($moderAssignAddr);
        $moderForm = str_get_html($moderPage);
        $form = $moderForm->find(".uform form", 0);
        
        // Check if form shows up on the page.
        if (!$form)
        {
            return false;
        }

        // Send request to assign user role i a group.
        $postData = [
          "fr.posted" => "set",
          "fr.ri" => $role->getValue(),
          "button_save" => "Сохранить"
        ];

        $this->requestBehaviour->requestPost(self::URL . $form->action, $postData);
        
        // If code 200 then all went ok - return true, if no return false.
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
        $membersAddr = str_replace("GROUPID", $groupId, OkPagesEnum::GROUP_MEMBERS);
        $membersAddr = str_replace("PAGENUMBER", $page, $membersAddr);
   
        // Get page with members list.
        $members = $this->attendPage($membersAddr);
        $members = str_get_html($members);

        // Get all members block.
        $members = $members->find("#member-list", 0);

        // If no members on the page return false.
        if (!$members) 
        {
            return false;
        }

        // Collect all members information.
        $usersArray = [];
        foreach ($members->find("li.item") as $oneMember)
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

        return $usersArray;
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
