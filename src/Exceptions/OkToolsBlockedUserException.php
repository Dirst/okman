<?php

namespace Dirst\OkTools\Exceptions;

/**
 * Exception class for User blocked exception type.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 2.0
 */
class OkToolsBlockedUserException extends OkToolsBlockedException
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
     * @param string $verificationUrl
     *   Verification url for account
     * @param Throwable $previous.
     *   Previous exception.
     */
    public function __construct(
        $message,
        $itemId,
        $responseString = null,
        $verificationUrl = null,
        Throwable $previous = null
    ) {
    
        parent::__construct($message, $itemId, $responseString, $previous);
        $this->values['verificationUrl'] = $verificationUrl;
    }
}
