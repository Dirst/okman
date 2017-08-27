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
    protected $lastNavigated;
    
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
        
        $rows = $accountPageDom->find(".user-settings_i_tx");
        
        // Check if block with profile ID exists.
        if (!$rows) {
            throw new OkToolsNotFoundException(
                "No profile ID block has been found.",
                $accountPageDom->outertext
            );
        }

        $id = $rows[count($rows) - 1];

        // Return digits only from block.
        return $id->innertext;
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
        // Get area url.
//        $areaUrl = $this->retrieveAreaUrl($area);
        
        $areaUrl = $area->getValue();
//        $this->lastNavigated = urlencode($areaUrl);
        if (!in_array($area->getValue(), [OkToolsAccountAreaEnum::SETTINGS])) {
          $headers = ["x-requested-with" => "XMLHttpRequest"];
        }
        
        $areaUrl .=  "&" . $this->OkToolsClient->getAjaxRequestUrlParams();

        // Navigate to page
        $result = $this->OkToolsClient->sendForm($areaUrl, [], true);
        return $result;
    }
    
    /**
     * Push request to gateway CTrl. 
     * 
     * @notice This is possible to get new push state. 
     * But we not really retrieve. We just need to show that we not a robot.
     * 
     * @parm string $areaUrl
     *   Url of the are that has be attended currently.
     */
//    protected function pushGatewayCtrlRequest($areaUrl)
//    {
//        $headers = [
//          "need.new.stateId" => "0",
//          "X-Requested-With" => "XMLHttpRequest",
//          "X-XTKN" => $this->xTokenHeader
//        ];
//        
//        // Url to push.
//        $url = "push?st.cmd=PushGatewayCtrl&reqBlocks=[HeaderLogo]&p_sId=" . $this->pushStateId;
//        
//        // Get all URL parameters in array.
//        $urlParameters = explode("&amp;", $areaUrl);
//        
//        // We need to append 2 URL parameter to gateway push url.
//        if (isset($urlParameters[1]) && strpos($urlParameters[1], "_") !== 0) {
//            $url .= $urlParameters[1];
//        }
//        
//        // Send post request.
//        $this->OkToolsClient->sendForm($url, [], false, $headers);
//    }

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
//    private function retrieveAreaUrl(OkToolsAccountAreaEnum $area)
//    {
//        $lastPageDom = str_get_html($this->navigationPage);
//        if ($areaLink = $lastPageDom->find("a[aria-label={$area->getValue()}]", 0)) {
//            return ltrim($areaLink->href, '/');
//        } else {
//            throw new OkToolsNotFoundException("Couldn't find area link: {$area->getValue()}", $lastPageDom->outertext);
//        }
//    }

    /**
     * Check all notifications.
     * - Accept friendship.
     * - Accept gifts.
     * - Close other notifications.
     * 
     * @param int|null $count
     *   Notifications count to check. null = all.
     */
//    public function checkNotifications($count = null)
//    {
//        // Submit notifications.
//        $eventsPage = $this->seeAreaPage(OkToolsAccountAreaEnum::NOTIFICATIONS());
//        $html = str_get_html($eventsPage);
//        $events = $html->find("#events-list li.notify");
//        
//        // If no events have been found return.
//        if (!$events) {
//            return;
//        }
//
//        // Until counter (if $count passed) or events exceed.
//        while (count($events) && ($count === null || $count !== 0)) {
//            // Select random event index.
//            $randomIndex = rand(0, count($events) - 1);
//
//            // Select event.
//            $event = $events[$randomIndex];
//
//            // Update events array.
//            unset($events[$randomIndex]);
//
//            // Update array keys.
//            $events = array_values($events);
//
//            // Check notification.
//            $this->checkNotification($event);
//
//            // Count iterations if not 0 passed,
//            if ($count) {
//                $count--;
//            }
//        }
//    }
//
//    /**
//     * Check one notification.
//     *
//     * @param simple_html_dom_node $event
//     *   Node of the DOM. li item of the event.
//     *
//     * @throws OkToolsNotFoundException
//     *   Thrown if can't collect post data.
//     */
//    private function checkNotification(&$event)
//    {
//        // Post data collect.
//        $postData = [];
//        foreach ($event->find(".notify-actions", 0)->find("input[type=hidden]") as $parameter) {
//            $postData[$parameter->name] = $parameter->value ? $parameter->value : "" ;
//        }
//        
//        //  If post data is empty - means no form has been found.
//        if (empty($postData)) {
//            throw new OkToolsNotFoundException(
//                "No form has been found on check Notification action.",
//                $event->outertext
//            );
//        }
//        
//        // Check event type. All notifications not in list - just close.
//        $eventType = $event->{"data-type"};
//        if (in_array($eventType, $this->getNotificationTypes())) {
//            $buttonPos = 0;
//        } else {
//            $buttonPos = 1;
//        }
//
//        // Button clicked.
//        $postData[$event->find(".base-button_target", $buttonPos)->name] =
//            $event->find(".base-button_target", $buttonPos)->value;
//
//        // Send request for notification close or accept.
//        $requestUrl = ltrim($event->find("form", 0)->action, "/");
//        
//        // Set up headers and send request.
//        $headers = [
//            "X-Requested-With" => "XMLHttpRequest",
//            "X-XTKN" => $this->xTokenHeader
//        ];
//        $this->OkToolsClient->sendForm($requestUrl, $postData, false, $headers);
//    }

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
        $proxy = null,
        $userAgent = null,
        $cookiesDir = null,
        $requestPauseSec = 1
    ) {
        $okToolsClient = new OkToolsClient($login, $pass, $requesterType, $proxy, $userAgent, $cookiesDir, $requestPauseSec);
        return new static($okToolsClient);
    }
}
