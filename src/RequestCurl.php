<?php

namespace Dirst\OkTools;

/**
 * Curl request methods implementation.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class RequestCurl implements RequestInterface
{
    // @var resource object.
    private $curlResource;

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
        $this->curlResource = curl_init();
        $this->proxy = $proxy;
    }

    /**
     * Destroy curl resource.
     */
    public function __destruct()
    {
        curl_close($this->curlResource);
    }

    /**
     * {@inheritdoc}
     */
    public function requestGet($url, array $getParameters = null)
    {
        // check if url has been passed with parameters already.
        $url = $url . (strpos($url, "?") === FALSE ? "?" : "&");
        $this->setRequest($url . http_build_query($getParameters));
        curl_setopt($this->curlResource, CURLOPT_HTTPGET, true);
        return curl_exec($this->curlResource);
    }
    
    /**
     * {@inheritdoc}
     */
    public function requestPost($url, array $postData)
    {
        $this->setRequest($url);
        curl_setopt($this->curlResource, CURLOPT_POSTFIELDS, http_build_query($postData));
        return curl_exec($this->curlResource);
    }

    /**
     * Common function for Post/Get setup.
     *
     * @param string $url
     *   Url to send request to.
     */
    private function setRequest($url)
    {
        curl_setopt($this->curlResource, CURLOPT_URL, html_entity_decode($url));
        curl_setopt($this->curlResource, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->curlResource, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlResource, CURLOPT_POST, true);
        curl_setopt($this->curlResource, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($this->curlResource, CURLINFO_HEADER_OUT, 1);

        // Save cookies in memory until curl_close  is not called. Windows use NULL.
        curl_setopt($this->curlResource, CURLOPT_COOKIEJAR, '\\' === DIRECTORY_SEPARATOR ? NULL : '/dev/null');
        
        // Set proxy settings.
        if ($this->proxy)
        {
            list($proxyType, $proxyIp, $proxyPort, $proxyLogin, $proxyPass) = explode(":", $this->proxy);
            curl_setopt($this->curlResource, CURLOPT_PROXY, $proxyIp);
            curl_setopt($this->curlResource, CURLOPT_PROXYPORT, $proxyPort);
            curl_setopt($this->curlResource, CURLOPT_PROXYTYPE, $proxyType == 'http' ? CURLPROXY_HTTP : CURLPROXY_SOCKS5);
            curl_setopt($this->curlResource, CURLOPT_PROXYUSERNAME, $proxyLogin);
            curl_setopt($this->curlResource, CURLOPT_PROXYPASSWORD, $proxyPass);
            curl_setopt($this->curlResource, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->curlResource, CURLOPT_SSL_VERIFYHOST, false);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        return curl_getinfo($this->curlResource, CURLINFO_HEADER_OUT);
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseCode() 
    {
        return curl_getinfo($this->curlResource, CURLINFO_HTTP_CODE);
    }

    /**
     * {@inheritdoc}
     */
    public function setHeaders($headers) 
    {
        curl_setopt($this->curlResource, CURLOPT_HTTPHEADER, $headers);
    }
}
