<?php

namespace Dirst\OkTools;

use MyCLabs\Enum\Enum;

/**
 * Blocked/FROZEN status.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkBlockedStatusEnum extends Enum
{
    const USER_BLOCKED = 'accountBlockedByAdminStub';
    const USER_FROZEN  = 'uvPrePhoneCaptcha';
    const GROUP_BLOCKED_CLASS = 'group-disabled';
    const ERROR_PAGE_CLASS    = "error-page";
}
