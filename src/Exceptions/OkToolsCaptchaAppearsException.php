<?php

namespace Dirst\OkTools\Exceptions;

use Dirst\OkTools\Requesters\RequestInterface;

/**
 * Exception class for Captcha appears event.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkToolsCaptchaAppearsException extends OkToolsBlockedException
{
    /**
     * Exception constructor.
     *
     * @param string $message
     *   Message about error.
     * @param string $itemId
     *   Id of the item that has been blocked.
     * @param string $responseString
     *   Response string.
     * @param RequestInterface $requester
     *   Interface for requester object.
     * @param Throwable $previous.
     *   Previous exception.
     */
    public function __construct($message, $itemId, $responseString = null, RequestInterface &$requester, Throwable $previous = null)
    {
        parent::__construct($message, $itemId, $responseString, $previous);
        $this->values['requester'] = &$requester;
    }
}
