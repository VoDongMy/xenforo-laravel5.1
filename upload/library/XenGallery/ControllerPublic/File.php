<?php

class XenGallery_ControllerPublic_File extends XenGallery_ControllerPublic_Abstract
{
	public function actionUpload()
	{
		$mediaHelper = $this->_getMediaHelper();
		$mediaHelper->assertCanAddMedia();

		$input = $this->_input->filter(array(
			'hash' => XenForo_Input::STRING,
			'content_type' => XenForo_Input::STRING,
			'upload_type' => XenForo_Input::STRING,
			'content_data' => array(XenForo_Input::UINT, 'array' => true),
			'key' => XenForo_Input::STRING
		));
		if (!$input['hash'])
		{
			$input['hash'] = $this->_input->filterSingle('temp_hash', XenForo_Input::STRING);
		}

		$this->_assertCanUploadAndManageAttachments($input['hash'], $input['content_type'], $input['content_data']);

		$attachmentModel = $this->_getAttachmentModel();
		$attachmentHandler = $attachmentModel->getAttachmentHandler($input['content_type']); // known to be valid
		$contentId = $attachmentHandler->getContentIdFromContentData($input['content_data']);

		$existingAttachments = ($contentId
			? $attachmentModel->getAttachmentsByContentId($input['content_type'], $contentId)
			: array()
		);
		$newAttachments = $attachmentModel->getAttachmentsByTempHash($input['hash']);

		$constraints = $attachmentHandler->getUploadConstraints($input['upload_type']);
		if ($constraints['count'] <= 0)
		{
			$canUpload = true;
			$remainingUploads = true;
		}
		else
		{
			$remainingUploads = $constraints['count'] - (count($existingAttachments) + count($newAttachments));
			$canUpload = ($remainingUploads > 0);
		}

		$viewParams = array(
			'attachmentConstraints' => $constraints,
			'existingAttachments' => $existingAttachments,
			'newAttachments' => $newAttachments,

			'canUpload' => $canUpload,
			'remainingUploads' => $remainingUploads,

			'hash' => $input['hash'],
			'contentType' => $input['content_type'],
			'contentData' => $input['content_data'],
			'attachmentParams' => array(
				'hash' => $input['hash'],
				'content_type' => $input['content_type'],
				'content_data' => $input['content_data']
			),
			'key' => $input['key'],
			'uploadType' => $input['upload_type']
		);

		return $this->responseView('XenGallery_ViewPublic_Media_Upload', 'xengallery_media_file_upload', $viewParams);
	}

