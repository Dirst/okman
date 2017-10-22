<?php

namespace Dirst\OkTools;

use Dirst\OkTools\Exceptions\OkToolsBlockedUserException;
use Dirst\OkTools\Exceptions\OkToolsLoginFailedException;
use Dirst\OkTools\Requesters\RequestersFactory;
use Dirst\OkTools\Requesters\RequestersTypesEnum;
use Dirst\OkTools\Requesters\RequestersHttpCodesEnum;
use Dirst\OkTools\Exceptions\OkToolsCaptchaAppearsException;
use Dirst\OkTools\Exceptions\OkToolsUnauthorizedUserException;

/**
 * Base class used with other Ok tools.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 2.0
 */
class OkToolsClient
{
    // @var requestInterface object.
    private $requestBehaviour;
    
    // @var string seed to convert api ids to real ids.
    protected $seed = "265224201205";
    
    // @var string base api endpoint.
    protected $apiBaseEndpoint = "https://api.ok.ru/api";
    
    // @var string moobile web api endpoint.
    protected $mobileVersionUrl = "https://m.ok.ru";

    // @var string after this time account session key should be refreshed.
    private $updateLoginTimeIntervalSeconds = 60 * 20;
    
    // @var array install ids of mobile app.
    private $deviceIdArray;

    // @var string android client string.
    private $androidClient = "android_8_17.10.3";
    
    // @var string login.
    private $login;

    // @var array login data.
    protected $loginResponseData;
    
    // @var string Application key.
    protected $appKey;
    
    // @var string useragent android device.
    protected $deviceUserAgent;

    // @var string useragent android browser.
    protected $browserUserAgent;
    
    /**
     * Construct OkToolsClient. Login to OK.RU. Define parameters.
     *
     * @param string $login
     *   User phone number.
     * @param string $pass
     *   Password.
     * @param RequestersTypesEnum $requesterType
     *   Requester to chose.
     * @param string $install_id
     *   Mobile install id parameter.
     * @param string $device_id
     *   Mobile install id parameter.
     * @param string $android_id
     *   Mobile install id parameter.
     * @param string $okAppKey
     *   OK app key.
     * @param string $proxy
     *   Proxy settings to use with request. type:ip:port:login:pass.
     *   Possible types are socks5, http.
     * @param string $userAgent
     *   User agent to be used in requests.
     * @param string $loginResponseDataDir. No Ended slash.
     *   Previous login response data dir path on a server.
     * @param int $requestPauseSec
     *   Pause before any request to emulate human behaviour.
     *
     * @throws OkToolsBlockedUserException
     *   Blocked user exception.If Human check retireved.
     * @throws OkToolsLoginFailedException
     *   If unknown problem occured.
     */
    public function __construct(
        $login,
        $pass,
        RequestersTypesEnum $requesterType,
        $installId,
        $deviceId,
        $androidId,
        $okAppKey,
        $proxy = null,
        $userAgentDevice = null,
        $userAgentBrowser = null,
        $loginResponseDataDir = null,
        $requestPauseSec = 1
    ) {
        $this->browserUserAgent = $userAgentBrowser;
        $this->deviceUserAgent = $userAgentDevice;
        
        // Create requester and define login and pause.
        $factory = new RequestersFactory();
        $this->requestBehaviour = $factory->createRequester($requesterType, $proxy, $userAgentDevice);
        
        // Android install ids.
        $this->deviceIdArray = [
          "INSTALL_ID" => $installId,
          "DEVICE_ID" => $deviceId,
          "ANDROID_ID" => $androidId
        ];

        // Set up pause.
        $this->requestPauseSec = $requestPauseSec;
        
        // Trying to login.
        $this->login = $login;
        
        // Set up application key.
        $this->appKey = $okAppKey;
        
        // Login again or use saved data from previous login to retrieve new.
        $credsFilePath = $loginResponseDataDir . "/$login";
        if (!file_exists($credsFilePath)) {
            $data = $this->login($login, $pass, $okAppKey);
        } else {
            $data = $this->updateLogin($credsFilePath, $okAppKey);
        }

        // Check for error.
        if (isset($data['error_code'])) {
            switch ($data['error_code']) {
                case RequestersHttpCodesEnum::HTTP_FORBIDDEN:
                    throw new OkToolsBlockedUserException(
                        "Couldn't login with message {$data['error_msg']}",
                        $login,
                        var_export($data, true),
                        $data['ver_redirect_url']
                    );
                    break;
                case RequestersHttpCodesEnum::HTTP_UNAUTHORIZED:
                    throw new OkToolsUnauthorizedUserException(
                        "Couldn't login with message {$data['error_msg']}",
                        $login,
                        var_export($data, true)
                    );
                    break;
            }
        }

        // If all is ok - trying to get session key.
        if (isset($data['auth_login_response']) && isset($data['auth_login_response']['session_key'])) {
            $data['time'] = time();
            file_put_contents($credsFilePath, serialize($data));
            $this->loginResponseData = $data;
        } else {
            // If no success throw Couldn't login exception.
            throw new OkToolsLoginFailedException(
                "Couldn't login beause of unknown reason.",
                $login,
                var_export($data, true)
            );
        }
    }

