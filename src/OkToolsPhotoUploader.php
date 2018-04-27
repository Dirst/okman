<?php

namespace Dirst\OkTools;

use Dirst\OkTools\Exceptions\PhotoUploader\OkToolsPhotoUploaderException;

/**
 * Photo Uploader class.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 2.0
 */
class OkToolsPhotoUploader extends OkToolsBaseControl
{
    /**
     * Get upload data array.
     *
     * @return array
     *   Upload data.
     *
     * @throws OkToolsGroupGetPhotosUploadDataException
     *   Throws when no upropriate data has been recieved.
     */
    public function getPhotoUploadData($groupId = null)
    {
        $methods = [];
        $params = [
          "count" => "1"
        ];

        if ($groupId) {
            $params['gid'] = $groupId;
        }
        
        $methods[] = [
            "method" => "photosV2.getUploadUrl",
            "params" => $params
        ];

        $form = [
            "application_key" => $this->OkToolsClient->getAppKey(),
            "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key'],
            "methods" => json_encode($methods),
            "id" => "photosV2.getUploadUrl",
            "format" => "json"
        ];
        
        // Send request.
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . "/batch/execute",
            $form,
            "post"
        );
        
        if (isset($result['photosV2_getUploadUrl_response']) && isset($result['photosV2_getUploadUrl_response']['upload_url'])) {
            return $result['photosV2_getUploadUrl_response'];
        } else {
            throw new OkToolsPhotoUploaderException(
              "Couldn't get upload data to perform photo upload",
              var_export($result, true)
            );
        }
    }

    /**
     * Upload photo via retrieved url.
     *
     * @param string $uploadUrl
     *   Upload url on ok side.
     * @param string $imagePath
     *   Absolute url to photo on server or in the web.
     *
     * @return array
     *   Uploaded photo array.
     *
     * @throws OkToolsGroupUploadPhotosException
     *   Thrown when couldn't upload photo.
     */
    public function uploadPhotoViaRetrievedUrl($uploadUrl, $imagePath)
    {
        $result = $this->OkToolsClient->makeRequest(
            $uploadUrl,
            file_get_contents($imagePath),
            "post",
            false,
            ["Content-Type" => "application/octet-stream"]
        );

        if (isset($result['photos'])) {
            return $result['photos'];
        } else {
            throw new OkToolsPhotoUploaderException("Couldn't upload photo", var_export($result, true));
        }
    }

    /**
     * 
     * @param type $uploadedPhotoId
     * @param type $uploadedToken
     * @return type
     * @throws OkToolsGroupGetPhotosUploadDataException
     */
    public function getAssignedPhotoId($uploadedPhotoId, $uploadedToken)
    {
        $photos = [];
        $photos[] = [
            "token" => $uploadedToken,
            "photo_id" => $uploadedPhotoId,
            "comment" => null
        ];
        
        $form = [
            "application_key" => $this->OkToolsClient->getAppKey(),
            "session_key" => $this->OkToolsClient->getLoginData()['auth_login_response']['session_key'],
            "photos" => json_encode($photos),
        ];
        
        // Send request.$this->OkToolsClient->getAppKey()
        $result = $this->OkToolsClient->makeRequest(
            $this->OkToolsClient->getApiEndpoint() . "/photosV2/commit",
            $form,
            "get"
        );
        
        if (isset($result['photos']) && isset($result['photos'][0]) && $result['photos'][0]['status'] == "SUCCESS") {
            return $result['photos'][0]['assigned_photo_id'];
        } else {
            throw new OkToolsPhotoUploaderException(
              "Couldn't get assigned id",
              var_export($result, true)
            );
        }
    }
}
