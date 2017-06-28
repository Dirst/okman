<?php

namespace Dirst\OkTools;

/**
 * Guzzle request methods implementation.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class RequestGuzzle implements RequestInterface
{
    // @var resource object.
    private $guzzleClient;
    private $guzzleResponse;

    // @var string proxy.
    private $proxy;

    const USER_AGENT = "Mozilla/5.0 (Linux; U; Android 4.0.3; ko-kr; LG-L160L Build/IML74K) "
        . "ppleWebkit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30";
  
    /**
     * Create curl resource and assign it with proxy to variables.
     *
     * @param string $proxy
     *   Proxy settings to use with request. type:ip:port:login:pass.
     *   Possible types are socks5, http.
     */
    public function __construct($proxy = null)
    {
        $this->guzzleClient = new \GuzzleHttp\Client(['cookies' => true]);
        $this->proxy = $proxy;
    }

    /**
     * {@inheritdoc}
     */
    public function requestGet($url, array $getParameters = null)
    {
        $url = $url . (strpos($url, "?") === FALSE ? "?" : "&");
        $this->guzzleResponse = $this->guzzleClient->request('GET', $url . http_build_query($getParameters), [
          'headers' => [
             "User-Agent" => self::USER_AGENT
           ]
        ]);
        return $this->guzzleResponse->getBody();
    }
    
    /**
     * {@inheritdoc}
     */
    public function requestPost($url, array $postData)
    {
        $this->guzzleResponse = $this->guzzleClient->request('POST', $url, [
            'form_params' => $postData,
            'headers' => [
              "User-Agent" => self::USER_AGENT
            ],
           'allow_redirects' => true
        ]);

        return $this->guzzleResponse->getBody();
    }

    /**
     * {@inheritdoc}
     */
    public function getCookies()
    {
        return $this->guzzleResponse->getHeader("Set-Cookie");
    }

    /**
     * {@inheritdoc}
     */
    public function setCookies($cookies) 
    {
        
    }

    /**
     * {@inheritdoc}
     */
    public function setHeaders($headers) 
    {
        
    }
}
