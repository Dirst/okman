<?php

namespace Dirst\OkTools;

use Dirst\OkTools\OkToolsPhotoUploader;

use Dirst\OkTools\Exceptions\Likes\OkToolsLikesDoneBeforeException;
use Dirst\OkTools\Exceptions\Likes\OkToolsLikesException;
use Dirst\OkTools\Exceptions\Likes\OkToolsLikesNotPermittedException;
use Dirst\OkTools\Exceptions\OkToolsAccountUsersSearchException;
use Dirst\OkTools\Exceptions\Invite\OkToolsInviteCantBeDoneException;
use Dirst\OkTools\Exceptions\Invite\OkToolsInviteDoneBeforeException;
use Dirst\OkTools\Exceptions\Invite\OkToolsInviteAcceptorNotFoundException;
use Dirst\OkTools\Exceptions\OkToolsDomItemNotFoundException;
use Dirst\OkTools\Exceptions\Invite\OkToolsInviteFailedException;
use Dirst\OkTools\Exceptions\Invite\OkToolsInviteTooOftenException;
use Dirst\OkTools\Exceptions\OkToolsSettingChangeException;
use Dirst\OkTools\Exceptions\Invite\OkToolsInviteGroupLimitException;

/**
 * ACcount control class.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 2.0
 */
class OkToolsAccountControl extends OkToolsBaseControl
{
    /**
     * Retrieve user photos.
     *
     * @param int $userId
     *   Api user Id.
     * @param int $count
     *   Photos count to retrieve.
     *
     * @return array
     *   Photos array or empty array.
     */
    public function getUserPhotos($userId, $count)
    {
        $form = [
        "application_key" => $this->OkToolsClient->getAppKey(),
        "count" => $count,
        "fields" => "user.uid,user.first_name,user.last_name,user.name,user.gender,user.pic128x128,user.age,"
        . "user.location,user.vip,user.premium,user.show_lock,user.birthday,photo.id,photo.album_id,"
        . "photo.pic128x128,photo.pic240min,photo.pic640x480,photo.pic1024x768,photo.picmp4,"
        . "photo.like_summary,photo.discussion_summary,photo.reshare_summary,photo.user_id,photo.standard_width,"
        . "photo.standard_height,photo.like_allowed,photo.delete_allowed,photo.mark_allowed,photo.mark_allowed,"
        . "photo.modify_allowed,photo.text,photo.context,album.aid,album.title,album.main_photo,album.like_summary,"
        . "album.comments_count,album.photos_count",
        "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key'],
        "uid" => $userId,
        ];
        $photos = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . "/photos/getStream",
            $form
        );