    /**
     * Retrieve data on login update.
     *
     * @param string $credsFilePath
     *   Login response credentials data.
     * @param string $okAppKey
     *   App key.
     *
     * @return array
     *   Json response as array.
     */
    protected function updateLogin($credsFilePath, $okAppKey)
    {
        $data = unserialize(file_get_contents($credsFilePath));

        // Data time is not set.
        if (!isset($data['time'])) {
            $data['time'] = 0;
        }

        // Do only in amount of time new session key get.
        if ($data['time'] + $this->updateLoginTimeIntervalSeconds <= time()) {
            $form = [
              "application_key" => $okAppKey,
              "client" => $this->androidClient,
              "deviceId" => http_build_query($this->deviceIdArray, null, ";"),
              "token" => $data["auth_login_response"]['auth_token'],
              "verification_supported" => 'true',
              "verification_supported_v" => "1"
            ];

            $dataDecoded = $this->makeRequest("{$this->apiBaseEndpoint}/auth/loginByToken", $form, "get");
            $data['auth_login_response']['session_key'] = $dataDecoded['session_key'];
        }

        return $data;
    }

    /**
     * Login with account login/pass pair.
     *
     * @param string $login
     *   Account login.
     * @param string $pass
     *   Account Password.
     * @param string $okAppKey
     *   App key.
     *
     * @return array
     *   Json response on login ias array.
     */
    protected function login($login, $pass, $okAppKey)
    {
        // Set up methods parameter.
        $methods[] = [
          "method" => "auth.login",
          "params" => [
            "client" => $this->androidClient,
            "device_id" => http_build_query($this->deviceIdArray, null, ";"),
            "gen_token" => "true",
            "password" => $pass,
            "referrer" => "utm_source=google-play&utm_medium=organic",
            "user_name" => $login,
            "verification_supported" => "true",
            "verification_supported_v" => "1"
          ]
        ];

        $methods[] = [
          "method" => "settings.get",
          "params" => [
            "keys" => "*",
            "marker" => "0",
            "version" => "319"
          ]
        ];

        $methods[] = [
          "method" => "libverify.libverifyPhoneActual",
        ];
        
        // Set up form parameters.
        $form = [
          "application_key" => $okAppKey,
          "deviceId" => http_build_query($this->deviceIdArray, null, ";"),
          "id" => "auth.login",
          "methods" => json_encode($methods)
        ];

        // Set up headers and send request.
        $result = $this->makeRequest("{$this->apiBaseEndpoint}/batch/execute", $form, "post");

        return $result;
    }
    
