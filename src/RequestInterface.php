<?php

namespace Dirst\OkTools;

/**
 * Abstract class for different requests behaviour.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
interface RequestInterface
{
    /**
     * Send Get request.
     *
     * @param string $url
     *   Url to send request to.
     * @param array $getParameters
     *   Parameters to send with Get request.
     *
     * @return mixed
     *   Html response string or false on failure.
     */
    public function requestGet($url, array $getParameters = null);

    /**
     * Send post request.
     * 
     * @param string $url
     *   Url to send request to.
     * @param array $postData
     *   Data to post.
     *
     * @return mixed
     *   Html response string or false on failure.
     */
    public function requestPost($url, array $postData);

    /**
     * Gets cookies from current request.
     *
     * @return string
     *   Cookies string.
     */
    public function getCookies();

    /**
     * Set cookies before request if needed.
     *
     * @param array $cookies
     *   Array of cookies.
     */
    public function setCookies($cookies);

    /**
     * Set headers before request if needed.
     *
     * @param array $headers
     *   Array of headers.
     */
    public function setHeaders($headers);
}
