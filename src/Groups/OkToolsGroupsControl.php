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

    /**
     * Get group members.
     *
     * @param type $groupId
     * @param type $page
     * @return type
     * @throws OkToolsNotFoundException
     */
    public function getMembers($groupId, $page = 1)
    {
        $membersPageDom = $this->initMembersPage($groupId);

        $usersArray = [];
        if ($page == 1) {
            $usersArray = $this->getFirstPageGroupMembers($membersPageDom);
        }
        else {
          
        }
        
        return $usersArray;
    }
 
    /**
     * Get first group page members.
     * 
     * @notice To reduce the risk to be banned 1 page 
     * request for members should be done as always via Get request instead of other pages POST request
     *
     * @param simple_html_dom_node $membersPageDom
     *   Members Page Dom.
     *
     * @throws OkToolsNotFoundException 
     *   Thrown if users cards block not found.
     *
     * @return array
     *   Array of users [id, name, online]
     */
    protected function getFirstPageGroupMembers($membersPageDom)
    {
        $usersBlock = $membersPageDom->find("#hook_Loader_GroupMembersResultsBlockLoader > ul", 0);
        if (!$usersBlock) {
            throw new OkToolsNotFoundException("Couldn't find Groups members on 1 page for $groupId group", $usersBlock->outertext);
        }

        $usersArray = [];
        foreach ($usersBlock->find(".cardsList_li") as $userBlock) {
            if ($userBlock->find(".add-stub", 0)) {
                continue;
            }

            $usersArray[] = [
                'id' => str_replace("/profile/", "", $userBlock->find("a.photoWrapper", 0)->href),
                'name' => $userBlock->find(".card_add a.o", 0)->plaintext,
                'online' => $userBlock->find("span.ic-online", 0) ? true : false
            ];
        }

        return $usersArray;
    }
    
    /**
     * Get members page DOM
     *
     * @param type $groupId
     * @return type
     * @throws OkToolsBlockedGroupException
     * @throws OkToolsResponseException
     */
    protected function initMembersPage($groupId)
    {
      try {
        $membersPage = $this->OkToolsClient->attendPage(self::GROUP_BASE_URL . "/$groupId/members", true);
      }
      catch (OkToolsResponseException $ex) {
          if ($ex->responseCode == 404) {
              //  @TODO new exception.
              throw new OkToolsBlockedGroupException("Couldn't find members page", $groupId, $ex->html);
          }
          else {
              throw $ex;
          }
      }
      
      return str_get_html($membersPage);
    }

    /**
     * Join the group.
     *
     * @param type $groupId
     * @throws OkToolsNotFoundException
     */
    public function joinTheGroup($groupId)
    {
        $groupPageDom = $this->initGroupPage($groupId);
        $joinLink = $groupPageDom->find('a[href*="AltGroupTopCardButtonsJoin"]', 0);
        if (!$joinLink) {
           throw new OkToolsNotFoundException("Couldn't find join link in group - $groupId", $groupPageDom->outertext);
        }

        $groupJoinedPage = $this->OkToolsClient->sendForm($joinLink->href, [], true);
    }

    /**
     * Check if account already joined to group.
     *
     * @param type $groupId
     * @return boolean
     * @throws OkToolsNotFoundException
     */
    public function isJoinedToGroup($groupId)
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

    /**
     * Init Group front page.
     *
     * @param type $groupId
     * @return type
     * @throws OkToolsBlockedGroupException
     * @throws OkToolsResponseException
     * @throws OkToolsNotFoundException
     */
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

    /**
     * Cancel group membership for account.
     *
     * @param type $groupId
     * @throws OkToolsNotFoundException
     */
    public function leftTheGroup($groupId)
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

    public function assignGroupRole(OkGroupRoleEnum $role, $uid, $groupId)
    {
        
    }
    
    protected function getGwtHash()
    {
      
    }
}