    /**
     * Get request behaviour.
     *
     * @return RequestInterface
     *   Return Object that is used to send requests.
     */
    public function getRequestBehaviour()
    {
        return $this->requestBehaviour;
    }

    /**
     * Returns account login. login() method should be called before.
     *
     * @return string
     *   Account Login string.
     */
    public function getAccountLogin()
    {
        return $this->login;
    }
    
    /**
     * Returns app key.
     *
     * @return string
     *   Application key.
     */
    public function getAppKey()
    {
        return $this->appKey;
    }

    /**
     * Login response data return.
     *
     * @return array
     *   Login response data.
     */
    public function getLoginData()
    {
        return $this->loginResponseData;
    }

    /**
     * Get Api endpoint.
     *
     * @return string
     *   Api endpoint url.
     */
    public function getApiEndpoint()
    {
        return $this->apiBaseEndpoint;
    }

    /**
     * Get Mobile Web Api endpoint.
     *
     * @return string
     *   Api endpoint url.
     */
    public function getMobileVersionUrl()
    {
        return $this->mobileVersionUrl;
    }
    
    /**
     * Get app seed.
     *
     * @return string
     *   App seed.
     */
    public function getAppSeed()
    {
        return $this->seed;
    }

    /**
     * Convert Id to/from application id.
     *
     * @param string $id
     *   Id to convert.
     *
     * @return string
     *   Converted ID.
     */
    public function convertId($id)
    {
        $a = gmp_init($this->seed);
        $b = gmp_init($id);
        $id = (string)(gmp_intval($a) ^ gmp_intval($b));

        return $id;
    }

    
    /**
     * Get account API ID.
     *
     * @return string
     *   User id in api format.
     */
    public function getAccountApiId()
    {
        return $this->loginResponseData['auth_login_response']['uid'];
    }

    /**
     * Get android client.
     *
     * @return string
     *   Android client.
     */
    public function getAndroidClient()
    {
        return $this->androidClient;
    }
    
    /**
     * Make a request to OK.
     *
     * @param string $url
     *   Url to make a request to.
     * @param mixed $formData
     *   Data to send.
     * @param string $type
     *   Get or Post request.
     * @param string $browser
     *   Use request as browser or as android device.
     * @param array $additionalHeaders
     *   ADditional headers to set.
     *
     * @throws OkToolsCaptchaAppearsException
     *   Thrown if there is aptcha on a page.
     *
     * @return string|array
     *   Web page or decoded json string.
     */
    public function makeRequest($url, $formData = null, $type = "get", $browser = false, $additionalHeaders = [])
    {
      // Set base headers
        $headers = [
          "Accept-Encoding" => "gzip",
          "Connection" => "keep-alive",
        ];
        $headers["Accept"] = $browser ? "text/html,application/xhtml+xml,"
            . "application/xml;q=0.9,image/webp,*/*;q=0.8" : 'application/json';
        $headers['User-Agent'] = $browser ? $this->browserUserAgent : $this->deviceUserAgent;

      // Additional header.
        if ($browser) {
            $headers["X-Requested-With"] = "ru.ok.android";
        }
      
        // Set additional headers.
        if (!empty($additionalHeaders)) {
            $headers = array_merge($headers, $additionalHeaders);
        }
        
        $this->requestBehaviour->setHeaders($headers);

        // Send request.
        if ($type == "get") {
            $result = $this->requestBehaviour->requestGet($url, $formData);
        } else {
            $result = $this->requestBehaviour->requestPost($url, $formData);
        }

      // Decompress
        if ($decompressed = @gzdecode($result)) {
            $result = $decompressed;
        }
      
      // Check if captcha appears. Possible only in browser mode.
        if ($browser) {
            $mobilePage = str_get_html($result);

            // Check if captcha shows up.
            if ($mobilePage->find("#captcha", 0)) {
                throw new OkToolsCaptchaAppearsException("Captcha appears", $this->login, $result);
            }
        }

        return $browser ? $result : json_decode($result, true);
    }
}
