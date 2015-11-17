<?php

class XenGallery_Model_File extends XenForo_Model
{
	/**
	 * Inserts uploaded attachment data.
	 *
	 * @param XenForo_Upload $file Uploaded attachment info. Assumed to be valid
	 * @param integer $userId User ID uploading
	 * @param array $exif Exif data to cache
	 *
	 * @return integer Attachment data ID
	 */
	public function insertUploadedAttachmentData(XenForo_Upload $file, $userId, array $exif = array())
	{
		$dimensions = array();
		$fileIsVideo = false;
		$tempThumbFile = false;

		$options = XenForo_Application::getOptions();

		if ($file->isImage())
		{
			$dimensions = array(
				'width' => $file->getImageInfoField('width'),
				'height' => $file->getImageInfoField('height'),
			);

			if (XenForo_Image_Abstract::canResize($dimensions['width'], $dimensions['height']))
			{
				$imageFile = $file->getTempFile();
			}
			else
			{
				$imageFile = $options->xengalleryDefaultNoThumb;
			}

			$tempThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
			if ($tempThumbFile)
			{
				@copy($imageFile, $tempThumbFile);
			}
		}
		else
		{
			$fileIsVideo = true;

			if ($options->get('xengalleryVideoTranscoding', 'thumbnail'))
			{
				try
				{
					$video = new XenGallery_Helper_Video($file->getTempFile());
					$tempThumbFile = $video->getKeyFrame();

					list($width, $height) = $video->getVideoDimensions();

					$dimensions['width'] = $width;
					$dimensions['height'] = $height;
				}
				catch (XenForo_Exception $e) {}
			}

			if (!$tempThumbFile)
			{
				$tempThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
				if ($tempThumbFile)
				{
					@copy($options->xengalleryDefaultNoThumb, $tempThumbFile);
				}
			}
		}

		if ($tempThumbFile)
		{
			$image = new XenGallery_Helper_Image($tempThumbFile);
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

		$mediaModel = $this->_getMediaModel();

		try
		{
			$dataDw = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');

			$filename = $file->getFileName();

			$dataDw->set('user_id', $userId);

			if ($fileIsVideo)
			{
				$filename = strtr($filename, strtolower(substr(strrchr($filename, '.'), 1)), 'mp4');
				$dataDw->set('file_path', $mediaModel->getVideoFilePath());
			}
			$dataDw->set('filename', $filename);
			$dataDw->bulkSet($dimensions);

			$dataDw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_FILE, $file->getTempFile());
			if ($tempThumbFile)
			{
				$dataDw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_THUMB_FILE, $tempThumbFile);
			}

			$dataDw->setExtraData(XenGallery_DataWriter_AttachmentData::DATA_XMG_FILE_IS_VIDEO, $fileIsVideo);
			$dataDw->setExtraData(XenGallery_DataWriter_AttachmentData::DATA_XMG_DATA, true);

			$dataDw->save();
		}
		catch (Exception $e)
		{
			if ($tempThumbFile)
			{
				@unlink($tempThumbFile);
			}

			throw $e;
		}

		if ($tempThumbFile)
		{
			@unlink($tempThumbFile);
		}

		$exif = $this->_getMediaModel()->sanitizeExifData($exif);

		$db = $this->_getDb();
		$db->query('
			INSERT IGNORE INTO xengallery_exif_cache
				(data_id, media_exif_data_cache_full, cache_date)
			VALUES
				(?, ?, ?)
		', array($dataDw->get('data_id'), @json_encode($exif), XenForo_Application::$time));

		return $dataDw->get('data_id');
	}
	
	/**
	 * Gets am image resource from an existing file.
	 *
	 * @param string $fileName
	 * @param integer $inputType IMAGETYPE_XYZ constant representing image type
	 *
	 * @return resource|false
	 */
	public static function getImageResource($fileName, $inputType)
	{
		$invalidType = false;
	
		try
		{
			switch ($inputType)
			{
				case IMAGETYPE_GIF:
					if (!function_exists('imagecreatefromgif'))
					{
						return false;
					}
					$image = imagecreatefromgif($fileName);
					break;
	
				case IMAGETYPE_JPEG:
					if (!function_exists('imagecreatefromjpeg'))
					{
						return false;
					}
					$image = imagecreatefromjpeg($fileName);
					break;
	
				case IMAGETYPE_PNG:
					if (!function_exists('imagecreatefrompng'))
					{
						return false;
					}
					$image = imagecreatefrompng($fileName);
					break;
	
				default:
					$invalidType = true;
			}
		}
		catch (Exception $e)
		{
			return false;
		}
	
		if ($invalidType)
		{
			throw new XenForo_Exception('Invalid image type given. Expects IMAGETYPE_XXX constant.');
		}
	
		return $image;
	}	
	
