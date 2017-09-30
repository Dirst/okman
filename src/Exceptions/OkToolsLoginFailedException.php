<?php

namespace Dirst\OkTools\Exceptions;

/**
 * Exception class for blocked exception type.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkToolsLoginFailedException extends OkToolsException
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
     * @param Throwable $previous.
     *   Previous exception.
     */
    public function __construct($message, $itemId, $responseString = null, Throwable $previous = null)
    {
        parent::__construct($message, $responseString, $previous);
        $this->values['itemId'] = $itemId;
    }
}
