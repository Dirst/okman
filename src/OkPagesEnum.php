<?php

namespace Dirst\OkTools;

use MyCLabs\Enum\Enum;

/**
 * Pages Enum.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkPagesEnum extends Enum
{
    const LOGIN_PATH = "dk?bk=GuestMain";
    const LOGOUT_PATH = "dk?bk=Logoff&st.cmd=logoff&_prevCmd=logoff";
    const NEWS_PATH = "dk?st.cmd=userMain&_prevCmd=userMain&_aid=leftMenuClick";
    const GUESTS_PATH = "dk?st.cmd=userGuests&_prevCmd=userGuests&_aid=leftMenuClick";
    const EVENTS = "dk?st.cmd=userEvents&st.rf=on&_prevCmd=userEvents&_aid=leftMenuClick";
    const GROUP_MEMBERS = "dk?st.cmd=altGroupActiveMembers&_prevCmd=altGroupMain&_aid=groupProfMenu&st.groupId=GROUPID&"
        . "st.page=PAGENUMBER";
    const MODER_ASSIGN_PAGE = "dk?st.cmd=altGroupGrantModerator&st.groupId=GROUPID&st.usrId=USERID&"
        . "_prevCmd=altGroupActiveMembers&st.rtu=RETURNPAGE";
    const INVITE_TO_GROUP_PAGE = "dk?st.cmd=altGroupConfirmInvite&st.iog=off&st.groupId=GROUPID&st.usrId=USERID&"
        . "st.rtu=RETURNPAGE&_prevCmd=altGroupSelectGroupToAdd";
    const INVITE_LIST_PAGE = "dk?st.cmd=altGroupSelectGroupToAdd&st.friendId=USERID&st.page=PAGENUMBER&"
        . "_prevCmd=altGroupSelectGroupToAdd";
    const GROUP_PAGE = "dk?st.cmd=altGroupMain&st.groupId=GROUPID&_prevCmd=userAltGroups&_aid=groupOwnShowcase";
    const JOIN_GROUP_PAGE = "dk?st.cmd=altGroupJoin&st.groupId=GROUPID&st.frwd=on&st.page=1&"
        . "_prevCmd=altGroupMain&_aid=groupProfJoin#js-dlg";
}
