<?php

namespace Dirst\OkTools;

use Dirst\OkTools\Exceptions\OkToolsException;
use Dirst\OkTools\Exceptions\OkToolsNotFoundException;
use Dirst\OkTools\Exceptions\OkToolsBlockedGroupException;
use Dirst\OkTools\Exceptions\OkToolsBlockedUserException;
use Dirst\OkTools\Exceptions\OkToolsNotPermittedException;
use Dirst\OkTools\Exceptions\OkToolsCaptchaAppearsException;

use Dirst\OkTools\Requesters\RequestersFactory;
use Dirst\OkTools\Requesters\RequestersTypesEnum;

/**
 * Base class used with other Ok tools.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkToolsClient
{
    // Ok urls.
    const M_URL = "https://m.ok.ru/";
    const D_URL = "https://ok.ru/";

    // @var requestInterface object.
    private $requestBehaviour;

    // @var string.
    private $login;
    
    // @var string
    private $requestPauseSec;

    // @var string
    private $lastPage;
    
    // @var string
    private $postToken;
    
    // @var string
    private $gwtDesktopHash;
  
    // @var array
    private $periodicDesktopManagerData;

    /**
     * Construct OkToolsClient. Login to OK.RU. Define parameters.
     *
     * @param string $login
     *   User phone number.
     * @param string $pass
     *   Password.
     * @param RequestersTypesEnum $requesterType
     *   Requester to chose.
     * @param string $proxy
     *   Proxy settings to use with request. type:ip:port:login:pass.
     *   Possible types are socks5, http.
     * @param string $userAgent
     *   User agent to be used in requests.
     * @param string $cookiesDir. No Ended slash.
     *   Cookies dir path on a server.
     * @param int $requestPauseSec
     *   Pause before any request to emulate human behaviour.

     * @throws OkToolsNotFoundException
     *   Will be thrown if no user marker found.
     *
     * @return string
     *   Html page after login.
     */
    public function __construct(
        $login,
        $pass,
        RequestersTypesEnum $requesterType, 
        $proxy = null, 
        $userAgent = null,
        $cookiesDir = null,
        $requestPauseSec = 1
    ) {
        // Create requester and define login and pause.
        $factory = new RequestersFactory();
        $this->requestBehaviour = $factory->createRequester($requesterType, $proxy, $userAgent);

        // Set up cookies file.
        if ($cookiesDir) {
          $this->requestBehaviour->setCookieFile($cookiesDir . "/" . $login);
        }

        $this->requestPauseSec = $requestPauseSec;
        $this->login = $login;

        // Attend login page.
        $loginFormPage = $this->attendPage(null);
                
        // Check if user already logged in.
        if (!$this->isUserloggedIn($loginFormPage)) {
            // User authorization.
            $postData = [
                'fr.login' => $login,
                'fr.password' => $pass,
                'fr.posted' => 'set',
                'fr.proto' => 1
            ];

            // Make login attempt.
            $loggedInPage = $this->sendForm(OkPagesEnum::LOGIN_PATH, $postData);

            // Check if user Frozen/Blocked.
            if (!$this->isUserloggedIn($loggedInPage)) {
              throw new OkToolsNotFoundException("User couldn't login.", $loggedIn->outertext);
            }
        }

        // Return page after login.
        return $this->lastPage;
    }
    
    /**
     * Check if user is already authorized.
     *
     * @param string $mobileFrontPage
     *   Ok front page to search logged in user markers.
     *
     * @throws OkToolsBlockedUserException
     *   Will be thrown on User blocked/frozen marker and if response is not successfull.
     * @throws OkToolsNotFoundException
     *   Will be thrown if no user marker found.
     * 
     * @return boolean
     *   TRUE if user is authorized, FALSE otherway.
     */
    protected function isUserloggedIn($mobileFrontPage) {
        $loggedIn = str_get_html($mobileFrontPage);
        $box = $loggedIn->find("#boxPage", 0);
        if ($box) {
            $status = $box->{"data-logloc"};
            switch ($status) {
                // User Blocked.
                case OkBlockedStatusEnum::USER_BLOCKED:
                    throw new OkToolsBlockedUserException("User has been blocked forever.", $login, $loggedIn->outertext);
                  break;
                // User Frozen.
                case OkBlockedStatusEnum::USER_VERIFICATION:
                case OkBlockedStatusEnum::USER_FROZEN:
                    throw new OkToolsBlockedUserException("User has been frozen status = {$status}", $login, $loggedIn->outertext);
                  break;
                // User is authorized.
                case "userMain":
                    // Return html page.
                    return true;
                // Couldn't login.
                case "main":
                    return false;
            }
        } else {
            // Some unexpected result.
            throw new OkToolsNotFoundException("Can't find user logged in marker.", $loggedIn->outertext);
        }
    }

    /**
     * Logout from OK.RU.
     */
    public function logout()
    {
        $postData = [
            'fr.posted' => 'set',
            'button_logoff' => 'Выйти'
        ];
        $this->sendForm(OkPagesEnum::LOGOUT_PATH, $postData);
    }

    /**
     * Last attended page getter.
     *
     * @return string
     *   Last page html string.
     */
    public function getLastAttendedPage()
    {
        return $this->lastPage;
    }

    /**
     * Get Last post token.
     *
     * @return string
     *   Post token.
     */
    public function getLastToken()
    {
        return $this->postToken;
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
     * Returns Gwt hash.
     *
     * @return string
     *   Gwt hash
     */
    public function getGwtDesktopHash()
    {
        return $this->gwtDesktopHash;
    }

    /**
     * Get periodic manager data.
     *
     * @return array
     *   Periodic manager data.
     */
    public function getPeriodicManagerData()
    {
        return $this->periodicDesktopManagerData;
    }

    /**
     * @TODO define content parsing/posting methods.
     */
//    public function getGroupContent();
//    public function postContentToGroup();

    /**
     * Requests a page with passed relative url.
     *
     * @param string $pageUrl.
     *   Relative url without slash.
     * @param boolean $desktop
     *   Use desktop base url or mobile flag.
     * @param array $headers
     *   Headers to set before request.
     *
     * @throws OkToolsCaptchaAppearsException
     *   Thrown when captcha appeared and solved.
     *
     * @return string
     *   Html result.
     */
    public function attendPage($pageUrl, $desktop = false, $headers = [])
    {
        // Define url.
        $baseUrl = $desktop ? self::D_URL : self::M_URL;

        // Should always wait before next request to emulate human. Delay 1-2 sec.
        $delay = ( rand(0, 100) / 100 ) + (float) $this->requestPauseSec;
        usleep($delay * 1000000);

        // Set headers before request.
        if (!empty($headers)) {
            $this->requestBehaviour->setHeaders($headers);
        }

        // Make a request And convert to DOM.
        $page = $this->requestBehaviour->requestGet($baseUrl . $pageUrl);
        $pageDom = str_get_html($page);
        
        // Check if Captcha
        if ($pageDom->find(".captcha_content", 0)) {
            throw new OkToolsCaptchaAppearsException("Captcha has appeared", $this->login, $pageDom->outertext);
        }

        // Save last page.
        $this->lastPage = $page;
        
        // Desktop mode routines.
        if ($desktop) {
            $this->desktopAttendRoutines($page);
        }

        return $page;
    }
    
    /**
     * Perform desktop GET request operations.
     * 
     * @param string $page
     *   Html Page after request.
     */
    protected function desktopAttendRoutines(&$page) {
      // Set gwt hash for desktop.
      if (!$this->gwtDesktopHash) {
          $this->gwtDesktopHash = $this->retrieveParameterFromPage("gwtHash");
      }

      // Set first postToken for desktop.
      $this->postToken = $this->retrievePostToken($page);

      // Set periodic manager data.
      $this->periodicDesktopManagerData = $this->retrievePeriodicManagerData();
    }

    /**
     * Retireve token from html page.
     *
     * @param string $attendedPage
     *   Html page in desktop.
     *
     * @return string
     *   Post Token string.
     */
    protected function retrievePostToken(&$attendedPage)
    {
        // For perfomance purposes extract part of html string with token.
        $tokenExpressionPos = strpos($attendedPage, "OK.tkn.set");
        if (!$tokenExpressionPos) {
            return "";
        }
        $tokenExprString = substr($attendedPage, $tokenExpressionPos, 100);
        
        return preg_replace("/(OK\.tkn\.set\(')(.*)('\);.*)/s", "$2", $tokenExprString);
    }

    /**
     * Retrieve gparameter by name from html.
     *
     * @param string $paramName
     *   Param name on a page.
     * @param string $page
     *   Page to retrieve parameter from.
     * 
     * @throws OkToolsNotFoundException
     *   If gwt hash has not been found.
     *
     * @return string
     *   Gwt hash
     */
    public function retrieveParameterFromPage($paramName, $page = null)
    {
        $pageDom = str_get_html($page ? $page : $this->lastPage);
        $paramPos = strpos($pageDom->outertext, $paramName);
        if (!$paramPos) {
            throw new OkToolsNotFoundException("Parameter not found, $paramName", $pageDom->outertext);
        }
        $paramString = substr($pageDom->outertext, $paramPos, 100);
        
        return preg_replace("/($paramName:\")([^\"]*)(.+)/", "$2", $paramString);
    }
    
    /**
     * Retrieves periodic manager data.
     *
     * @throws OkToolsNotFoundException
     *   If no gwt hash has been found or couldn't decode manager data. or no required param is found in init data.
     *
     * @return array
     *   Retrieved periodic manager data
     */
    private function retrievePeriodicManagerData()
    {
      // Retrieve init periodic info.
        $periodicManagerDataInit = $this->getInitPeriodicData();
        
        // Check for gwt hash.
        if (!$this->gwtDesktopHash) {
            throw new OkToolsNotFoundException("Couldn't find gwthash for periodic manager data request", $this->lastPage);
        }
        
        // Check if time values are present.
        if (!isset($periodicManagerDataInit['params']) && !isset($periodicManagerDataInit['params']['st.mpCheckTime'])) {
            throw new OkToolsNotFoundException("Couldn't find time values for periodic manager data request.", var_export($periodicManagerDataInit));
        }
        
        // Pause before request 1 - 5 seconds.
        sleep(rand(1, 5));
        
        // Packing the request.
        $postData = [
            "cpLCT" => 0,
            "tlb.act" => "news",
            "st.mpCheckTime" => $periodicManagerDataInit['params']['st.mpCheckTime'],
            "blocks" => "TD,NTF,MPC,VCP,FeedbackGrowl,FSC",
            "p_NLP" => 0
        ];
        
        // Send request.
        $response = $this->sendForm("push?cmd=PeriodicManager&gwt.requested={$this->gwtDesktopHash}&p_sId=0", $postData, true);
        $periodicManagerData = preg_replace("/\<\!--([^\<\!]+)--\>.+/", "$1", $response);
        
        // Decode data string.
        $periodicManagerData = json_decode($periodicManagerData, true);

        // Check if data exists.
        if (!$periodicManagerData) {
            throw new OkToolsNotFoundException("Couldn't decode periodic manager data.", $periodicManagerData);
        }
        
        return $periodicManagerData;
    }
    
    /**
     * Returns init periodic manager data.
     *
     * @throws OkToolsNotFoundException
     *   Thrown when No init periodic data retrieved.
     *
     * @return array
     *   Init data for periodic manager.
     */
    private function getInitPeriodicData()
    {
        // Convert page to dom.
        $pageDom = str_get_html($this->lastPage);
        $periodicData = $pageDom->find("#hook_PeriodicHook_PeriodicManager", 0);
        if (!$periodicData) {
            throw new OkToolsNotFoundException("Couldn't find periodic data manager info", $pageDom->outertext);
        }
        
        // Decode init data for periodic manager
        $periodicManagerDataInit = str_replace(["<!--", "-->"], "", $periodicData->innertext);
        $periodicManagerDataInit = json_decode($periodicManagerDataInit, true);

        // Check if data exists.
        if (!$periodicManagerDataInit) {
            throw new OkToolsNotFoundException("Coouldn't decode init periodic manager data.", $periodicData->innertext);
        }

        return $periodicManagerDataInit;
    }
    

    /**
     * Sends post request.
     *
     * @param string $url
     *   Url to sned request to.
     * @param array $data
     *   Post data.
     * @param boolean $desktop
     *   Use desktop base url or mobile flag.
     * @param array $headers
     *   Headers to set before request.
     *
     * @return string
     *   Html page.
     */
    public function sendForm($url, $data, $desktop = false, $headers = [])
    {
        // Define url.
        $baseUrl = $desktop ? self::D_URL : self:: M_URL;
      
        // Should always wait before next request to emulate human. Delay 1-2 sec.
        $delay = ( rand(0, 100) / 100 ) + (float) $this->requestPauseSec;
        usleep($delay * 1000000);
        
        // Set token header for desktop before request.
        if ($desktop) {
            $headers['tkn'] = $this->getLastToken();
        }
        
        // Set headers before request.
        if (!empty($headers)) {
            $this->requestBehaviour->setHeaders($headers);
        }

        // Send request and remember last page.
        $page =  $this->requestBehaviour->requestPost($baseUrl . $url, $data);
        $this->lastPage = $page;
        
        // Set newly retrieved post token from headers.
        $headers = $this->requestBehaviour->getHeaders();
        if (isset($headers['TKN'])) {
            $this->postToken = trim($headers['TKN']);
        }

        return $page;
    }

    /**
     * Logging for desktop operations.
     *
     * @param array $logData
     *   Data to log.
     */
    public function gwtLog($logData)
    {
        // Pause before log 1 - 4 sec.
        sleep(rand(1, 4));
        
        // Send log request.
        $data['a'] = json_encode($logData);
        $this->sendForm("gwtlog", $data, true);
    }
}
