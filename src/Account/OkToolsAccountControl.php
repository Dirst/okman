<?php

namespace Dirst\OkTools\Account;

use Dirst\OkTools\OkToolsBaseControl;
use Dirst\OkTools\Exceptions\OkToolsNotFoundException;
use Dirst\OkTools\Requesters\RequestersTypesEnum;
use Dirst\OkTools\OkToolsClient;

/**
 * OK Account control class.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkToolsAccountControl extends OkToolsBaseControl
{
    protected $navigationPage;
    
    /**
     * {@inheridoc}
     */
    public function __construct(OkToolsClient $okTools) {
      parent::__construct($okTools);
      
      // Save front page.
      $this->navigationPage = $this->OkToolsClient->attendPage(null);
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
        $accountPage = $this->seeAreaPage(OkToolsAccountAreaEnum::SETTINGS());
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
     * Attend area page.
     *
     * @param OkToolsAccountAreaEnum $area
     *   Area enum.
     *
     * @return string
     *   Area html page string.
     */
    public function seeAreaPage(OkToolsAccountAreaEnum $area)
    {
        $areaUrl = $this->retrieveAreaUrl($area);
        $this->navigationPage = $this->OkToolsClient->attendPage($areaUrl);
    }

    /**
     * Retrieve area url. Last page should be mobile.
     *
     * @param OkToolsAccountAreaEnum $area
     *   Area enum.
     *
     * @throws OkToolsNotFoundException
     *   Thrown when couldn't find link for area.
     *
     * @return string
     *   Area page url withoul left slash.
     */
    private function retrieveAreaUrl(OkToolsAccountAreaEnum $area)
    {
        $lastPageDom = str_get_html($this->navigationPage);
        if ($areaLink = $lastPageDom->find("a[aria-label={$area->getValue()}]", 0)) {
            return ltrim($areaLink->href, '/');
        } else {
            throw new OkToolsNotFoundException("Couldn't find area link: {$area->getValue()}", $lastPageDom->outertext);
        }
    }

    /**
     * Check all notifications.
     * - Accept friendship.
     * - Accept gifts.
     * - Close other notifications.
     */
    public function checkAllNotifications()
    {
        // Submit notifications.
        $event = true;
        // @TODO prevent loop to be inifinte.
        while ($event) {
            $eventsPage = $this->seeAreaPage(OkToolsAccountAreaEnum::NOTIFICATIONS());
            $html = str_get_html($eventsPage);
            $event = $html->find("#events-list", 0);

            if ($event && $event = $event->find("li.notify", 0)) {
                $this->checkNotification($event);
            }
        }
    }

    /**
     * Check one notification.
     *
     * @param simple_html_dom_node $event
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
        
        //  If post data is empty - means no form has been found.
        if (empty($postData)) {
            throw new OkToolsNotFoundException(
                "No form has been found on check Notification action.",
                $event->outertext
            );
        }
        
        // Check event type. All notifications not in list - just close.
        $eventType = $event->{"data-type"};
        if (in_array($eventType, $this->getNotificationTypes())) {
            $buttonPos = 0;
        } else {
            $buttonPos = 1;
        }

        // Button clicked.
        $postData[$event->find(".base-button_target", $buttonPos)->name] =
            $event->find(".base-button_target", $buttonPos)->value;

        // Send request for notification close or accept.
        $requestUrl = ltrim($event->find("form", 0)->action, "/");
        $this->OkToolsClient->sendForm($requestUrl, $postData);
    }

    /**
     * Get notification types.
     *
     * @return array
     *   Notification types array.
     */
    private function getNotificationTypes()
    {
        return [
          'FriendshipWithRelationsRequest',
          'Present',
          'GroupNotificationFromAdmin',
          'GroupUserInvitationDecision'
        ];
    }

    /**
     * Construct New object with new OktoolsClient inside.
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
     * @return OkToolsBaseControl
     *   Control object with Client initialized inside.
     */
    public static function initWithClient(
        $login,
        $pass,
        RequestersTypesEnum $requesterType,
        $proxy = null,
        $requestPauseSec = 1
    ) {
        $okToolsClient = new OkToolsClient($login, $pass, $requesterType, $proxy, $requestPauseSec);
        return new static($okToolsClient);
    }
}
