<?php

namespace Dirst\OkTools;

use Dirst\OkTools\Requesters\RequestCurl;
use SmsActivator\SmsActivator;
use SmsActivator\Service\smsActivate;
use SmsActivator\Service\getSms;
use GuzzleHttp\Client;
use Dirst\OkTools\Exceptions\OkToolsDomItemNotFoundException;
use Dirst\OkTools\Exceptions\Activators\OkToolsUnfreezeException;
use Dirst\OkTools\Exceptions\OkToolsCaptchaAppearsException;
use Dirst\OkTools\Exceptions\Activators\OkToolsRegisterException;

/**
 * Class to register account.
 *
 * @author dirst
 */
class OkToolsRegister
{

  // @var string useragent to use.
    protected $userAgent = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.31 (KHTML, like Gecko)"
      . "Chrome/60.0.1222.90 Safari/537.31";
  
  // @var RequestCurl requester.
    protected $requester;
  
  // @var smsActivate activator.
    protected $activator;
  
  // @var string mobile page of OK.
    protected $okUrl = "https://m.ok.ru";
  
  // @var string russian country code.
    protected $ruCountry = "10414533690";

    // Service type.
    const SMSACTIVATE = 0;
    const GETSMS = 1;
    
    // @var smple_html_dom object.
    protected $innerDom;
    
    /**
     * Construct Unfreeze object.
     *
     * @param string $activatorKey
     *   Activator key.
     * @param string $proxy
     *   Proxy.
     * @param string $type
     *   Activate type.
     */
    public function __construct($activatorKey, $proxy = null, $userAgent = null, $type = self::SMSACTIVATE)
    {
        $client= new Client();
        $this->userAgent = $userAgent ? $userAgent : $this->userAgent;
        $activator = $type == self::SMSACTIVATE ? new smsActivate($activatorKey) : new getSms($activatorKey);
        $this->activator = new SmsActivator($activator, $client);
        $this->requester = new RequestCurl($proxy, $this->userAgent);
    }

  /**
   * Register account and get new phone/password pair.
   *
   * @param string $verificationUrl
   *   Verification url.
   * @param int $waitInterval
   *   Wait interval.
   *
   * @return array
   *   Phone and new password.
   */
    public function registerAccount($waitInterval = 300)
    {
      // Generate name.
      $names = file_get_contents(__DIR__ . "/files/names.txt");
      $names = explode("\n", $names);
      $name = $names[array_rand($names)];
      
      // Generate surname.
      $surnames = file_get_contents(__DIR__ . "/files/surnames.txt");
      $surnames = explode("\n", $surnames);
      $surname = $surnames[array_rand($surnames)];
      
      // Get main page.
      $request = $this->makeRequest($this->okUrl);
      $domHumanCheck = str_get_html($request);

      // Register page.
      $register = $domHumanCheck->find("div[class='base-button __modern __full-width'] a", 0)->href;
      $request = $this->makeRequest($this->okUrl . $register);
      $domHumanCheck = str_get_html($request);

//      file_put_contents(__DIR__."/reg1.html", $domHumanCheck);

      // Get new phone.
      $result = $this->getNewPhone();

      // Phone prefix chose
      if (strpos($result['phone'], "380") === 0) {
        $prefix = "380";
      } else {
        $prefix = "7";
      }
      
      // Send new phone.
      $formData = [
        "rfr.posted" => "set",
        "rfr.phonePrefix" => "+" . $prefix,
        "rfr.countryCode" => 'ru',
        "rfr.countryName" => "Россия",
        "rfr.phone" => preg_replace("/^\+?$prefix/", "", $result['phone']),
        "getCode" => "Получить код"
      ];

      $form = $this->getNextForm($this->okUrl . $domHumanCheck->find("form.recovery_form", 0)->action, $formData);
//      file_put_contents(__DIR__."/reg2.html", $this->innerDom); 

      // Get phone Code.
      $code = $this->getPhoneCode($result['id'], $waitInterval);

      $formData = [
        "rfr.posted" => "set",
        "rfr.smsCode" => $code,
      ];
      
      $form = $this->getNextForm($this->okUrl . $form->action, $formData, "form.recovery_form");
//      file_put_contents(__DIR__."/reg3.html", $this->innerDom);
      
      $password = md5(uniqid());
      
      // Set up profile.
      $formData = [
        "rfr.posted" => 'set',
        "rfr.name" => $name,
        "rfr.surname" => $surname,
        "rfr.birthday" => rand(1, 20),
        "rfr.month" => rand(3, 6),
        "rfr.year" => rand(1984, 1989),
        "rfr.gender" => 2, // Female.
        "rfr.country" => $this->ruCountry,
        "rfr.password" => $password,
        "saveProfileData" => "Далее"
      ];

      $form = $this->getNextForm($this->okUrl . $form->action, $formData);
      
      //Clear memory.
      $this->innerDom->clear();

      // Check success status.
      if (strpos($this->requester->getHeaders()['Location'], 'st.cmd=newRegUploadPhoto') !== false) {
          $this->activator->setComplete($result['id']);
          return ["phone" => $result['phone'], 'password' => $password];
      } else {
          $this->activator->setCancel($result['id']);
          throw new OkToolsRegisterException("Couldn't retrieve success status on register", $form);
      }
    }

