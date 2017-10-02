<?php

namespace Dirst\OkTools;

use Dirst\OkTools\OkToolsClient;
use Dirst\OkTools\Requesters\RequestersTypesEnum;

/**
 * Abstract class for other control classes.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
abstract class OkToolsBaseControl
{
    // @var \Dirst\OkTools\OkToolsClient object.
    protected $OkToolsClient;

    /**
     * Init Account control object.
     *
     * @param OkToolsClient $okTools
     *   Ok Tools Base object.
     */
    public function __construct(OkToolsClient $okTools)
    {
        $this->OkToolsClient = $okTools;
    }
    
    /**
     * Returns  ok tools client object.
     *s
     * @return OkToolsClient
     *   Client object.
     */
    public function getOkToolsClient()
    {
        return $this->OkToolsClient;
    }
}
