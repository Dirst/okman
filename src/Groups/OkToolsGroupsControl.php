<?php

namespace Dirst\OkTools\Groups;

use Dirst\OkTools\OkToolsBaseControl;
use Dirst\OkTools\Exceptions\OkToolsResponseException;
use Dirst\OkTools\Exceptions\OkToolsNotFoundException;
use Dirst\OkTools\Exceptions\OkToolsBlockedGroupException;
/**
 * Groups control for account.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkToolsGroupsControl extends OkToolsBaseControl
{
    const GROUP_BASE_URL = "group";
    public function getMembers($groupId)
    {
        
    }

    public function joinGroup($groupId)
    {
        $groupPageDom = $this->initGroupPage($groupId);
        $joinLink = $groupPageDom->find('a[href*="AltGroupTopCardButtonsJoin"]', 0);
        if (!$joinLink) {
           throw new OkToolsNotFoundException("Couldn't find join link in group - $groupId", $groupPageDom->outertext);
        }

        $groupJoinedPage = $this->OkToolsClient->sendForm($joinLink->href, [], true);
    }

    public function isJoindeToGroup($groupId)
    {
        $groupPageDom = $this->initGroupPage($groupId);
        $exitLink = $groupPageDom->find("a[href*='GroupJoinDropdownBlock&amp;st.jn.act=EXIT']", 0);
        $joinLink = $groupPageDom->find('a[href*="AltGroupTopCardButtonsJoin"]', 0);
        switch (true) {
            case $exitLink:
              return true;
              break;
            case $joinLink:
              return false;
              break;
            default:
              throw new OkToolsNotFoundException("Couldn't Grooup exit/join links - $groupId", $groupPageDom->outertext);
        }        
    }
    protected function initGroupPage($groupId) 
    {
        try {
            $groupPage = $this->OkToolsClient->attendPage(self::GROUP_BASE_URL . "/$groupId", true);
        }
        catch (OkToolsResponseException $ex) {
            if ($ex->responseCode == 404) {
                //  @TODO new exception.
                throw new OkToolsBlockedGroupException("Couldn't find the group", $groupId, $ex->html);
            }
            else {
                throw $ex;
            }
        }

        $groupPageDom = str_get_html($groupPage);
        $disabledMarker = $groupPageDom->find("#hook_Block_DisabledGroupTeaserBlock", 0);

        if (!$disabledMarker) {
            throw new OkToolsNotFoundException("Couldn't find disabled marker in the $groupId group", $groupPage);
        }

        if ($disabledMarker->innertext != null) {
            throw new OkToolsBlockedGroupException("Group is disabled.", $groupId, $groupPage);
        }

        return $groupPageDom;
    }

    public function leftGroup($groupId)
    {
        $groupPageDom = $this->initGroupPage($groupId);
        $exitLink = $groupPageDom->find("a[href*='GroupJoinDropdownBlock&amp;st.jn.act=EXIT']", 0);
        if (!$exitLink) {
           throw new OkToolsNotFoundException("Couldn't find exit link in group - $groupId", $groupPageDom->outertext);
        }

        $groupJoinedPage = $this->OkToolsClient->sendForm($exitLink->href, [], true);
    }

    public function inviteUserToGroup($userId, $groupId)
    {
      
    }
}
