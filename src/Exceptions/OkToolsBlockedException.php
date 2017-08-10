<?php

namespace Dirst\OkTools\Exceptions;

/**
 * Exception class for blocked exception type.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkToolsBlockedException extends OkToolsException
{
    /**
     * Exception constructor.
     *
     * @param string $message
     *   Message about error.
     * @param string $itemId
     *   Id of the item that has been blocked.
     * @param string $html
     *   Html response string.
     * @param int $code
     *   Code of Exception.
     * @param Throwable $previous.
     *   Previous exception.
     */
    public function __construct($message, $itemId, $html = null, $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $html, $code, $previous);
        $this->values['itemId'] = $itemId;
    }
}
