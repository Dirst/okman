<?php

namespace Dirst\OkTools;

use Dirst\OkTools\Requesters\RequestCurl;
use SmsActivator\SmsActivator;
use SmsActivator\Service\smsActivate;
use SmsActivator\Service\getSms;
use GuzzleHttp\Client;
use Dirst\OkTools\Exceptions\OkToolsDomItemNotFoundException;
use Dirst\OkTools\Exceptions\Activators\OkToolsUnfreezeException;

/**
 * Class to unfreeze user account.
 *
 * @author dirst
 */
class OkToolsUnfreeze
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
    public function __construct($activatorKey, $proxy = null, $type = self::SMSACTIVATE)
    {
        $client= new Client();
        $activator = $type == self::SMSACTIVATE ? new smsActivate($activatorKey) : new getSms($activatorKey);
        $this->activator = new SmsActivator($activator, $client);
        $this->requester = new RequestCurl($proxy, $this->userAgent);
    }

  /**
   * Activate account and get new phone/password pair.
   *
   * @param string $verificationUrl
   *   Verification url.
   * @param int $waitInterval
   *   Wait interval.
   *
   * @return array
   *   Phone and new password.
   */
    public function unfreezeAccount($verificationUrl, $waitInterval = 300)
    {
      
        $request = $this->requester->requestGet($verificationUrl);
        $domHumanCheck = str_get_html($request);
      
        // Check if phone form already exists.
        if (!($form = $domHumanCheck->find("form", 0))) {
            // Get link to chose another phone.
            if (!($choseAnotherPhone = $domHumanCheck->find('a.ai', 0))) {
                throw new OkToolsDomItemNotFoundException("Couldn't get link to chose new number.", $request);
            }

            // Get page with form for new phone.
            $request = $this->requester->requestGet($this->okUrl . $choseAnotherPhone->href);
            $domHumanCheck = str_get_html($request);
            if (!($form = $domHumanCheck->find("form", 0))) {
                throw new OkToolsDomItemNotFoundException("Couldn't get form to insert new phone.", $request);
            }
        }

        // Get new phone.
        $result = $this->getNewPhone();

        // Send new phone.
        $formData = [
        "st.posted" => "set",
        "st.country" => $this->ruCountry,
        "st.phn" => ltrim($result['phone'], "7"),
        "button_continue" => "Отправить код"
        ];
        $form = $this->getNextForm($this->okUrl . $form->action, $formData);

        // Get phone Code.
        $code = $this->getPhoneCode($result['id'], $waitInterval);
      
        // Send phone code.
        $formData = [
        "st.posted" => "set",
        "st.smsCode" => $code,
        "button_continue" => "Подтвердить код"
        ];
        $form = $this->getNextForm($this->okUrl . $form->action, $formData);
      
        // Set new password.
        $password = md5(uniqid()) . "_";
        $formData = [
        "st.posted" => "set",
        "st.splnk" => null,
        "st.password" => $password,
        "button_submit" => "Сохранить"
        ];
        $request = $this->requester->requestPost($this->okUrl . $form->action, $formData);
        if (strpos($this->requester->getHeaders()['Location'], 'st.verificationResult=ok') !== false) {
            $this->activator->setComplete($result['id']);
            return ["phone" => $result['phone'], 'password' => $password];
        } else {
            $this->activator->setCancel($result['id']);
            throw new OkToolsUnfreezeException("Couldn't retrieve success status", $request);
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
    protected function getNextForm($url, $formData)
    {
        // Get phone code enter page.
        $request = $this->requester->requestPost($url, $formData);
        $domHumanCheck = str_get_html($request);
      
        if (!($form = $domHumanCheck->find("form", 0))) {
            throw new OkToolsDomItemNotFoundException("Couldn't retrieve next form page.", $request);
        } else {
            return $form;
        }
    }
}
