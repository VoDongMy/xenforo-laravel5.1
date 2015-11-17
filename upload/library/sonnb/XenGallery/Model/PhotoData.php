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
class sonnb_XenGallery_Model_PhotoData extends sonnb_XenGallery_Model_ContentData
{
	public function rotatePhoto(array $photoData, $angle = 0)
	{
		$newDate = XenForo_Application::$time;
		$newPhotoData = $photoData;
		$newPhotoData['upload_date'] = $newDate;

		$engine = $photoData['bdattachmentstore_engine'];
		$engineOptions = @unserialize($photoData['bdattachmentstore_options']);

		if ($angle == 0)
		{
			return false;
		}

		$updateData = array(
			'upload_date' => $newDate
		);

		if (empty($engine) || !class_exists('bdAttachmentStore_Model_File'))
		{
			$success = $this->rotatePhotoLocal($photoData, $newPhotoData, $angle);

			if ($success === false)
			{
				return false;
			}

			$largeImageTemp = $this->getContentDataLargeThumbnailFile($newPhotoData, true);
			$mediumImageTemp = $this->getContentDataMediumThumbnailFile($newPhotoData, true);
			$smallImageTemp = $this->getContentDataSmallThumbnailFile($newPhotoData, true);

			if ($fileInfo = @getimagesize($largeImageTemp))
			{
				$updateData['large_width'] = $fileInfo[0];
				$updateData['large_height'] = $fileInfo[1];
			}
			if ($fileInfo = @getimagesize($mediumImageTemp))
			{
				$updateData['medium_width'] = $fileInfo[0];
				$updateData['medium_height'] = $fileInfo[1];
			}
			if ($fileInfo = @getimagesize($smallImageTemp))
			{
				$updateData['small_width'] = $fileInfo[0];
				$updateData['small_height'] = $fileInfo[1];
			}
		}
		else
		{
			$fileModel = $this->_bdAttachmentStore_getFileModel();
			$successCount = 0;

			if (!empty($engineOptions['keepLocalCopy']))
			{
				$this->rotatePhotoLocal($photoData, $newPhotoData, $angle);
			}

			$largeImagePath = $this->getStoreContentDataLargeThumbnailFile($photoData);
			$newLargeImagePath = $this->getStoreContentDataLargeThumbnailFile($newPhotoData);
			$largeImageTemp = $fileModel->getAccessibleFilePath($engine, $engineOptions, $largeImagePath, true);

			$rotateSuccess = $this->_rotatePhoto($photoData, $largeImageTemp, $angle, $largeImageTemp);
			if ($rotateSuccess)
			{
				$successCount++;
			}

			$mediumImagePath = $this->getStoreContentDataMediumThumbnailFile($photoData);
			$newMediumImagePath = $this->getStoreContentDataMediumThumbnailFile($newPhotoData);
			$mediumImageTemp = $fileModel->getAccessibleFilePath($engine, $engineOptions, $mediumImagePath, true);

			$rotateSuccess = $this->_rotatePhoto($photoData, $mediumImageTemp, $angle, $mediumImageTemp);
			if ($rotateSuccess)
			{
				$successCount++;
			}

			$smallImagePath = $this->getStoreContentDataSmallThumbnailFile($photoData);
			$newSmallImagePath = $this->getStoreContentDataSmallThumbnailFile($newPhotoData);
			$smallImageTemp = $fileModel->getAccessibleFilePath($engine, $engineOptions, $smallImagePath, true);

			$rotateSuccess = $this->_rotatePhoto($photoData, $smallImageTemp, $angle, $smallImageTemp);
			if ($rotateSuccess)
			{
				$successCount++;
			}

			if ($successCount === 3)
			{
				if ($fileModel->saveFile($engine, $engineOptions, $largeImageTemp, $newLargeImagePath, basename($newLargeImagePath)))
				{
					$successCount++;
				}
				if ($fileModel->saveFile($engine, $engineOptions, $mediumImageTemp, $newMediumImagePath, basename($newMediumImagePath)))
				{
					$successCount++;
				}
				if ($fileModel->saveFile($engine, $engineOptions, $smallImageTemp, $newSmallImagePath, basename($newSmallImagePath)))
				{
					$successCount++;
				}

				@unlink($largeImageTemp);
				@unlink($smallImageTemp);
				@unlink($mediumImageTemp);

				if ($successCount === 6)
				{
					$originalImagePath = $this->getStoreContentDataFile($photoData);
					$newOriginalImagePath = $this->getStoreContentDataFile($newPhotoData);
					$originalImageTemp = $fileModel->getAccessibleFilePath($engine, $engineOptions, $originalImagePath, true);

					if ($fileModel->saveFile($engine, $engineOptions, $originalImageTemp, $newOriginalImagePath, basename($newOriginalImagePath)))
					{
						if ($fileInfo = @getimagesize($largeImageTemp))
						{
							$updateData['large_width'] = $fileInfo[0];
							$updateData['large_height'] = $fileInfo[1];
						}
						if ($fileInfo = @getimagesize($mediumImageTemp))
						{
							$updateData['medium_width'] = $fileInfo[0];
							$updateData['medium_height'] = $fileInfo[1];
						}
						if ($fileInfo = @getimagesize($smallImageTemp))
						{
							$updateData['small_width'] = $fileInfo[0];
							$updateData['small_height'] = $fileInfo[1];
						}

						@unlink($originalImageTemp);
					}
					else
					{
						@unlink($originalImageTemp);
						return false;
					}
				}
				else
				{
					return false;
				}
			}
			else
			{
				return false;
			}
		}

		$db = $this->_getDb();
		$db->update(
			'sonnb_xengallery_content',
			array(
				'content_updated_date' => $newDate
			),
			array(
				'content_id = '. $photoData['content_id'],
				"content_type = '". sonnb_XenGallery_Model_Photo::$contentType ."'"
			)
		);

		$db->update(
			'sonnb_xengallery_content_data',
			$updateData,
			'content_data_id = '. $photoData['content_data_id']
		);

		return true;
	}

