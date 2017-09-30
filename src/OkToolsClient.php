<?php

namespace Dirst\OkTools;

use Dirst\OkTools\Exceptions\OkToolsBlockedUserException;
use Dirst\OkTools\Exceptions\OkToolsLoginFailedException;
use Dirst\OkTools\Requesters\RequestersFactory;
use Dirst\OkTools\Requesters\RequestersTypesEnum;
use Dirst\OkTools\Requesters\RequestersHttpCodesEnum;
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

    // @var string after this time account session key should be refreshed.
    protected $updateLoginTimeIntervalSeconds = 60 * 20;
    
    // @var array install ids of mobile app.
    private $deviceIdArray;

    // @var string android client string.
    private $androidClient = "android_8_15.2.1";
    
    // @var string login.
    private $login;

    // @var array login data.
    private $loginResponseData;

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
        $userAgent = null,
        $loginResponseDataDir = null,
        $requestPauseSec = 1
    ) {
        // Create requester and define login and pause.
        $factory = new RequestersFactory();
        $this->requestBehaviour = $factory->createRequester($requesterType, $proxy, $userAgent);
        
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
        
        // Login again or use saved data from previous login to retrieve new.
        $credsFilePath = $loginResponseDataDir . "/$login";
        if (!file_exists($credsFilePath)) {        
            $data = $this->login($login, $pass, $okAppKey);
        } 
        else {
            $data = $this->updateLogin($credsFilePath, $okAppKey);
        }

        // Check for error.
        if (isset($data['error_code'])) {
            switch($data['error_code']) {
                case RequestersHttpCodesEnum::HTTP_FORBIDDEN:
                    
                    throw new OkToolsBlockedUserException(
                        "Couldn't login with message {$data['error_msg']}",
                        $login,
                        var_export($data, true),
                        $data['ver_redirect_url']
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
            throw new OkToolsLoginFailedException("Couldn't login beause of unknown reason.", $login, var_export($data, true));
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
    protected function updateLogin($credsFilePath, $okAppKey) {
        $data = unserialize(file_get_contents($credsFilePath));

        // Data time is not set.
        if (!isset($data['time'])) 
          $data['time'] = 0;

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

            $result = $this->requestBehaviour->requestGet("{$this->apiBaseEndpoint}/auth/loginByToken", $form);
            if ($decompressed = @gzdecode($result)) {
              $result = $decompressed;
            }
   
            $dataDecoded = json_decode($result, true);
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
    protected function login($login, $pass, $okAppKey) {
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
        
        // Set up headers
        $headers = [
          "Content-Type" => "application/x-www-form-urlencoded",
          "Accept" => 'application/json',
          "Connection" => "keep-alive",
          "Accept-Encoding" => "gzip"
        ];

        // Set up form parameters.
        $form = [
          "application_key" => $okAppKey,
          "deviceId" => http_build_query($this->deviceIdArray, null, ";"),
          "id" => "auth.login",
          "methods" => json_encode($methods)
        ];

        // Set up headers and send request.
        $this->requestBehaviour->setHeaders($headers);
        $result = $this->requestBehaviour->requestPost("{$this->apiBaseEndpoint}/batch/execute", $form);
        
        // Trying to decode response string anyway.
        if ($decodedResult = @gzdecode($result)) {
            $result = $decodedResult;
        }
        
        $data = json_decode($result, true);
        return $data;
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
}