  /**
   * Retrieve operation id and new phone.
   *
   * @throws OkToolsUnfreezeException
   *   Thrown when it is impossible to get new number.
   *
   * @return array
   *   array with [id, phone] keys.
   */
    protected function getNewPhone()
    {
        // Check if balance less then required for one number.
        $balance = str_replace("ACCESS_BALANCE:", "", $this->activator->getBalance());
        if ($balance < 4) {
            throw new OkToolsUnfreezeException("Balance is not enough", $balance);
        }
    
        $number = $this->activator->getNumber("ok");
        if ($number == "NO_NUMBERS") {
            throw new OkToolsUnfreezeException("No Numbers for OK", $number);
        } elseif (strpos($number, "ACCESS_NUMBER") === false) {
            throw new OkToolsUnfreezeException("Problem to retrieve new number", $number);
        }
    
        // If all is fine
        $response = explode(":", $number);
    
        // Return operation id and phone.
        return ["id" => $response[1], "phone" => $response[2]];
    }

  /**
   * Get code from sms.
   *
   * @param int $id
   *   Operation id to retrieve code for.
   * @param int $waitInterval
   *   How long to wait in seconds before stop trying to get the code.
   *
   * @throws OkToolsUnfreezeException
   *   Thrown when some problems on code retrieve occured.
   *
   * @return string
   *   Code string.
   */
    protected function getPhoneCode($id, $waitInterval = 300)
    {
        // Set sms sent status.
        $ready = $this->activator->setReady($id);
        if ($ready != "ACCESS_READY") {
            throw new OkToolsUnfreezeException("Problem set ready status", $ready);
        }
    
        // Waiting for code.
        $time = time();
        while ($time + $waitInterval > time()) {
            $status = $this->activator->getStatus($id);
            if ($status == "STATUS_WAIT_CODE") {
                continue;
            } elseif (strpos($status, "STATUS_OK") !== false) {
                return str_replace("STATUS_OK:", "", $status);
            } else {
                throw new OkToolsUnfreezeException("Couldn't retrieve sms code.", $status);
            }
        }
    
        throw new OkToolsUnfreezeException("Time's up on code retrieve tries.", $status);
    }

  /**
   * Submit current form and retrieve next form.
   *
   * @param string $url
   *   Current form action url.
   * @param array $formData
   *   Data to send with form.
   *
   * @throws OkToolsDomItemNotFoundException
   *   Thrown when there is no form on retrieved page.
   *
   * @return array
   *   Form got after request.
   */
    protected function getNextForm($url, $formData, $formSelector = "form")
    {
        // Get phone code enter page.
        $request = $this->makeRequest($url, $formData, "post");
        
        if ($this->innerDom) {
          $this->innerDom->clear();
        }

        $this->innerDom = str_get_html($request);
      
        if (!($form = $this->innerDom->find($formSelector, 0))) {
            $this->innerDom->clear();
            throw new OkToolsDomItemNotFoundException("Couldn't retrieve next form page.", $request);
        } else {
            return $form;
        }
    }
    
    /**
     * Make request.
     *
     * @param string $url
     *   Url to send to.
     * @param array $formData
     *   Data array to post/get
     * @param string $type
     *   Request type.
     *
     * @return string
     *   Response string.
     * 
     * @throws OkToolsCaptchaAppearsException
     */
    protected function makeRequest($url, $formData = [], $type = "get")
    {
        // Send request.
        if ($type == "get") {
            $result = $this->requester->requestGet($url, $formData);
        } else {
            $result = $this->requester->requestPost($url, $formData);
        }
        
        $mobilePage = str_get_html($result);
        
        // Check if captcha shows up.
        if ($mobilePage->find("#captcha", 0)) {
            $mobilePage->clear();
            throw new OkToolsCaptchaAppearsException("Captcha on request", null, $result, $this->requester);
        }
  
        $mobilePage->clear();
        return $result;
    }
}
