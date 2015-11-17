<?php

/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
class sonnb_XenGallery_ControllerPublic_XenGallery_Photo extends sonnb_XenGallery_ControllerPublic_XenGallery_Content
{
	public function actionAlbumSelect()
	{
		$conditions = array();
		$maxPhotos = $this->_getPhotoModel()->getPhotoCountLimit();
		if ($maxPhotos)
		{
			$conditions['photo_count'] = array('<', $maxPhotos);
		}
		
		return $this->_actionAlbumSelect(sonnb_XenGallery_Model_Photo::$contentType, $conditions);
	}

	public function actionRotate()
	{
		$this->_assertRegistrationRequired();
		
		list($content, $album) = $this->_getPhotoOrError();
		
		if (!$this->_getControllerContentModel()->canEditContent($content))
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_you_do_not_have_permission_to_edit_this_photo');
		}
		
		if ($this->_request->isPost())
		{
			$angle = $this->_input->filterSingle('angle', XenForo_Input::INT);

			if ($angle)
			{
				//To prevent timeout on slow server
				@set_time_limit(0);
				ignore_user_abort(true);

				$return = $this->_getPhotoDataModel()->rotatePhoto($content, 360-$angle);
				
				if (!$return)
				{
					return $this->responseError(new XenForo_Phrase('sonnb_xengallery_error_occurred_during_rotation'));
				}
			}
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				$this->_getControllerContentLink($content),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'content' => $content,

				'breadCrumbs' => $this->_getPhotoModel()->getContentBreadCrumbs($content, $album)
			);
			
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Photo_Rotate',
				'sonnb_xengallery_photo_rotate',
				$viewParams
			);
		}
	}
	
	public function actionMeta()
	{
		list($content, $album) = $this->_getPhotoOrError();

		if (empty($content['photo_exif']))
		{
			return $this->responseMessage(new XenForo_Phrase('sonnb_xengallery_this_photo_does_not_contain_any_exif_information'));
		}
		
		if (!empty($content['photo_exif']['ExposureProgram']) &&
				is_int($content['photo_exif']['ExposureProgram']) &&
				$content['photo_exif']['ExposureProgram'] > 0)
		{
			$phrase = @sonnb_XenGallery_Model_Photo::$exifExposureProgram[$content['photo_exif']['ExposureProgram']];
				
			if ($phrase)
			{
				$phrase = new XenForo_Phrase($phrase);
				$content['photo_exif']['ExposureProgram'] = $phrase->render();
			}
		}
			
		if (!empty($content['photo_exif']['ExposureTime']))
		{
			$content['photo_exif']['ExposureTime'] .= ' ('.$content['photo_exif']['ExposureTimeOrigin'].')';
		}

		if (isset($content['photo_exif']['Software']))
		{
			$content['photo_exif']['Software'] = utf8_bad_replace($content['photo_exif']['Software'], '');
		}

		if (isset($content['photo_exif']['ImageDescription']))
		{
			$content['photo_exif']['ImageDescription'] = utf8_bad_replace($content['photo_exif']['ImageDescription'], '');
		}

		if (isset($content['photo_exif']['Artist']))
		{
			$content['photo_exif']['Artist'] = utf8_bad_replace($content['photo_exif']['Artist'], '');
		}

		if (isset($content['photo_exif']['Copyright']))
		{
			$content['photo_exif']['Copyright'] = utf8_bad_replace($content['photo_exif']['Copyright'], '');
		}

		$viewParams = array(
			'album' => $album,
			'content' => $content,
	
			'breadCrumbs' => $this->_getPhotoModel()->getContentBreadCrumbs($content, $album)
		);

		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Photo_Exif',
			'sonnb_xengallery_photo_exif',
			$viewParams
		);
	}

	public function actionMakeAvatar()
	{
		$this->_assertRegistrationRequired();

		$visitor = XenForo_Visitor::getInstance();

		list($content, $album) = $this->_getPhotoOrError();

		if ($this->_noRedirect() || $this->_request->isPost())
		{
			$forceLocal = false;
			$engine = $content['bdattachmentstore_engine'];
			$engineOptions = @unserialize($content['bdattachmentstore_options']);
			$contentDataModel = $this->_getPhotoDataModel();

			if (!empty($engine) && !empty($engineOptions['keepLocalCopy']))
			{
				$forceLocal = true;
			}

			$avatar = $contentDataModel->getContentDataLargeThumbnailFile($content, $forceLocal);
			if ($forceLocal && !file_exists($avatar))
			{
				$avatar = $contentDataModel->getContentDataLargeThumbnailFile($content);
			}

			$avatarTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			@copy($avatar, $avatarTempFile);

			/* @var $modelAvatar XenForo_Model_Avatar */
			$modelAvatar = $this->getModelFromCache('XenForo_Model_Avatar');
			$newAvatar = $modelAvatar->applyAvatar($visitor['user_id'], $avatarTempFile);

			if (is_file($avatarTempFile) && file_exists($avatarTempFile))
			{
				@unlink($avatarTempFile);
			}

			foreach ($newAvatar as $key => $value)
			{
				XenForo_Visitor::getInstance()->offsetSet($key, $value);
			}

			return $this->responseReroute('XenForo_ControllerPublic_Account', 'avatar');
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'content' => $content,

				'breadCrumbs' => $this->_getPhotoModel()->getContentBreadCrumbs($content, $album)
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Photo_Avatar',
				'sonnb_xengallery_photo_avatar',
				$viewParams
			);
		}
	}

	public function actionMakeCover()
	{
		$this->_assertRegistrationRequired();

		list($content, $album) = $this->_getPhotoOrError();

		if (!$album['canEdit'] && !$content['canEdit'])
		{
			throw $this->_throwFriendlyNoPermission('sonnb_xengallery_no_permission_to_edit_cover_photo_of_this_album');
		}

		$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
		$albumDw->setExistingData($album);
		$albumDw->set('cover_content_id', $content['content_id']);
		$albumDw->set('cover_content_type', $content['content_type']);
		$albumDw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
			$this->_getControllerContentLink($content),
			new XenForo_Phrase('changes_saved')
		);
	}

	public function actionSetCover()
	{
		$this->_assertRegistrationRequired();

		list($content, $album) = $this->_getPhotoOrError();

		if ($this->_noRedirect() || $this->_request->isPost())
		{
			$contentFile = $this->_getPhotoDataModel()->getContentDataFile($content);
			if (!is_file($contentFile) || !file_exists($contentFile))
			{
				$contentFile = $this->_getPhotoDataModel()->getContentDataLargeThumbnailFile($content);
			}

			$coverTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			@copy($contentFile, $coverTempFile);

			$this->_getGalleryModel()->applyAuthorCover($coverTempFile, XenForo_Visitor::getUserId());

			if (is_file($coverTempFile) && file_exists($coverTempFile))
			{
				@unlink($coverTempFile);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				$this->_buildLink('gallery/authors', XenForo_Visitor::getInstance()->toArray()),
				new XenForo_Phrase('changes_saved')
			);
		}
		else
		{
			$viewParams = array(
				'album' => $album,
				'content' => $content,

				'breadCrumbs' => $this->_getPhotoModel()->getContentBreadCrumbs($content, $album)
			);

			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Photo_Cover',
				'sonnb_xengallery_photo_cover',
				$viewParams
			);
		}
	}
	
	public function actionUpload()
	{
		$input = $this->_input->filter(array(
			'hash' => XenForo_Input::STRING,
			'album_id' => XenForo_Input::UINT
		));
		
		if (!$input['hash'])
		{
			$input['hash'] = $this->_input->filterSingle('temp_hash', XenForo_Input::STRING);
		}

		$breadCrumbs = array();
		if ($input['album_id'])
		{
			$album = $this->_getAlbumModel()->getAlbumById($input['album_id']);
			$breadCrumbs = $this->_getAlbumModel()->getAlbumBreadCrumbs($album);
		}
	
		$this->_assertCanUploadContents();
		$this->_assertIosDevices();
		
		$contentModel = $this->_getPhotoModel();
		
		$maxPhotoCounts = $contentModel->getPhotoCountLimit();

		$viewParams = array(
			'contentDataConstraints' => $contentModel->getPhotoDataConstraints(),
			
			'remainingUploads' => $maxPhotoCounts,

			'breadCrumbs' => $breadCrumbs,

			'hash' => $input['hash'],
			'album_id' => $input['album_id'],
			'contentDataParams' => array(
				'hash' => $input['hash'],
				'album_id' => $input['album_id']
			)
		);
	
		return $this->responseView(
			'sonnb_XenGallery_ViewPublic_Photo_Upload',
			'sonnb_xengallery_photo_upload',
			$viewParams
		);
	}
	
	public function actionDoUpload()
	{
		$this->_assertPostOnly();
	
		$input = $this->_input->filter(array(
			'hash' => XenForo_Input::STRING,
			'album_id' => XenForo_Input::UINT
		));

		if (!$input['hash'])
		{
			$input['hash'] = $this->_input->filterSingle('temp_hash', XenForo_Input::STRING);
		}

		$this->_assertPhpUploadError();
		$this->_assertCanUploadContents();

		$contentDataModel = $this->_getPhotoDataModel();
		$contentModel = $this->_getPhotoModel();

		$conditions = array('content_type' => sonnb_XenGallery_Model_Photo::$contentType);
		$existingPhotoData = ($input['album_id'] ? $this->_getPhotoModel()->countContentsByAlbumId($input['album_id'], $conditions) : 0);
		$newPhotoData = $contentDataModel->countDataByHash($input['hash'], $conditions);
		$maxPhotoCounts = $contentModel->getPhotoCountLimit();
		
		if ($maxPhotoCounts > 0 && ($existingPhotoData + $newPhotoData >= $maxPhotoCounts))
		{
			return $this->responseError(new XenForo_Phrase(
					'sonnb_xengallery_you_may_upload_maximum_x_photos_to_this_album_only',
					array('total' => $maxPhotoCounts)
			));
		}

		$contentDataConstraints = $contentModel->getPhotoDataConstraints();

		if ($contentDataConstraints['count'] > 0)
		{
			$remainingUploads = $contentDataConstraints['count'] - (count($existingPhotoData) + count($newPhotoData));
			if ($remainingUploads <= 0)
			{
				return $this->responseError(new XenForo_Phrase(
					'you_may_not_upload_more_files_with_message_allowed_x',
					array('total' => $contentDataConstraints['count'])
				));
			}
		}
	
		$file = sonnb_XenGallery_Model_PhotoUpload::getUploadedFile('upload');
		if (!$file)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->_buildLink('gallery/photos/upload', false, array(
					'hash' => $input['hash'],
					'album_id' => $input['album_id']
				))
			);
		}
	
		$file->setConstraints($contentDataConstraints);
		if (!$file->isImage())
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_our_gallery_currently_accepts_images_only'));
		}

		if (!$file->isValid())
		{
			return $this->responseError($file->getErrors());
		}

		if (!$contentModel->canResizeImage($file->getImageInfoField('width'), $file->getImageInfoField('height')))
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_your_photo_is_too_big'));
		}

		$xenOptions = XenForo_Application::getOptions();
		if ($xenOptions->sonnbXG_nudityFilter && $xenOptions->sonnbXG_nudityAction === 'refuse')
		{
			//TODO: Prevent timeout on slow server
			$score = sonnb_XenGallery_Model_PhotoFilter::getInstance()->getScore($file->getTempFile());

			if ($score > $xenOptions->sonnbXG_nudityScore)
			{
				return $this->responseError(new XenForo_Phrase('sonnb_xengallery_your_photo_might_contain_nudity_content'));
			}
		}

        if (!$xenOptions->sonnbXG_enableResize)
        {
            if (($contentDataConstraints['width'] && $file->getImageInfoField('width') > $contentDataConstraints['width'])
                || ($contentDataConstraints['height'] && $file->getImageInfoField('height') > $contentDataConstraints['height']))
            {
                return $this->responseError(new XenForo_Phrase('sonnb_xengallery_your_photo_is_too_big'));
            }
        }

		$extras = array(
			'temp_hash' => $input['hash'],
			'content_type' => sonnb_XenGallery_Model_Photo::$contentType,
		);
		$contentData = $contentDataModel->insertUploadedPhotoData($file, $extras);

		if (empty($contentData['content_data_id']))
		{
			return $this->responseError(new XenForo_Phrase('sonnb_xengallery_error_occurred_while_uploading'));
		}
		
		$message = new XenForo_Phrase('upload_completed_successfully');
	
		if ($this->_noRedirect())
		{
			$viewParams = array(
				'album' => $this->_getAlbumModel()->getAlbumById($input['album_id']),
				'content' => $contentDataModel->prepareDataSingle($contentData),
				'message' => $message
			);
	
			return $this->responseView(
				'sonnb_XenGallery_ViewPublic_Photo_DoUpload', 
				'', 
				$viewParams
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->_buildLink('gallery/photos/upload', false, array(
					'hash' => $input['hash'],
					'album_id' => $input['album_id']
				)),
				$message
			);
		}
	}
	
	protected function _getPhotoOrError($contentId = null)
	{
		if ($contentId === null)
		{
			$contentId = $this->_input->filterSingle('content_id', XenForo_Input::UINT);
		}
		
		$visitor = XenForo_Visitor::getInstance();

		/* @var $galleryHelper sonnb_XenGallery_ControllerHelper_Gallery */
		$galleryHelper = $this->getHelper('sonnb_XenGallery_ControllerHelper_Gallery');
		
		$contentFetchOptions = array(
			'join' => sonnb_XenGallery_Model_Photo::FETCH_DATA |
							sonnb_XenGallery_Model_Photo::FETCH_USER |
							sonnb_XenGallery_Model_Photo::FETCH_PHOTO,
			'likeUserId' => $visitor['user_id'],
			'watchUserId' => $visitor['user_id'],
			'followingUserId' => $visitor['user_id']
		);
		
		list($content, $album) = $galleryHelper->assertPhotoValidAndViewable($contentId, $contentFetchOptions);

		if (!$this->_getPhotoModel()->isPhoto($content))
		{
			throw $this->responseException($this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->_getControllerContentLink($content)
			));
		}

		return array($content, $album);
	}

	public static function getSessionActivityDetailsForList(array $activities)
	{
		$xenOptions = XenForo_Application::getOptions();

		if ($xenOptions->sonnbXG_showActivity)
		{
			$contentIds = array();
			foreach ($activities AS $activity)
			{
				if (!empty($activity['params']['content_id']))
				{
					$contentIds[$activity['params']['content_id']] = intval($activity['params']['content_id']);
				}
			}

			$contentData = array();
			if ($contentIds)
			{
				/* @var $contentModel sonnb_XenGallery_Model_Photo */
				$contentModel = XenForo_Model::create('sonnb_XenGallery_Model_Photo');
				$fetchOptions = array(
					'join' => sonnb_XenGallery_Model_Photo::FETCH_ALBUM
								| sonnb_XenGallery_Model_Photo::FETCH_USER
								| sonnb_XenGallery_Model_Photo::FETCH_PHOTO
				);

				$contents = $contentModel->getContentsByContentIds(sonnb_XenGallery_Model_Photo::$contentType, $contentIds, $fetchOptions);
				$contents = $contentModel->prepareContents($contents, $fetchOptions);

				foreach ($contents AS $content)
				{
					if ($contentModel->canViewContentAndContainer($content, $content['album']))
					{
						$content['title'] = XenForo_Helper_String::censorString($content['title']);

						$contentData[$content['content_id']] = array(
							'title' => $content['title'],
							'url' => $content['url'],
							'album_title' => XenForo_Helper_String::censorString($content['album']['title']),
							'album_id' => $content['album_id'],
							'user' => $content['album']['username']
						);
					}
				}
			}

			$output = array();
			foreach ($activities AS $key => $activity)
			{
				$content = false;
				if (!empty($activity['params']['content_id']))
				{
					$contentId = $activity['params']['content_id'];

					if (isset($contentData[$contentId]))
					{
						$content = $contentData[$contentId];
					}
				}

				if ($content)
				{
					$output[$key] = array(
						' ',
						new XenForo_Phrase(
							'sonnb_xengallery_viewing_photo_x_in_album_y_by_z',
							array(
								'title' => $content['title'],
								'link' => $content['url'],
								'album_title' => $content['album_title'],
								'user' => $content['user']
							)
						),
						$content['url'],
						''
					);
				}
				else
				{
					$output[$key] = new XenForo_Phrase('sonnb_xengallery_viewing_gallery_photo');
				}
			}
		}
		else
		{
			$output = array();
			foreach ($activities AS $key => $activity)
			{
				$output[$key] = new XenForo_Phrase('sonnb_xengallery_viewing_gallery_photo');
			}
		}

		return $output;
	}

    protected function _getControllerContentLink(array $content)
    {
        return $this->_buildLink("gallery/photos", $content);
    }

    protected function _getControllerContentData()
    {
        return $this->_getPhotoOrError();
    }

    protected function _getControllerContentDwClass()
    {
        return "sonnb_XenGallery_DataWriter_Photo";
    }

    protected function _getControllerContentModel()
    {
        return $this->_getPhotoModel();
    }

    protected function _getControllerContentDataModel()
    {
        return $this->_getPhotoDataModel();
    }

    protected function _getControllerContentType()
    {
        return sonnb_XenGallery_Model_Photo::$contentType;
    }

    protected function _getControllerContentXfType()
    {
        return sonnb_XenGallery_Model_Photo::$xfContentType;
    }
}