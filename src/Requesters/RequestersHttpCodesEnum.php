<?php

namespace Dirst\OkTools\Requesters;

use MyCLabs\Enum\Enum;

/**
 * Requesters status codes list.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class RequestersHttpCodesEnum extends Enum
{
    const HTTP_SUCCESS = 200;
    const HTTP_FORBIDDEN = 403;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_NOT_FOUND = 404;
    const HTTP_UNAUTHORIZED = 401;    
}
