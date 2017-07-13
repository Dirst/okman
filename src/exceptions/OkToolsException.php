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
     * @param string $html
     *   Html response string.
     * @param int $code
     *   Code of Exception.
     * @param Throwable $previous.
     *   Previous exception.
     */
    public function __construct($message, $html, $code = 0, Throwable $previous = null)
    {
        $this->values['html'] = $html;
        parent::__construct($message, $code, $previous);
    }

    /**
     * {@inheritdoc}
     */
    public function __get($name)
    {
        return $this->values[$name];
    }
}
