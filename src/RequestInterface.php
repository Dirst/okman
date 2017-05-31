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
     * @param array $getParameters
     *   Parameters to send with Get request.
     * @param string $cookies
     *   Cookies to use in current request.
     *
     * @return string
     *   Html response.
     */
    public function requestGet(array $getParameters = null, $cookies = null);

    /**
     * Send post request.
     *
     * @param array $postData
     *   Data to post.
     * @param string $cookies
     *   Cookies to use in current request.
     *
     * @return string
     *   Html response.
     */
    public function requestPost(array $postData, $cookies = null);

    /**
     * Gets cookies from current request.
     *
     * @return string
     *   Cookies string.
     */
    public function getCookies();

    /**
     * Get html of the current request.
     *
     * @return string
     *   Html string.
     */
    public function getBody();
}