	public function saveImageResource($image, $inputType, $oldFileName, $dataId)
	{
		$invalidType = false;
		$imageData = array();
		
		try
		{			
			$tmpFileName = sys_get_temp_dir() . "$dataId-" . uniqid() . '.data';
			
			$writeFilePrefixPos = strrpos($oldFileName, "$dataId-");
			$writeFilePrefix = substr($oldFileName, 0, $writeFilePrefixPos);
			
			switch ($inputType)
			{
				case IMAGETYPE_GIF:
					if (!function_exists('imagegif'))
					{
						return false;
					}
					
					$tmpImage = imagegif($image, $tmpFileName);
					
					if ($tmpImage)
					{
						$imageData = $this->fetchImageData($image, $tmpFileName, $dataId);
							
						$image = imagegif($image, $writeFilePrefix . $imageData['writeFileName']);
					}
					
					break;
		
				case IMAGETYPE_JPEG:
					if (!function_exists('imagejpeg'))
					{
						return false;
					}
					
					$tmpImage = imagejpeg($image, $tmpFileName);
					
					if ($tmpImage)
					{
						$imageData = $this->fetchImageData($image, $tmpFileName, $dataId);
						
						$image = imagejpeg($image, $writeFilePrefix . $imageData['writeFileName']);
					}
					
					break;
		
				case IMAGETYPE_PNG:
					if (!function_exists('imagepng'))
					{
						return false;
					}
					
					$tmpImage = imagepng($image, $tmpFileName);
					
					if ($tmpImage)
					{
						$imageData = $this->fetchImageData($image, $tmpFileName, $dataId);
						
						$image = imagepng($image, $writeFilePrefix . $imageData['writeFileName']);
					}

					break;
		
				default:
					$invalidType = true;
			}
		}
		catch (Exception $e)
		{
			return false;
		}
		
		if ($invalidType)
		{
			throw new XenForo_Exception('Invalid image type given. Expects IMAGETYPE_XXX constant.');
		}
		
		@unlink($oldFileName);
		@unlink($tmpFileName);
		
		$imageData['writeFileName'] = $writeFilePrefix . $imageData['writeFileName'];
		
		return $imageData;
	}
	
	public function fetchImageData($image, $fileName, $dataId)
	{
		return $imageData = array(
			'width' => imagesx($image),
			'height' => imagesy($image),
			'file_size' => filesize($fileName),
			'file_hash' => $hash = md5_file($fileName),
			'writeFileName' => $dataId . '-' . $hash . '.data'
		);
	}

	public function addToFilesFromUrl($key, $url, &$errorText)
	{
		if (!Zend_Uri::check($url))
		{
			$errorText = new XenForo_Phrase('xengallery_please_enter_a_valid_url');
			return false;
		}

		$tempName = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
		$originalName = basename(parse_url($url, PHP_URL_PATH));

		$client = XenForo_Helper_Http::getClient($url);

		$request = $client->request('GET');
		if (!$request->isSuccessful())
		{
			$errorText = new XenForo_Phrase('xengallery_no_media_found_at_the_url_provided');
			return false;
		}

		$rawImage = $request->getBody();

		$fp = fopen($tempName, 'w');

		fwrite($fp, $rawImage);
		fclose($fp);

		$imageInfo = @getimagesize($rawImage);
		$mimeType = '';
		if ($imageInfo)
		{
			$mimeType = $imageInfo['mime'];
		}

		$_FILES[$key] = array(
			'name' => $originalName,
			// try to force jpg mime type error (if present) will be caught in a later validation.
			'type' => $mimeType ? $mimeType : 'image/jpg',
			'tmp_name' => $tempName,
			'error' => 0,
			'size' => strlen($rawImage)
		);

		return $tempName;
	}

	/**
	 * @return XenForo_Model_Attachment
	 */
	protected function _getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
	}

	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Media');
	}

	/**
	 * @return XenGallery_Model_Watermark
	 */
	protected function _getWatermarkModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Watermark');
	}
}
