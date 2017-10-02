<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Dirst\OkTools;

use Dirst\OkTools\Exceptions\Likes\OkToolsLikesDoneBeforeException;
use Dirst\OkTools\Exceptions\Likes\OkToolsLikesException;
use Dirst\OkTools\Exceptions\Likes\OkToolsLikesNotPermittedException;

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
        if ($photoArray['like_summary']['like_possible']) {
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
            $this->OkToolsClient->getApiEndpoint() . "/api/like/like",
            $form
        );

      // Check if like has been done.
        if (!(isset($result['summary']) && $result['summary']['self'])) {
            throw new OkToolsLikesException("Photo has not been liked", var_export($result, true));
        }
    }
}
