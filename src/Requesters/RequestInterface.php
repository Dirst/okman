<?php

namespace Dirst\OkTools\Requesters;

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
     * @throw OkToolsResponseException
     *   Will be thrown if response code != 200.
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
     * @param mixed $postData
     *   Data to post. json string or array.
     * @param boolean $multipart
     *   Send as multipart.
     *
     * @throw OkToolsResponseException
     *   Will be thrown if response code != 200.
     *
     * @return mixed
     *   Html response string or false on failure.
     */
    public function requestPost($url,  $postData, $multipart = false);

    /**
     * Gets headers from current request.
     *
     * @return string
     *   Headers string.
     */
    public function getHeaders();

    /**
     * Gets headers from current request.
     *
     * @return int
     *   Current response http code.
     */
    public function getResponseCode();

    /**
     * Set headers before request if needed.
     *
     * @param array $headers
     *   Array of headers.
     */
    public function setHeaders(array $headers);
}
