<?php

namespace Dirst\OkTools\Exceptions;

/**
 * Exception class for request Exception.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkToolsResponseException extends OkToolsException
{

    /**
     * Exception constructor.
     *
     * @param string $message
     *   Message about error.
     * @param string $html
     *   Html response string.
     * @param int $responseCode
     *   Response code.
     * @param array $responseHeaders
     *   Response Headers.
     * @param int $code
     *   Code of Exception.
     * @param Throwable $previous.
     *   Previous exception.
     */
    public function __construct(
        $message,
        $html,
        $responseCode,
        $responseHeaders,
        $code = 0,
        Throwable $previous = null
    ) {
        $this->values['responseCode'] = $responseCode;
        $this->values['responseHeaders'] = $responseHeaders;

        parent::__construct($message, $html, $previous);
    }
}