	public function actionDoUpload()
	{
		$mediaHelper = $this->_getMediaHelper();
		$mediaHelper->assertCanAddMedia();

		$this->_assertPostOnly();

		$mediaModel = $this->_getMediaModel();

		$deleteArray = array_keys($this->_input->filterSingle('delete', XenForo_Input::ARRAY_SIMPLE));
		$delete = reset($deleteArray);
		if ($delete)
		{
			$this->_request->setParam('attachment_id', $delete);
			return $this->responseReroute(__CLASS__, 'delete');
		}

		$input = $this->_input->filter(array(
			'hash' => XenForo_Input::STRING,
			'content_type' => XenForo_Input::STRING,
			'upload_type' => XenForo_Input::STRING,
			'content_data' => array(XenForo_Input::UINT, 'array' => true),
			'key' => XenForo_Input::STRING
		));
		if (!$input['hash'])
		{
			$input['hash'] = $this->_input->filterSingle('image_upload_hash', XenForo_Input::STRING);
		}

		$this->_assertCanUploadAndManageAttachments($input['hash'], $input['content_type'], $input['content_data']);

		$attachmentModel = $this->_getAttachmentModel();
		$attachmentHandler = $attachmentModel->getAttachmentHandler($input['content_type']);

		$newAttachments = $attachmentModel->getAttachmentsByTempHash($input['hash']);
		$attachmentConstraints = $attachmentHandler->getUploadConstraints($input['upload_type']);

		if ($attachmentConstraints['count'] > 0)
		{
			$remainingUploads = $attachmentConstraints['count'] - count($newAttachments);
			if ($remainingUploads <= 0)
			{
				return $this->responseError(new XenForo_Phrase(
					'xengallery_you_can_upload_a_maximum_of_x_items_at_a_time',
					array('total' => $attachmentConstraints['count'])
				));
			}
		}

		$file = XenForo_Upload::getUploadedFile('upload');
		if (!$file)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery/files/upload', false, array(
					'hash' => $input['hash'],
					'content_type' => $input['content_type'],
					'upload_type' => $input['upload_type'],
					'content_data' => $input['content_data'],
					'key' => $input['key']
				))
			);
		}

		$exif = array();
		if ($input['upload_type'] == 'image_upload')
		{
			if (function_exists('exif_read_data'))
			{
				$filePath = $file->getTempFile();
				$fileType = @getimagesize($filePath);

				if (isset($fileType[2]) && $fileType[2] == IMAGETYPE_JPEG)
				{
					@ini_set('exif.encode_unicode', 'UTF-8');
					$exif = @exif_read_data($filePath, null, true);
					if (isset($exif['FILE']))
					{
						$exif['FILE']['FileName'] = $file->getFileName();
					}

				}
			}
		}

		$file->setConstraints($attachmentConstraints);
		if (!$file->isValid())
		{
			return $this->responseError($file->getErrors());
		}

		$input['requiresTranscode'] = false;
		if ($input['upload_type'] == 'video_upload')
		{
			$input['requiresTranscode'] = $mediaModel->requiresTranscoding($file->getTempFile());
			if ($input['requiresTranscode'] && !$mediaModel->canTranscode())
			{
				return $this->responseError(new XenForo_Phrase('xengallery_video_not_encoded_in_supported_format_explain'));
			}
		}

		if ($attachmentConstraints['storage'] > 0)
		{
			$visitor = XenForo_Visitor::getInstance();

			$existingFileSize = 0;
			foreach($newAttachments AS $newAttachment)
			{
				$existingFileSize += $newAttachment['file_size'];
			}
			$newFileSize = filesize($file->getTempFile());

			if (($visitor['xengallery_media_quota'] + $newFileSize + $existingFileSize) > $attachmentConstraints['storage'])
			{
				return $this->responseError(new XenForo_Phrase(
					'xengallery_you_have_exceeded_your_allowed_storage_quota',
					array(
						'quota' => XenForo_Locale::numberFormat($attachmentConstraints['storage'], 'size'),
						'filesize' => XenForo_Locale::numberFormat($newFileSize, 'size'),
						'storage' => XenForo_Locale::numberFormat($visitor['xengallery_media_quota'], 'size')
					)
				));
			}
		}

		$fileModel = $this->_getFileModel();

		$dataId = $fileModel->insertUploadedAttachmentData($file, XenForo_Visitor::getUserId(), $exif);
		$attachmentId = $attachmentModel->insertTemporaryAttachment($dataId, $input['hash']);

		$message = new XenForo_Phrase('upload_completed_successfully');

		// return a view if noredirect has been requested and we are not deleting
		if ($this->_noRedirect())
		{
			return $this->_getUploadResponse($attachmentId, $input, $message);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery/files/upload', false, array(
					'hash' => $input['hash'],
					'content_type' => $input['content_type'],
					'content_data' => $input['content_data'],
					'uploadType' => $input['upload_type'],
					'key' => $input['key']
				)),
				$message
			);
		}
	}

	protected function _getUploadResponse($attachmentId, array $input, $message)
	{
		$attachmentModel = $this->_getAttachmentModel();
		$mediaModel = $this->_getMediaModel();

		$attachment = $attachmentModel->getAttachmentById($attachmentId);
		if (XenForo_Application::getOptions()->xengalleryAutoGenerateImageTitles)
		{
			$attachment['media_title'] = ucwords(pathinfo($attachment['filename'], PATHINFO_FILENAME));
		}

		$category = array();
		$album = array();

		if (!empty($input['content_data']['category_id']))
		{
			$categoryModel = $this->_getCategoryModel();
			$category = $categoryModel->getCategoryById($input['content_data']['category_id']);
			$category = $categoryModel->prepareCategory($category);

			$fieldCache = $category['categoryFieldCache'];
		}
		else if (!empty($input['content_data']['album_id']))
		{
			$albumModel = $this->_getAlbumModel();
			$album = $albumModel->getAlbumByIdSimple($input['content_data']['album_id']);
			$album = $albumModel->prepareAlbum($album);

			$fieldCache = $album['albumFieldCache'];
		}
		else
		{
			$fieldCache = array();
		}

		$fieldModel = $this->_getFieldModel();
		$customFields = $fieldModel->prepareGalleryFields($fieldModel->getGalleryFields(array('display_add_media' => true)), true);

		$hasRequiredFields = false;
		$shownFields = array();
		foreach ($fieldCache AS $fields)
		{
			foreach ($fields AS $fieldId)
			{
				if (isset($customFields[$fieldId]))
				{
					$shownFields[$customFields[$fieldId]['display_group']][$fieldId] = $customFields[$fieldId];
					if ($customFields[$fieldId]['required'])
					{
						$hasRequiredFields = true;
					}
				}
			}
		}

		$minTags = $category ? $category['min_tags'] : XenForo_Application::getOptions()->xengalleryAlbumMinTags;

		$viewParams = array(
			'media' => $attachment ? $attachmentModel->prepareAttachment($attachment) : array(),
			'message' => $message,
			'hash' => $input['hash'],
			'content_type' => $input['content_type'],
			'upload_type' => $input['upload_type'],
			'content_data' => $input['content_data'],
			'key' => $input['key'],
			'canEditTags' => $mediaModel->canEditTags(),
			'minTags' => $minTags,
			'customFields' => $shownFields,
			'requiredInput' => ($minTags || $hasRequiredFields),
			'requiresTranscode' => isset($input['requiresTranscode']) ? $input['requiresTranscode'] : false
		);

		return $this->responseView('XenGallery_ViewPublic_Media_DoUpload', 'xengallery_media_add_item', $viewParams);
	}

	public function actionDoDownload()
	{
		$mediaHelper = $this->_getMediaHelper();
		$mediaHelper->assertCanAddMedia();

		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'hash' => XenForo_Input::STRING,
			'content_type' => XenForo_Input::STRING,
			'upload_type' => XenForo_Input::STRING,
			'content_data' => array(XenForo_Input::UINT, 'array' => true),
			'key' => XenForo_Input::STRING,
			'image_url' => XenForo_Input::STRING,
			'unique_key' => XenForo_Input::STRING
		));
		if (!$input['hash'])
		{
			$input['hash'] = $this->_input->filterSingle('temp_hash', XenForo_Input::STRING);
		}

		$this->_assertCanUploadAndManageAttachments($input['hash'], $input['content_type'], $input['content_data']);

		$attachmentModel = $this->_getAttachmentModel();
		$attachmentHandler = $attachmentModel->getAttachmentHandler($input['content_type']); // known to be valid
		$contentId = $attachmentHandler->getContentIdFromContentData($input['content_data']);

		$existingAttachments = ($contentId
			? $attachmentModel->getAttachmentsByContentId($input['content_type'], $contentId)
			: array()
		);
		$newAttachments = $attachmentModel->getAttachmentsByTempHash($input['hash']);

		$attachmentConstraints = $attachmentHandler->getUploadConstraints($input['upload_type']);

		if ($attachmentConstraints['count'] > 0)
		{
			$remainingUploads = $attachmentConstraints['count'] - (count($existingAttachments) + count($newAttachments));
			if ($remainingUploads <= 0)
			{
				return $this->responseError(new XenForo_Phrase(
						'you_may_not_upload_more_files_with_message_allowed_x',
						array('total' => $attachmentConstraints['count'])
					));
			}
		}

		$fileModel = $this->_getFileModel();

		$url = $input['image_url'];

		if (!$tempName = $fileModel->addToFilesFromUrl($input['unique_key'], $url, $error))
		{
			return $this->responseError($error);
		}

		$file = XenForo_Upload::getUploadedFile($input['unique_key']);
		if (!$file)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery/files/upload', false, array(
						'hash' => $input['hash'],
						'content_type' => $input['content_type'],
						'content_data' => $input['content_data'],
						'key' => $input['key']
					))
			);
		}

		$file->setConstraints($attachmentConstraints);
		if (!$file->isValid())
		{
			return $this->responseError($file->getErrors());
		}

		if (!$file->isImage())
		{
			return $this->responseError(new XenForo_Phrase('xengallery_files_added_by_url_must_be_images'));
		}

		$dataId = $fileModel->insertUploadedAttachmentData($file, XenForo_Visitor::getUserId());
		$attachmentId = $attachmentModel->insertTemporaryAttachment($dataId, $input['hash']);

		$message = new XenForo_Phrase('upload_completed_successfully');

		@unlink($tempName);

		// return a view if noredirect has been requested and we are not deleting
		if ($this->_noRedirect())
		{
			return $this->_getUploadResponse($attachmentId, $input, $message);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xengallery/files/upload', false, array(
						'hash' => $input['hash'],
						'content_type' => $input['content_type'],
						'content_data' => $input['content_data'],
						'key' => $input['key']
					)),
				$message
			);
		}
	}
}