	public function rotatePhotoLocal(array $photoData, array $newPhotoData, $angle = 0)
	{
		if ($angle == 0)
		{
			return false;
		}

		$imageArray = array();
		$imageArray[] = $this->getContentDataLargeThumbnailFile($photoData, true);
		$imageArray[] = $this->getContentDataMediumThumbnailFile($photoData, true);
		$imageArray[] = $this->getContentDataSmallThumbnailFile($photoData, true);

		$newImageArray = array();
		$newImageArray[] = $this->getContentDataLargeThumbnailFile($newPhotoData, true);
		$newImageArray[] = $this->getContentDataMediumThumbnailFile($newPhotoData, true);
		$newImageArray[] = $this->getContentDataSmallThumbnailFile($newPhotoData, true);

		$successCount = 0;
		if (XenForo_Application::getOptions()->imageLibrary['class'] === 'imPecl')
		{
			foreach ($imageArray as $index => $image)
			{
				if (file_exists($image))
				{
					$class = new Imagick($image);
					$class->rotateimage(new ImagickPixel(), 360-$angle);
					$class->setimageformat($photoData['extension']);
					$success = $class->writeImages($newImageArray[$index], true);
					$class->destroy();

					if ($success)
					{
						$successCount++;
					}
				}
			}
		}
		else
		{
			foreach ($imageArray as $index => $image)
			{
				if (file_exists($image))
				{
					$gd = $this->createImageFromFile($image, self::$typeMap[$photoData['extension']]);

					if (!$gd)
					{
						continue;
					}

					$rotate = imagerotate($gd, $angle, imageColorAllocateAlpha($gd, 0, 0, 0, 127));
					$success = $this->outputImage($rotate, self::$typeMap[$photoData['extension']], $newImageArray[$index]);

					imagedestroy($rotate);
					imagedestroy($gd);

					if ($success)
					{
						$successCount++;
					}
				}
			}
		}

		if ($successCount === count($imageArray))
		{
			$originalImage = $this->getContentDataFile($photoData, true);
			$newOriginalImage = $this->getContentDataFile($newPhotoData, true);

			try
			{
				$originSuccess = rename($originalImage, $newOriginalImage);
			}
			catch (Exception $e)
			{
				$originSuccess = false;
			}

			if (!$originSuccess)
			{
				$originSuccess = $this->_getContentDataModel()->copyFile($originalImage, $newOriginalImage);
			}

			if ($originSuccess)
			{
				foreach ($imageArray as $image)
				{
					@unlink($image);
				}

				@unlink($originalImage);
			}
			else
			{
				return false;
			}

			return true;
		}
		else
		{
			return false;
		}
	}

