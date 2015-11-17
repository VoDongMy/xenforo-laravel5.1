<?php

class XenGallery_Model_Watermark extends XenForo_Model
{
	public function getWatermarkById($watermarkId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xengallery_watermark
			WHERE watermark_id = ?
		', $watermarkId);
	}

	public function getWatermarkFilePath($watermarkId, $original = '')
	{
		return sprintf('%s/xengallery_watermark/%d/%d%s.jpg',
			XenForo_Helper_File::getExternalDataPath(),
			floor($watermarkId / 500),
			$watermarkId,
			$original ? '_original' : $original
		);
	}

	public function getCurrentSiteWatermarkFilePath()
	{
		$currentSiteWatermark = $this->_getDb()->fetchOne('
			SELECT watermark_id
			FROM xengallery_watermark
			WHERE is_site = 1
		');

		return array(
			$currentSiteWatermark,
			$this->getWatermarkFilePath($currentSiteWatermark)
		);
	}

	public function getWatermarkUrl($watermarkId)
	{
		return sprintf('%s/xengallery_watermark/%d/%d.jpg',
			XenForo_Application::$externalDataUrl,
			floor($watermarkId / 500),
			$watermarkId
		);
	}

	public function canBypassWatermark(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Application::getOptions()->xengalleryEnableWatermarking == 'disabled')
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'bypassWatermark'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_cannot_bypass_watermarking';
		return false;
	}

	public function canAddWatermark(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$options = XenForo_Application::getOptions();

		if ((isset($media['media_type']) && $media['media_type'] != 'image_upload')
			|| $options->xengalleryEnableWatermarking == 'disabled'
			|| !$options->xengalleryUploadWatermark
			|| !file_exists($this->getWatermarkFilePath($options->xengalleryUploadWatermark))
		)
		{
			$errorPhraseKey = 'xengallery_watermark_no_permission';
			return false;
		}

		if (isset($media['extension']) && $media['extension'] == 'gif')
		{
			if (!$options->xengalleryWatermarkAnimated)
			{
				$errorPhraseKey = 'xengallery_watermark_no_permission';
				return false;
			}
		}

		if (!$media['watermark_id'])
		{
			if ($media['user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'addWatermark'))
			{
				return true;
			}

			if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'addWatermarkAny'))
			{
				return true;
			}
		}

		$errorPhraseKey = 'xengallery_watermark_no_permission';
		return false;
	}

	public function canRemoveWatermark(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($media['media_type'] != 'image_upload'
			|| XenForo_Application::getOptions()->xengalleryEnableWatermarking == 'disabled'
		)
		{
			$errorPhraseKey = 'xengallery_watermark_no_permission';
			return false;
		}

		if ($media['watermark_id'])
		{
			if ($media['user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'removeWatermark'))
			{
				return true;
			}

			if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'removeWatermarkAny'))
			{
				return true;
			}
		}

		$errorPhraseKey = 'xengallery_watermark_remove_no_permission';
		return false;
	}

	public function addWatermarkToImage(array $media)
	{
		if (XenForo_Application::getOptions()->xengalleryEnableWatermarking == 'disabled')
		{
			return false;
		}

		/** @var $mediaModel XenGallery_Model_Media */
		$mediaModel = $this->getModelFromCache('XenGallery_Model_Media');

		$imageInfo = array();
		if ($media)
		{
			$originalPath = $mediaModel->getOriginalDataFilePath($media);
			$internalDataPath = $mediaModel->getAttachmentDataFilePath($media);

			list ($watermarkId, $watermarkPath) = $this->getCurrentSiteWatermarkFilePath();

			if (!$watermarkId || !file_exists($watermarkPath))
			{
				return false;
			}

			if (XenForo_Helper_File::createDirectory(dirname($originalPath), true))
			{
				$success = copy($internalDataPath, $originalPath);
				if ($success)
				{
					XenForo_Helper_File::makeWritableByFtpUser($originalPath);
				}

				$image = new XenGallery_Helper_Image($internalDataPath);
				$watermark = new XenGallery_Helper_Image($watermarkPath);

				$options = XenForo_Application::getOptions();

				$resizedWatermark = $watermark->resize(
					($image->getWidth() / 100) * $options->xengalleryWatermarkDimensions['width'],
					($image->getHeight() / 100) * $options->xengalleryWatermarkDimensions['height'], 'fit'
				);

				if (!$resizedWatermark)
				{
					return false;
				}

				$image->addWatermark($watermark->tmpFile);
				$watermarked = $image->writeWatermark(
					$options->xengalleryWatermarkOpacity,
					$options->xengalleryWatermarkMargin['h'],
					$options->xengalleryWatermarkMargin['v'],
					$options->xengalleryWatermarkHPos,
					$options->xengalleryWatermarkVPos
				);

				if (!$watermarked)
				{
					unset($watermark);
					return false;
				}

				unset($watermark);

				$image->saveToPath($internalDataPath);

				$imageInfo = $image->getImageInfo();
				$imageInfo['file_hash'] = $image->getFileHash();

				$attachmentDataWriter = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');
				$attachmentDataWriter->setExistingData($media);

				clearstatcache();
				$attachmentDataWriter->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_FILE, $internalDataPath);
				$attachmentDataWriter->save();

				$imageInfo['file_hash'] = $attachmentDataWriter->get('file_hash');

				$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
				$mediaWriter->setExistingData($media);

				$mediaWriter->bulkSet(array(
					'watermark_id' => $watermarkId,
					'last_edit_date' => XenForo_Application::$time
				));
				$mediaWriter->save();
			}

			return $imageInfo;
		}

		return false;
	}

	public function removeWatermarkFromImage(array $media)
	{
		/** @var $mediaModel XenGallery_Model_Media */
		$mediaModel = $this->getModelFromCache('XenGallery_Model_Media');

		$originalPath = $mediaModel->getOriginalDataFilePath($media);
		$originalTemp = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
		copy($originalPath, $originalTemp);

		$options = XenForo_Application::getOptions();

		$tempThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
		if ($tempThumbFile)
		{
			$image = new XenGallery_Helper_Image($originalTemp);
			if ($image)
			{
				$image->resize(
					$dimensions['thumbnail_width'] = $options->xengalleryThumbnailDimension['width'],
					$dimensions['thumbnail_height'] = $options->xengalleryThumbnailDimension['height'], 'crop'
				);

				$image->saveToPath($tempThumbFile);

				unset($image);
			}
		}

		$attachmentDataWriter = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');
		$attachmentDataWriter->setExistingData($media['data_id']);

		if ($tempThumbFile)
		{
			$attachmentDataWriter->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_THUMB_FILE, $tempThumbFile);
		}

		$attachmentDataWriter->setExtraData(XenGallery_DataWriter_AttachmentData::DATA_XMG_DATA, true);
		$attachmentDataWriter->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_FILE, $originalTemp);
		$attachmentDataWriter->save();

		$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
		$mediaWriter->setExistingData($media);

		$mediaWriter->bulkSet(array(
			'watermark_id' => 0,
			'last_edit_date' => XenForo_Application::$time
		));
		$mediaWriter->save();

		return true;
	}
}