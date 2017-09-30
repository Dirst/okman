<?php

namespace Dirst\OkTools\Requesters;

use Dirst\OkTools\Exceptions\OkToolsResponseException;
use Dirst\OkTools\Requesters\RequestersHttpCodesEnum;

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
    
    private $responseHeaders;

    // @var string If no UA passed to constructor.
    const USER_AGENT = "OKAndroid/15.2.1 b319 (Android 4.1.4; ru_RU; Xiaomi "
        . "HM NOTE 1S Build/MiuiPro; xhdpi 320dpi 640x1024)";
    
    // @var string
    protected $userAgent;
    
    // @var string
    protected $cookiesFilePath;
    
    
    /**
     * Create curl resource and assign it with proxy to variables.
     *
     * @param string $proxy
     *   Proxy settings to use with request. type:ip:port:login:pass.
     *   Possible types are socks5, http.
     * 
     * @param string $userAgent
     *   User agent to be used in requests.
     */
    public function __construct($proxy = null, $userAgent = null)
    {
        $this->curlResource = curl_init();
        $this->proxy = $proxy;
        $this->userAgent = $userAgent ? $userAgent : self::USER_AGENT;
        $this->cookiesFilePath = null;
    }
    
    /**
     * Sets cookie file.
     *
     * @param string $cookieFilePath
     *   Sets cookie file path.
     */
    public function setCookieFile($cookieFilePath) {
        $this->cookiesFilePath = $cookieFilePath;
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
    public function requestGet($url, array $getParameters = [])
    {
        // check if url has been passed with parameters already.
        $url = $url . (strpos($url, "?") === false ? "?" : "&");
        $this->setRequest($url . http_build_query($getParameters));
        curl_setopt($this->curlResource, CURLOPT_HTTPGET, true);
        
        return $this->executeRequest();
    }
    
    /**
     * {@inheritdoc}
     */
    public function requestPost($url,  $postData, $multipart = false)
    {
        $this->setRequest($url);
        if ($postData) {
            $postData = is_array($postData) && !$multipart ? http_build_query($postData) : $postData;
            curl_setopt($this->curlResource, CURLOPT_POSTFIELDS, $postData);
        }

        return $this->executeRequest();
    }
    
    /**
     * Executes prepared request.
     *
     * @return string
     *   Html response.
     *
     * @throws OkToolsResponseException
     *   Exception will be thrown if respoonse code != 200.
     */
    private function executeRequest()
    {
        $this->responseHeaders = [];
        $result = curl_exec($this->curlResource);

        // Check if response code is OK.
        if ($this->getResponseCode() == RequestersHttpCodesEnum::HTTP_SUCCESS) {
            return $result;
        } else {
            throw new OkToolsResponseException(
                "Response has been failed",
                $this->getHeaders(),
                $this->getResponseCode(),
                $result
            );
        }
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
        curl_setopt($this->curlResource, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($this->curlResource, CURLINFO_HEADER_OUT, 1);
//        curl_setopt($this->curlResource, CURLOPT_HTTP_VERSION, CURL_VERSION_HTTP2); Sometimes couldn't make a request. Unstable.
        curl_setopt($this->curlResource, CURLOPT_HEADERFUNCTION, [$this, 'readHeaders']);

        // Set cookies to memory or to file.
        if (!$this->cookiesFilePath) {
            // Save cookies in memory until curl_close  is not called. Windows use NULL.
            curl_setopt($this->curlResource, CURLOPT_COOKIEJAR, '\\' === DIRECTORY_SEPARATOR ? null : '/dev/null');
        } else {
            curl_setopt($this->curlResource, CURLOPT_COOKIEJAR, $this->cookiesFilePath);
            curl_setopt($this->curlResource, CURLOPT_COOKIEFILE, $this->cookiesFilePath);
        }

        // Set proxy settings.
        if ($this->proxy) {
            list($proxyType, $proxyIp, $proxyPort, $proxyLogin, $proxyPass) = explode(":", $this->proxy);
            curl_setopt($this->curlResource, CURLOPT_PROXY, $proxyIp);
            curl_setopt($this->curlResource, CURLOPT_PROXYPORT, $proxyPort);
            curl_setopt(
                $this->curlResource,
                CURLOPT_PROXYTYPE,
                $proxyType == 'http' ? CURLPROXY_HTTP : CURLPROXY_SOCKS5
            );
            curl_setopt($this->curlResource, CURLOPT_PROXYUSERNAME, $proxyLogin);
            curl_setopt($this->curlResource, CURLOPT_PROXYPASSWORD, $proxyPass);
            curl_setopt($this->curlResource, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->curlResource, CURLOPT_SSL_VERIFYHOST, false);
        }
    }

    /**
     * Callback to parse header line and insert in array.
     *
     * @param Resource $curl
     *   Curl Object.
     * @param string $headerLine
     *   One header line.
     * @return int
     *   Length of header line.
     */
    private function readHeaders($curl, $headerLine)
    {
        @list($name, $value) = explode(": ", $headerLine, 2);
        if (trim($name)) {
            $this->responseHeaders[$name] = $value;
        }
        return strlen($headerLine);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        return $this->responseHeaders;
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
    public function setHeaders(array $headers)
    {
        // Iterate through headers array and make format according to curl requirement.
        foreach ($headers as $name => $header) {
            $curlHeaders[] = "$name:$header";
        }
        
        // Set new http headers.
        curl_setopt($this->curlResource, CURLOPT_HTTPHEADER, $curlHeaders);
    }
}