	protected function _rotatePhoto($photoData, $image, $angle, $tempFile)
	{
		if (XenForo_Application::getOptions()->imageLibrary['class'] === 'imPecl')
		{
			$class = new Imagick($image);
			$class->rotateimage(new ImagickPixel(), 360-$angle);
			$class->setimageformat($photoData['extension']);
			$success = $class->writeImages($tempFile, true);
			$class->destroy();
		}
		else
		{
			$gd = $this->createImageFromFile($image, self::$typeMap[$photoData['extension']]);

			if (!$gd)
			{
				return false;
			}

			$rotate = imagerotate($gd, $angle, imageColorAllocateAlpha($gd, 0, 0, 0, 127));
			$success = $this->outputImage($rotate, self::$typeMap[$photoData['extension']], $tempFile);

			imagedestroy($rotate);
			imagedestroy($gd);
		}

		return $success;
	}
	
	public function insertUploadedPhotoData(XenForo_Upload $file, array $extra = array(), &$errorPhraseKey = '')
	{
		$return = false;

		if ($this->_getPhotoModel()->canResizeImage($file->getImageInfoField('width'), $file->getImageInfoField('height')))
		{
			$xenOptions = XenForo_Application::getOptions();
			$tempFile = $file->getTempFile();
			$tempType = $file->getImageInfoField('type');

			$dimensions = array(
				'width' => $file->getImageInfoField('width'),
				'height' => $file->getImageInfoField('height'),
				'is_animated' => $this->_getPhotoModel()->isAnimatedGif($tempFile) ? 1 : 0
			);

			$smallThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			$mediumThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			$largeThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			$originalFile = false;
            $successCount = 0;

			if ($xenOptions->sonnbXG_enableResize)
			{
				$photoConstraints = $this->_getPhotoModel()->getPhotoDataConstraints();
				//Resize original image if it excess user's limit.
				if ($dimensions['width'] > $photoConstraints['width'] || $dimensions['height'] > $photoConstraints['height'])
				{
					$image = XenForo_Image_Abstract::createFromFile($tempFile, $tempType);
					if ($image)
					{
						if ($image->thumbnail($photoConstraints['width'], $photoConstraints['height']))
						{
							$image->output($tempType, $tempFile, 100);

							$dimensions['width'] = $image->getWidth();
							$dimensions['height'] = $image->getHeight();
						}

						unset($image);
					}
				}
			}

			if (!$xenOptions->sonnbXG_disableOriginal)
			{
				$originalFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
				if ($this->_getContentDataModel()->copyFile($tempFile, $originalFile))
				{
					$successCount++;
				}
			}

			$this->exifRotate($tempFile, $tempType, $tempFile);

			if ($largeThumbFile)
			{
                if ($this->createContentDataThumbnailFile($tempFile, $largeThumbFile, $tempType, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_LARGE, $fallback, $dimensions))
                {
                    $successCount++;
                }

                if ($fallback === true)
                {
                    $dimensions['large_width'] = $file->getImageInfoField('width');
                    $dimensions['large_height'] = $file->getImageInfoField('height');
                }
			}

            if ($mediumThumbFile)
            {
                if ($this->createContentDataThumbnailFile($largeThumbFile, $mediumThumbFile, $tempType, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_MEDIUM, $fallback))
                {
                    $successCount++;
                }

                if ($fallback === true)
                {
                    $dimensions['medium_width'] = $file->getImageInfoField('width');
                    $dimensions['medium_height'] = $file->getImageInfoField('height');
                }
            }
			
			if ($smallThumbFile)
			{
                if ($this->createContentDataThumbnailFile($largeThumbFile, $smallThumbFile, $tempType, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_SMALL, $fallback))
                {
                    $successCount++;
                }

                if ($fallback === true)
                {
                    $dimensions['small_width'] = $file->getImageInfoField('width');
                    $dimensions['small_height'] = $file->getImageInfoField('height');
                }
			}

            if ($xenOptions->sonnbXG_disableOriginal)
            {
                if ($successCount !== 3)
                {
                    return $return;
                }

                $dimensions['width'] = $dimensions['large_width'];
                $dimensions['height'] = $dimensions['large_height'];
                $dimensions['is_animated'] = $this->_getPhotoModel()->isAnimatedGif($largeThumbFile) ? 1 : 0;
            }
            else
            {
                if ($successCount !== 4)
                {
                    return $return;
                }
            }

			try
			{
				/** @var sonnb_XenGallery_DataWriter_ContentData $dataDw */
				$dataDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_ContentData');
				$dataDw->bulkSet($extra);
				$dataDw->bulkSet($dimensions);
				$dataDw->set('extension', sonnb_XenGallery_Model_ContentData::$extensionMap[$tempType]);

				if (!$xenOptions->sonnbXG_disableOriginal && $originalFile)
				{
					$dataDw->setExtraData(sonnb_XenGallery_DataWriter_ContentData::DATA_TEMP_FILE, $originalFile);
				}

				if ($smallThumbFile)
				{
					$dataDw->setExtraData(sonnb_XenGallery_DataWriter_ContentData::DATA_TEMP_SMALL_THUMB_FILE, $smallThumbFile);
				}
				if ($mediumThumbFile)
				{
					$dataDw->setExtraData(sonnb_XenGallery_DataWriter_ContentData::DATA_TEMP_MEDIUM_THUMB_FILE, $mediumThumbFile);
				}
				if ($largeThumbFile)
				{
					$dataDw->setExtraData(sonnb_XenGallery_DataWriter_ContentData::DATA_TEMP_LARGE_THUMB_FILE, $largeThumbFile);
				}

				$dataDw->save();

				$return = $dataDw->getMergedData();

				$exif = $this->_getPhotoModel()->getPhotoExif($return, $tempFile);

				$return['title'] = isset($exif['title']) ? trim($exif['title']) : ($xenOptions->sonnbXG_useFileName ? trim(pathinfo($file->getFileName(), PATHINFO_FILENAME)) : '');
				$return['description'] = isset($exif['description']) ? trim($exif['description']) : '';

                $return['location_lat'] = isset($exif['latitude']) ? $exif['latitude'] : '';
                $return['location_lng'] = isset($exif['longitude']) ? $exif['longitude'] : '';
                $return['content_location'] = isset($exif['address']) ? $exif['address'] : '';
			}
			catch (Exception $e)
			{
				if ($smallThumbFile)
				{
					@unlink($smallThumbFile);
				}
				if ($mediumThumbFile)
				{
					@unlink($mediumThumbFile);
				}
				if ($largeThumbFile)
				{
					@unlink($largeThumbFile);
				}
				if (!$xenOptions->sonnbXG_disableOriginal && $originalFile)
				{
					@unlink($originalFile);
				}

				throw $e;
			}

			if ($smallThumbFile)
			{
				@unlink($smallThumbFile);
			}
			if ($mediumThumbFile)
			{
				@unlink($mediumThumbFile);
			}
			if ($largeThumbFile)
			{
				@unlink($largeThumbFile);
			}
			if (!$xenOptions->sonnbXG_disableOriginal && $originalFile)
			{
				@unlink($originalFile);
			}
		}

		unset($tempFile);
	
		return $return;
	}