      // Check if photo exists
        if (!count(@$photos['entities']['photos'])) {
            return [];
        } else {
            return $photos['entities']['photos'];
        }
    }

    /**
     * Like one photo retireved with getUserPhotos.
     *
     * @param array $photoArray
     *   Photo array getted from getUserPhotos method.
     *
     * @throws OkToolsLikesNotPermittedException
     *   Like is not permitted for this photo.
     * @throws OkToolsLikesDoneBeforeException
     *   When Like has been done earlier for this user from this account.
     * @throws OkToolsLikesException
     *   When like has not been performed.
     */
    public function likeUserPhoto($photoArray)
    {
      // Check if like is possible
        if (!$photoArray['like_summary']['like_possible']) {
            throw new OkToolsLikesNotPermittedException(
                "Not possible to like this photo",
                var_export($photoArray, true)
            );
        }

      // Check if photo has not been liked before.
        if ($photoArray['like_summary']['self']) {
            throw new OkToolsLikesDoneBeforeException("Like has been done before.", var_export($photoArray, true));
        }
      
        $form = [
          "application_key" => $this->OkToolsClient->getAppKey(),
          "like_id" => $photoArray['like_summary']['like_id'],
          "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key'],
        ];
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . "/like/like",
            $form
        );

      // Check if like has been done.
        if (!(isset($result['summary']) && $result['summary']['self'])) {
            throw new OkToolsLikesException("Photo has not been liked", var_export($result, true));
        }
    }

    /**
     * Get users from OK search.
     *
     * @param int $count
     *   Users count to retrieve.
     * @param string $anchor
     *   Page anchor.
     * @param int $ageFrom
     *   Min age. Could be started from 14 only.
     * @param int $ageTo
     *   Max age. Could be 100 maximum.
     * @param boolean $isOnline
     *   Should users be online only.
     * @param string $gender
     *   m - male or f - female.
     * @param boolean $isSingle
     *   Retrieve users that are sinles only.
     * @param array $countries
     *   Countries ids array.
     * @param string $city
     *   City
     *
     * @return array
     *   Users array with anchor and has_more.
     *
     * @throws OkToolsAccountUsersSearchException
     *   Thrown if not appropriate response has been received.
     */
    public function getUsersFromSearch(
        $count = 100,
        $anchor = null,
        $ageFrom = 14,
        $ageTo = 99,
        $isOnline = false,
        $gender = null,
        $isSingle = false,
        $countries = [],
        $city = null
    ) {

        $form = [
          "application_key" => $this->OkToolsClient->getAppKey(),
          "count" => $count,
          "fields" => "user.last_online_ms,user.show_lock,user.pic320min,user.pic128x128,user.last_name,"
            . "user.private,user.name,user.birthday,user.gender,user.pic190x190,user.premium,user.first_name,user.uid,"
            . "user.location,user.can_vcall,user.pic240min,user.vip,user.age,user.can_vmail,user.online,app.*",
          "types" => "USER",
          "format" => "json",
          "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key']
        ];

        // Page anchor.
        if ($anchor) {
            $form['anchor'] = $anchor;
        }
        
        // Age.
        $filters['type'] = "user";
        $filters['min_age'] = $ageFrom;
        $filters['max_age'] = $ageTo;
        
        // Gender
        if ($gender !== null) {
            if ($gender == "m") {
                $filters['gender_male'] = true;
            } else {
                $filters['gender_female'] = true;
            }
        }
  
        // Single.
        if ($isSingle) {
            $filters['isSingle'] = true;
        }
        
        // Online
        if ($isOnline) {
            $filters['isOnline'] = true;
        }

        // City.
        if ($city) {
            $filters['city'] = $city;
        }

        // Countries.
        if (!empty($countries)) {
            $filters['country_ids'] = $countries;
        }
        
        // Encode filters to json string.
        $form['filters']= json_encode([$filters]);
        
        // Perform request.
        $users = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . "/search/quick",
            $form,
            "post"
        );

        // Check if users have been retrieved;
        if (isset($users['found']) && !empty($users['found'])) {
            unset($users['found']);
            return $users;
        } elseif (empty($users['found'])) {
            return [];
        } else {
            throw new OkToolsAccountUsersSearchException(
                "Couldn't find users with current parameters",
                var_export($users, true)
            );
        }
    }

        /**
     * Perform invite operation.
     *
     * @notice Currently assume that account is moderator for group to invite to.
     *
     * @param int $userId
     *   User id to invite. Id should be in api format.
     * @param int $groupId
     *   Group Id to invite to.
     *
     * @throws OkToolsInviteCantBeDoneException
     *   Invite couldn't be done as user blocked this ability or account is blocked.
     * @throws OkToolsInviteAcceptorNotFoundException
     *   Couldn't find acceptor group. Group has been blocked or there is no group on this page.
     * @throws OkToolsInviteDoneBeforeException
     *   Invite has been done before for this user.
     * @throws OkToolsDomItemNotFoundException
     *   Couldn't find needed dom element.
     * @throws OkToolsInviteFailedException
     *   Invite confirm request has not been finalyzed correctly.
     */
    public function inviteUserToGroup($userId, $groupId)
    {
                // Form send.
        $form = [
          "application_key" => $this->OkToolsClient->getAppKey(),
          "fid" => $userId,
          "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key'],
          "app.params" => "x"
        ];

        // Get mobile page to invite user.
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getMobileVersionUrl() . "/api/user_invite_to_group",
            $form,
            "get",
            true
        );

        // Convert to dom.
        $mobilePage = str_get_html($result);
        
        // Get groups list.
        if (!($list = $mobilePage->find("#groups-list", 0))) {
            $mobilePage->clear();
            throw new OkToolsInviteCantBeDoneException("User couldn't be invited or account is blocked.", $result);
        }

        // Check if already invited.
        if (!($groups = $list->find("li[id*=$groupId]", 0))) {
            $mobilePage->clear();
            throw new OkToolsInviteAcceptorNotFoundException("Couldn't find acceptor group.", $result);
        }

        // Check if already invited.
        if ($groups->find('a[class*="group-select __disabled"]', 0)) {
            $mobilePage->clear();
            throw new OkToolsInviteDoneBeforeException("User has been already invited before.", $result);
        }
        
        // Sleep pause.
        sleep(rand(0, 3));
        
        // Confirm invite page
        $confirmInvitePage = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getMobileVersionUrl() . $groups->find("a", 0)->href,
            [],
            "get",
            true
        );
        
        $mobilePage->clear();
        $confirmInvitePage = str_get_html($confirmInvitePage);

        // Check if form exists.
        if (!$confirmInvitePage->find("form", 0)) {
            $confirmInvitePage->clear();
            throw new OkToolsDomItemNotFoundException("Couldn't find invite html form", $confirmInvitePage);
        }
        
        // Sleep pause.
        sleep(rand(1, 3));

        // Send invite request.
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getMobileVersionUrl() . $confirmInvitePage->find("form", 0)->action,
            ["fr.posted" => "set", "button_send" => "Отправить"],
            "post",
            true
        );
        $confirmInvitePage->clear();
        $mobilePage = str_get_html($result);
        
        // Check if invite is not too often.
        if (strpos($result, "Вы слишком часто приглашаете людей в группы") !== FALSE) {
            $mobilePage->clear();
            throw new OkToolsInviteTooOftenException(
                "Invite is too often.",
                $result
            );
        }
        
        // Check if group limit.
        if (strpos($result, "В эту группу рассылается слишком много приглашений") !== FALSE) {
            $mobilePage->clear();
            throw new OkToolsInviteGroupLimitException(
                "Group limit exceed.",
                $result
            );
        }
        
        // Check groups lits - success criteria.
        if (!$mobilePage->find("#groups-list", 0)) {
            $mobilePage->clear();
            throw new OkToolsInviteFailedException(
                "Couldn't finalize invite. No groups-list fiund as it should.",
                $result
            );
        }
        $mobilePage->clear();
    }

    /**
     * Change account language.
     *
     * @param type $langCode
     * @throws OkToolsDomItemNotFoundException
     * @throws OkToolsSettingChangeException
     */
    public function changeLanguage($langCode = "ru") {
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getMobileVersionUrl() . "/api/goto",
            [
              "url" => "/settings?app.params=x",
              "application_key" => $this->OkToolsClient->getAppKey(),
              "uid" => $this->OkToolsClient->getAccountApiId(),
              "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key'],
            ],
            "get",
            true
        );

        $mobilePage = str_get_html($result);
        
        // Search for change language link.
        if (!($changeLink = $mobilePage->find('a[href*="st.cmd=langSelector"]', 0))) {
            $mobilePage->clear();
            throw new OkToolsDomItemNotFoundException(
                "Couldn't find change language link.",
                $result
            );
        }
        
        // Change lang page.
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getMobileVersionUrl() . $changeLink->href,
            [],
            "get",
            true
        );
        
        $mobilePage->clear();
        $mobilePage = str_get_html($result);

        // Search for needed language link.
        if (!($langLink = $mobilePage->find('a[href*="st.lang=' . $langCode . '"]', 0))) {
            $mobilePage->clear();
            throw new OkToolsDomItemNotFoundException(
                "Couldn't find needed language button on langs list.",
                $result
            );
        }
        
        // Click on language to set it.
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getMobileVersionUrl() . $langLink->href,
            [],
            "get",
            true
        );
        
        $mobilePage->clear();

        // Check if we on settings page again.
        if (strpos($this->OkToolsClient->getRequestBehaviour()->getHeaders()['Location'], 'st.cmd=userSettings') === false) {
            throw new OkToolsSettingChangeException(
                "Couldn't change language.",
                $result
            );
        }

    }
 
    /**
     * Change profile settings 
     *
     * @param type $day
     * @param type $month
     * @param type $year
     * @param type $name
     * @param type $surname
     * @param type $gender
     * @throws OkToolsDomItemNotFoundException
     * @throws OkToolsSettingChangeException
     */
    public function changeProfile(
        $day = false,
        $month = false,
        $year = false,
        $name = false,
        $surname = false,
        $gender = false // 1 - m, 2 - f
    ) {
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getMobileVersionUrl() . "/api/goto",
            [
              "url" => "/settings?app.params=x",
              "application_key" => $this->OkToolsClient->getAppKey(),
              "uid" => $this->OkToolsClient->getAccountApiId(),
              "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key'],
            ],
            "get",
            true
        );

        $mobilePage = str_get_html($result);
        
        // Search for change personal link.
        if (!($settingsPersonalLink = $mobilePage->find('a[href*="st.cmd=userSettingsPersonal"]', 0))) {
            $mobilePage->clear();
            throw new OkToolsDomItemNotFoundException(
                "Couldn't find change personal link.",
                $result
            );
        }
        
        // Change personal page.
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getMobileVersionUrl() . $settingsPersonalLink->href,
            [],
            "get",
            true
        );
        
        $mobilePage->clear();
        $mobilePage = str_get_html($result);
        
        // Search for change profile link.
        if (!($settingsProfilelLink = $mobilePage->find('a[href*="st.cmd=userSettingsProfile"]', 0))) {
            $mobilePage->clear();
            throw new OkToolsDomItemNotFoundException(
                "Couldn't find change profile form link.",
                $result
            );
        }

        // Change profile form.
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getMobileVersionUrl() . $settingsProfilelLink->href,
            [],
            "get",
            true
        );
        
        $mobilePage->clear();
        $mobilePage = str_get_html($result);
        if ( !($mobilePage->find('form[action*="bk=UserSettingsProfile"]', 0)) ) {
            $mobilePage->clear();
            throw new OkToolsDomItemNotFoundException(
                "Couldn't find profile edit form.",
                $result
            );
        }
        
        // Get gender
        if (!$gender) {
          foreach ($mobilePage->find("input[name*='fr.gender']") as $genderElement) {
              if (isset($genderElement->checked)) {
                $gender = $genderElement->value;
                break;
              }
          }
        }

        $surname = $surname ? $surname : preg_replace("/[^a-zA-ZА-Яа-я0-9\s]/", "", $mobilePage->find("input[name*='fr.surname']", 0)->value);
        $surname = preg_replace("/(.+)[-\s].+/", "$1", $surname);
        $data = [
          "fr.posted" => "set",
          "fr.name" => $name ? $name : preg_replace("/[^a-zA-ZА-Яа-я0-9\s]/", "", $mobilePage->find("input[name*='fr.name']", 0)->value),
          "fr.surname" => trim($surname) == "" ? "-" : $surname,
          "fr.gender" => $gender,
          "fr.birthday" => $day ? $day : $mobilePage->find("select[name*='fr.birthday']", 0)->find("option[selected='selected']", 0)->value,
          "fr.bmonth" => $month ? $month : $mobilePage->find("select[name*='fr.bmonth']", 0)->find("option[selected='selected']", 0)->value,
          "fr.byear" => $year ? $year : $mobilePage->find("select[name*='fr.byear']", 0)->find("option[selected='selected']", 0)->value,
          "fr.country" => 10414533690,
          "fr.city" => "Москва",
          "fr.city" => "Москва",
          "fr.cityId" => 10407994421,
          "button_save" => "Сохранить"
        ];
        
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getMobileVersionUrl() . $mobilePage->find('form[action*="bk=UserSettingsProfile"]', 0)->action,
            $data,
            "post",
            true
        );
        
        $mobilePage->clear();

        // Check if we on settings page again.
        if (strpos($this->OkToolsClient->getRequestBehaviour()->getHeaders()['Location'], 'st.cmd=userSettingsPersonal') === false) {
            $mobilePage->clear();
            throw new OkToolsSettingChangeException(
                "Couldn't change profile.",
                $result
            );
        }
        
    }

    /**
     * Upload photo by url.
     *
     * @param string $imagePath
     *   Path to image to upload.
     *
     * @return boolean
     *   Status if avatar has been setted.
     */
    public function setUpAvatar($imagePath) {
      $photoUploader = new OkToolsPhotoUploader($this->OkToolsClient);
      $uploadData = $photoUploader->getPhotoUploadData();
      $photosData = $photoUploader->uploadPhotoViaRetrievedUrl($uploadData['upload_url'], $imagePath);
      $id = $photoUploader->getAssignedPhotoId(current($uploadData['photo_ids']), current($photosData)['token']);
      return $this->setAvatarByPhotoId($id);
    }

    /**
     * 
     * @param type $photoId
     * @return boolean
     * @throws OkToolsGroupGetPhotosUploadDataException
     */
    public function setAvatarByPhotoId($photoId) {
        $form = [
            "application_key" => $this->OkToolsClient->getAppKey(),
            "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key'],
            "photo_id" => $photoId,
        ];
        
        // Send request.$this->OkToolsClient->getAppKey()
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . "/users/setMainPhoto",
            $form,
            "get"
        );
 
        if ($result === true) {
            return true;
        } else {
            throw new OkToolsAccountAvatarSetUpException(
              "Couldn't set avatar by photo id",
              var_export($result, true)
            );
        }
    }
}
