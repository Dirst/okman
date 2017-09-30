<?php

namespace Dirst\OkTools\Exceptions;

/**
 * Base Exception class for OkTools.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkToolsException extends \Exception
{
    // @var array.
    protected $values;

    /**
     * Exception constructor.
     *
     * @param string $message
     *   Message about error.
     * @param string $responseString
     *   Response string.
     * @param Throwable $previous.
     *   Previous exception.
     */
    public function __construct($message, $responseString = null, Throwable $previous = null)
    {
        $this->values['responseString'] = $responseString;
        parent::__construct($message, 0, $previous);
    }

    /**
     * {@inheritdoc}
     */
    public function __get($name)
    {
        return $this->values[$name];
    }
}