	public function exifRotate($inputFile, $inputType, $outputFile)
	{
		$exif = array();
		//Compatible with XF 1.3 as it has method transformByExif
		$transforms = array(
			2 => 'flip-h',
			3 => 180,
			4 => 'flip-v',
			5 => 'transpose',
			6 => 90,
			7 => 'transverse',
			8 => 270
		);

		if (function_exists('exif_read_data'))
		{
			$exif = @exif_read_data($inputFile, 0);
		}
		if (isset($exif['Orientation']) && isset($transforms[$exif['Orientation']]))
		{
			$transform = $transforms[$exif['Orientation']];
			$image = XenForo_Image_Abstract::createFromFile($inputFile, $inputType);
			if ($image && is_callable(array($image, 'transformByExif')))
			{
				$image->transformByExif($exif['Orientation']);
				$image->output($inputType, $outputFile, 100);
			}
			elseif (is_int($transform))
			{
				//TODO: Refactor code.
				if (XenForo_Application::getOptions()->imageLibrary['class'] === 'imPecl')
				{
					$class = new Imagick($inputFile);
					foreach ($class->coalesceImages() AS $frame)
					{
						$frame->rotateImage(new ImagickPixel('none'), $transform);
					}
					$class->setimageformat(self::$extensionMap[$inputType]);
					$class->writeImages($outputFile, true);
					$class->destroy();
				}
				else
				{
					$gd = $this->createImageFromFile($inputFile, $inputType);

					if ($gd)
					{
						$rotate = imagerotate($gd, $transform * -1, imageColorAllocateAlpha($gd, 0, 0, 0, 127));
						$this->outputImage($rotate, $inputType, $outputFile);
						imagedestroy($rotate);
						imagedestroy($gd);
					}
				}
			}

			unset($image);
		}
	}
}
