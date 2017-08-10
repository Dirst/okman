<?php

namespace Dirst\OkTools;

use Dirst\OkTools\OkToolsClient;

/**
 * Abstract class for other control classes.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
abstract class OkToolsBaseControl
{
  // @var OkToolsClient object.
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
     * Construct New object with new OktoolsClient insides.
     *
     * @param string $login
     *   User phone number.
     * @param string $pass
     *   Password.
     * @param string $proxy
     *   Proxy settings to use with request. type:ip:port:login:pass.
     *   Possible types are socks5, http.
     * @param int $requestPauseSec
     *   Pause before any request to emulate human behaviour.
     *
     * @return OkToolsBaseControl
     *   Control object with Client initialized inside.
     */
    public static function initWithClient(
        $login,
        $pass,
        RequestersTypesEnum $requesterType,
        $proxy = null,
        $requestPauseSec = 1
    ) {
    
        $okToolsClient = new OkToolsClient($login, $pass, $requesterType, $proxy, $requestPauseSec);
        return new static($okToolsClient);
    }
}
