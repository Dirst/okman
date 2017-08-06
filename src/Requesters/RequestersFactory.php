<?php

namespace Dirst\OkTools\Requesters;

use Dirst\OkTools\Requesters\RequestCurl;
use Dirst\OkTools\Requesters\RequestersTypesEnum;

/**
 * Provides requester object.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class RequestersFactory
{
    /**
     * Creates new requester object.
     * 
     * @param string $proxy
     *   Proxy settings to use with request. type:ip:port:login:pass.
     *   Possible types are socks5, http.
     * @param RequestersTypesEnum $requesterType
     *   Type of requester to create.
     *
     * @return RequestInterface
     *   New Requester object.   
     */
    public function createRequester($proxy = null, RequestersTypesEnum $requesterType)
    {
        // Return appropriate requester.
        switch ($requesterType->getValue) {
          case RequestersTypesEnum::CURL:
          default:
            return new RequestCurl($proxy);
        }
    }
}
