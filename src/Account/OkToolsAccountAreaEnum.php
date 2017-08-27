<?php

namespace Dirst\OkTools\Account;

use MyCLabs\Enum\Enum;

/**
 * Contains all areas on account page.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkToolsAccountAreaEnum extends Enum
{
    const NOTIFICATIONS = "feed?st.cmd=userMain&cmd=NotificationsLayer&st.layer.cmd=PopLayerClose";
//    const GUESTS = "";
//    const EVENTS = "События";
    const MAIN = "feed?st.cmd=userMain&st.layer.cmd=PopLayerClose&st._forceSetHistory=true&st._aid=Toolbar_UserMain";
//    const FRIENDS = "Друзья";
    const MESSAGES = "dk?cmd=MessagesLayer";
    const DISCUSSION = "?cmd=ToolbarDiscussions&st.cmd=userMain";

    const SETTINGS = "settings?st.cmd=userConfig&st._aid=NavMenu_User_Config";
}
