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
    const LOGOUT_PATH = "dk?bk=Logoff&st.cmd=logoff&_prevCmd=logoff&tkn=2479";
}
