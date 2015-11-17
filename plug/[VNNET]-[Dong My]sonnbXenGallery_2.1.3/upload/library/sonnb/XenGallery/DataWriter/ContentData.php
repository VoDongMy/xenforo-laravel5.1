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
class sonnb_XenGallery_DataWriter_ContentData extends sonnb_XenGallery_DataWriter_Abstract
{
	const DATA_TEMP_FILE = 'tempFile';
	const DATA_TEMP_SMALL_THUMB_FILE = 'smallThumbFile';
	const DATA_TEMP_MEDIUM_THUMB_FILE = 'mediumThumbFile';
	const DATA_TEMP_LARGE_THUMB_FILE = 'largeThumbFile';

    const SAVE_TO_ATTACHMENT_STORE = 'saveToCloud';
	
	protected function _getFields()
	{
		return array(
			'sonnb_xengallery_content_data' => array(
				'content_data_id' => array(
					'type' => self::TYPE_UINT,
					'autoIncrement' => true
				),
				'content_type' => array(
					'type' => self::TYPE_STRING,
					'default' => 'visible',
					'allowedValues' => array('photo','audio','video'),
				),
				'temp_hash' => array(
					'type' => self::TYPE_STRING,
					'default' => '',
					'maxLength' => 32,
				),
				'unassociated' => array (
					'type' => self::TYPE_BOOLEAN,
					'default' => 1
				),
				'file_size' => array (
					'type' => self::TYPE_UINT,
					'required' => true
				),
				'file_hash' => array (
					'type' => self::TYPE_STRING,
					'maxLength' => 32,
					'required' => true
				),
				'extension' => array (
					'type' => self::TYPE_STRING,
					'maxLength' => 5,
					'default' => ''
				),
				'duration' => array (
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'is_animated' => array (
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'width' => array (
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'height' => array (
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'large_width' => array (
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'large_height' => array (
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'medium_width' => array (
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'medium_height' => array (
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'small_width' => array (
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'small_height' => array (
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'upload_date' => array(
					'type' => self::TYPE_UINT,
					'default' => XenForo_Application::$time,
				),
				'bdattachmentstore_engine' => array(
					'type' => XenForo_DataWriter::TYPE_STRING,
				),
				'bdattachmentstore_options' => array(
					'type' => XenForo_DataWriter::TYPE_SERIALIZED,
				)
			)
		);
	}
	
	protected function _preSave()
	{
        $tempFile = $this->getExtraData(self::DATA_TEMP_FILE);
        if (!$tempFile || !is_file($tempFile) || !is_readable($tempFile))
        {
            $this->setExtraData(self::DATA_TEMP_FILE, '');
        }

        $largeThumbFile = $this->getExtraData(self::DATA_TEMP_LARGE_THUMB_FILE);
        if (!$largeThumbFile || !is_file($largeThumbFile) || !is_readable($largeThumbFile))
        {
            $this->setExtraData(self::DATA_TEMP_LARGE_THUMB_FILE, '');
        }

        $mediumThumbFile = $this->getExtraData(self::DATA_TEMP_MEDIUM_THUMB_FILE);
        if (!$mediumThumbFile || !is_file($mediumThumbFile) || !is_readable($mediumThumbFile))
        {
            $this->setExtraData(self::DATA_TEMP_MEDIUM_THUMB_FILE, '');
        }

		$smallThumbFile = $this->getExtraData(self::DATA_TEMP_SMALL_THUMB_FILE);
		if (!$smallThumbFile || !is_file($smallThumbFile) || !is_readable($smallThumbFile))
		{
			$this->setExtraData(self::DATA_TEMP_SMALL_THUMB_FILE, '');
		}

        $this->_assertOriginalFile(XenForo_Application::getOptions()->sonnbXG_disableOriginal ? $largeThumbFile : $tempFile);

		if ($this->isInsert())
		{
			$xenOptions = XenForo_Application::getOptions();
			$useAttachmentStore = $xenOptions->sonnbXG_useBdStore;
			$engine = $this->get('bdattachmentstore_engine');
            if (XenForo_Application::isRegistered('addOns'))
            {
                $addOns = XenForo_Application::get('addOns');
            }

			if (!class_exists('bdAttachmentStore_Option') || empty($addOns['bdAttachmentStore']))
			{
				$useAttachmentStore = false;
			}

			if ($useAttachmentStore && empty($engine))
			{
				$fileModel = $this->_bdAttachmentStore_getFileModel();
				$defaultEngine = $fileModel->getDefaultEngine();

				if ($defaultEngine === bdAttachmentStore_Option::MODE_ATTACHMENT
						|| $defaultEngine === bdAttachmentStore_Option::MODE_EXTERNAL_DATA)
				{
					$defaultEngine = '';
				}

				if (!empty($defaultEngine))
				{
					$this->set('bdattachmentstore_engine', $defaultEngine);
					$engine = $defaultEngine;
				}
			}

			if (!empty($engine))
			{
				$fileModel = $this->_bdAttachmentStore_getFileModel();
				$storeOptions = $fileModel->getStorageOptions($engine, $this->getMergedData());
				if (!isset($storeOptions['keepLocalCopy']))
				{
					$storeOptions['keepLocalCopy'] = bdAttachmentStore_Option::get('keepLocalCopy');
				}

				$this->set('bdattachmentstore_options', $storeOptions);
			}
		}
	}

    protected function _assertOriginalFile($fileToCheck, $contentType = null)
    {
        if ($contentType === null)
        {
            $contentType = $this->get('content_type');
        }

        if ($contentType !== sonnb_XenGallery_Model_Photo::$contentType)
        {
            return;
        }

        if ($this->isInsert())
        {
	        if (!$fileToCheck)
	        {
                throw new XenForo_Exception(new XenForo_Phrase('sonnb_xengallery_tried_insert_'.$contentType.'_without_data'));
	        }

	        if (!is_readable($fileToCheck))
	        {
		        $this->error(new XenForo_Phrase('sonnb_xengallery_'.$contentType.'_file_could_not_be_read_by_server'));
		        return;
	        }

	        clearstatcache();
	        $this->set('file_size', filesize($fileToCheck));
	        $this->set('file_hash', md5_file($fileToCheck));
        }
    }
	
	protected function _postSave()
	{
		$contentType = $this->get('content_type');
		$data = $this->getMergedData();

		$xenOptions = XenForo_Application::getOptions();
		$contentSize = $this->get('file_size');
		$disableOriginal = $xenOptions->sonnbXG_disableOriginal;

		if (!$disableOriginal && !empty($contentSize))
		{
			$tempFile = $this->getExtraData(self::DATA_TEMP_FILE);
			if ($tempFile)
			{
				if (!$this->_writeDataFile($tempFile, $data))
				{
					throw new XenForo_Exception('sonnb_xengallery_failed_to_write_'.$contentType.'_file');
				}
			}
		}

		$smallThumbFile = $this->getExtraData(self::DATA_TEMP_SMALL_THUMB_FILE);
		if ($smallThumbFile)
		{
			if (!$this->_writeDataFile($smallThumbFile, $data, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_SMALL))
			{
				throw new XenForo_Exception('sonnb_xengallery_failed_to_write_'.$contentType.'_thumbnail_file');
			}
		}

		$mediumThumbFile = $this->getExtraData(self::DATA_TEMP_MEDIUM_THUMB_FILE);
		if ($mediumThumbFile)
		{
			if (!$this->_writeDataFile($mediumThumbFile, $data, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_MEDIUM))
			{
				throw new XenForo_Exception('sonnb_xengallery_failed_to_write_'.$contentType.'_thumbnail_file');
			}
		}

		$largeThumbFile = $this->getExtraData(self::DATA_TEMP_LARGE_THUMB_FILE);
		if ($largeThumbFile)
		{
			if (!$this->_writeDataFile($largeThumbFile, $data, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_LARGE))
			{
				throw new XenForo_Exception('sonnb_xengallery_failed_to_write_'.$contentType.'_thumbnail_file');
			}
		}
	}
	
	protected function _preDelete()
	{
		
	}
	
	protected function _postDelete()
	{
		$data = $this->getMergedExistingData();

		if (!empty($data['content_data_id']))
		{
			$contentDataModel = $this->_getContentDataModel();
			$engine = $data['bdattachmentstore_engine'];
			$engineOptions = @unserialize($data['bdattachmentstore_options']);

			if (empty($engineOptions)
					|| (!empty($engineOptions) && !empty($engineOptions['keepLocalCopy'])))
			{
				$file = $contentDataModel->getContentDataFile($data, true);
				if (is_file($file) && is_writable($file))
				{
					@unlink($file);
				}

				$file = $contentDataModel->getContentDataSmallThumbnailFile($data, true);
				if (is_file($file) && is_writable($file))
				{
					@unlink($file);
				}

				$file = $contentDataModel->getContentDataMediumThumbnailFile($data, true);
				if (is_file($file) && is_writable($file))
				{
					@unlink($file);
				}

				$file = $contentDataModel->getContentDataLargeThumbnailFile($data, true);
				if (is_file($file) && is_writable($file))
				{
					@unlink($file);
				}
			}

			if (!empty($engineOptions))
			{
				$fileModel = $this->_bdAttachmentStore_getFileModel();

				$filePath = $contentDataModel->getStoreContentDataSmallThumbnailFile($data);
				$fileModel->deleteFile($engine, $engineOptions, $filePath);

				$filePath = $contentDataModel->getStoreContentDataMediumThumbnailFile($data);
				$fileModel->deleteFile($engine, $engineOptions, $filePath);

				$filePath = $contentDataModel->getStoreContentDataLargeThumbnailFile($data);
				$fileModel->deleteFile($engine, $engineOptions, $filePath);

				$filePath = $contentDataModel->getStoreContentDataFile($data);
				$fileModel->deleteFile($engine, $engineOptions, $filePath);
			}
		}
	}

	public function rebuildThumbnailSize()
	{
		$contentDataModel = $this->_getContentDataModel();
		$contentData = $this->getMergedData();

		if (!empty($contentData)
			&& $contentData['content_type'] === sonnb_XenGallery_Model_Photo::$contentType
			&& (empty($contentData['large_height']) || empty($contentData['large_width'])))
		{
			$forceLocal = false;
			$engine = $contentData['bdattachmentstore_engine'];
			$engineOptions = @unserialize($contentData['bdattachmentstore_options']);
			$temp = true;

			if (empty($engine)
					|| (!empty($engine) && !empty($engineOptions['keepLocalCopy']) && class_exists('bdAttachmentStore_Model_File')))
			{
				$forceLocal = true;
			}

			$large = $contentDataModel->getContentDataLargeThumbnailFile($contentData, $forceLocal);
			$medium = $contentDataModel->getContentDataMediumThumbnailFile($contentData, $forceLocal);
			$small = $contentDataModel->getContentDataSmallThumbnailFile($contentData, $forceLocal);

			if ($forceLocal)
			{
				$temp = false;

				if (!is_file($large))
				{
					$large = $contentDataModel->getContentDataLargeThumbnailFile($contentData);
				}
				if (!is_file($medium))
				{
					$medium = $contentDataModel->getContentDataMediumThumbnailFile($contentData);
				}
				if (!is_file($small))
				{
					$small = $contentDataModel->getContentDataSmallThumbnailFile($contentData);
				}
			}

			$updateContentData = array();

			if ($fileInfo = @getimagesize($large))
			{
				$updateContentData['large_width'] = $fileInfo[0];
				$updateContentData['large_height'] = $fileInfo[1];
			}
			if ($fileInfo = @getimagesize($medium))
			{
				$updateContentData['medium_width'] = $fileInfo[0];
				$updateContentData['medium_height'] = $fileInfo[1];
			}
			if ($fileInfo = @getimagesize($small))
			{
				$updateContentData['small_width'] = $fileInfo[0];
				$updateContentData['small_height'] = $fileInfo[1];
			}

			if ($temp)
			{
				@unlink($large);
				@unlink($medium);
				@unlink($small);
			}

			$this->bulkSet($updateContentData);
		}
	}

	public function moveContentData($target)
	{
		if (!class_exists('bdAttachmentStore_Model_File'))
		{
			return;
		}

		$contentType = $this->get('content_type');
		$contentSize = $this->get('file_size');

		$xenOptions = XenForo_Application::getOptions();
		$disableOriginal = $xenOptions->sonnbXG_disableOriginal;
		$contentDataModel = $this->_getContentDataModel();
		$contentData = $this->getMergedData();

		$needSuccess = 4;
		if ($disableOriginal && !empty($contentSize))
		{
			$needSuccess = 3;
		}

		if ($target === 'store')
		{
			$engine = $contentData['bdattachmentstore_engine'];
			$fileModel = $this->_bdAttachmentStore_getFileModel();
			$defaultEngine = $fileModel->getDefaultEngine();

			if ($defaultEngine === bdAttachmentStore_Option::MODE_ATTACHMENT
					|| $defaultEngine === bdAttachmentStore_Option::MODE_EXTERNAL_DATA)
			{
				$defaultEngine = '';
			}

			if (!empty($defaultEngine) && empty($engine))
			{
				$storeOptions = $fileModel->getStorageOptions($defaultEngine, $contentData);
				if (!isset($storeOptions['keepLocalCopy']))
				{
					$storeOptions['keepLocalCopy'] = bdAttachmentStore_Option::get('keepLocalCopy');
				}

				$large = $contentDataModel->getContentDataLargeThumbnailFile($contentData, true);
				$medium = $contentDataModel->getContentDataMediumThumbnailFile($contentData, true);
				$small = $contentDataModel->getContentDataSmallThumbnailFile($contentData, true);
				$smallStore = $contentDataModel->getStoreContentDataSmallThumbnailFile($contentData);
				$mediumStore = $contentDataModel->getStoreContentDataMediumThumbnailFile($contentData);
				$largeStore = $contentDataModel->getStoreContentDataLargeThumbnailFile($contentData);

				$successCount = 0;

				if (!$disableOriginal && !empty($contentSize))
				{
					$original = $contentDataModel->getContentDataFile($contentData, true);
					$originalStore = $contentDataModel->getStoreContentDataFile($contentData);
					if ($fileModel->saveFile($defaultEngine, $storeOptions, $original, $originalStore, basename($originalStore)))
					{
						$successCount++;
					}
				}

				if ($fileModel->saveFile($defaultEngine, $storeOptions, $large, $largeStore, basename($largeStore)))
				{
					$successCount++;
				}

				if ($fileModel->saveFile($defaultEngine, $storeOptions, $medium, $mediumStore, basename($mediumStore)))
				{
					$successCount++;
				}

				if ($fileModel->saveFile($defaultEngine, $storeOptions, $small, $smallStore, basename($smallStore)))
				{
					$successCount++;
				}

				if ($successCount === $needSuccess)
				{
					if (empty($storeOptions['keepLocalCopy']))
					{
						if (!$disableOriginal && !empty($contentSize))
						{
							@unlink($original);
						}
						@unlink($large);
						@unlink($medium);
						@unlink($small);
					}

					$this->bulkSet(array(
						'bdattachmentstore_engine' => $defaultEngine,
						'bdattachmentstore_options' => $storeOptions
					));
				}
			}
		}
		else
		{
			$engine = $contentData['bdattachmentstore_engine'];
			$fileModel = $this->_bdAttachmentStore_getFileModel();
			$engineOptions = @unserialize($contentData['bdattachmentstore_options']);

			if (!empty($engine))
			{
				$successCount = 0;
				if (!empty($storeOptions['keepLocalCopy']))
				{
					$large = $contentDataModel->getContentDataLargeThumbnailFile($contentData, true);
					$medium = $contentDataModel->getContentDataMediumThumbnailFile($contentData, true);
					$small = $contentDataModel->getContentDataSmallThumbnailFile($contentData, true);
					$smallStore = $contentDataModel->getStoreContentDataSmallThumbnailFile($contentData);
					$mediumStore = $contentDataModel->getStoreContentDataMediumThumbnailFile($contentData);
					$largeStore = $contentDataModel->getStoreContentDataLargeThumbnailFile($contentData);

					if (!$disableOriginal && !empty($contentSize))
					{
						$original = $contentDataModel->getContentDataFile($contentData, true);
						$originalStore = $contentDataModel->getStoreContentDataFile($contentData);

						if (is_file($original))
						{
							$successCount++;
						}
						else
						{
							$originalTemp = $fileModel->getAccessibleFilePath($engine, $engineOptions, $originalStore, true);
							if ($originalTemp)
							{
								if ($this->_writeDataFileLocal($originalTemp, $contentData, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_ORIGINAL))
								{
									$successCount++;
								}
							}
						}
					}

					if (is_file($large))
					{
						$successCount++;
					}
					else
					{
						$largeTemp = $fileModel->getAccessibleFilePath($engine, $engineOptions, $largeStore, true);
						if ($largeTemp)
						{
							if ($this->_writeDataFileLocal($largeTemp, $contentData, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_LARGE))
							{
								$successCount++;
							}
						}
					}

					if (is_file($medium))
					{
						$successCount++;
					}
					else
					{
						$mediumTemp = $fileModel->getAccessibleFilePath($engine, $engineOptions, $mediumStore, true);
						if ($mediumTemp)
						{
							if ($this->_writeDataFileLocal($mediumTemp, $contentData, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_MEDIUM))
							{
								$successCount++;
							}
						}
					}

					if (is_file($small))
					{
						$successCount++;
					}
					else
					{
						$smallTemp = $fileModel->getAccessibleFilePath($engine, $engineOptions, $smallStore, true);
						if ($smallTemp)
						{
							if ($this->_writeDataFileLocal($smallTemp, $contentData, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_SMALL))
							{
								$successCount++;
							}
						}
					}
				}
				else
				{
					$smallStore = $contentDataModel->getStoreContentDataSmallThumbnailFile($contentData);
					$mediumStore = $contentDataModel->getStoreContentDataMediumThumbnailFile($contentData);
					$largeStore = $contentDataModel->getStoreContentDataLargeThumbnailFile($contentData);

					if (!$disableOriginal && !empty($contentSize))
					{
						$originalStore = $contentDataModel->getStoreContentDataFile($contentData);
						$originalTemp = $fileModel->getAccessibleFilePath($engine, $engineOptions, $originalStore, true);
						if ($originalTemp)
						{
							if ($this->_writeDataFileLocal($originalTemp, $contentData, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_ORIGINAL))
							{
								$successCount++;
							}
						}
					}

					$largeTemp = $fileModel->getAccessibleFilePath($engine, $engineOptions, $largeStore, true);
					if ($largeTemp)
					{
						if ($this->_writeDataFileLocal($largeTemp, $contentData, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_LARGE))
						{
							$successCount++;
						}
					}

					$mediumTemp = $fileModel->getAccessibleFilePath($engine, $engineOptions, $mediumStore, true);
					if ($mediumTemp)
					{
						if ($this->_writeDataFileLocal($mediumTemp, $contentData, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_MEDIUM))
						{
							$successCount++;
						}
					}

					$smallTemp = $fileModel->getAccessibleFilePath($engine, $engineOptions, $smallStore, true);
					if ($smallTemp)
					{
						if ($this->_writeDataFileLocal($smallTemp, $contentData, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_SMALL))
						{
							$successCount++;
						}
					}
				}

				if ($successCount === $needSuccess)
				{
					if (!$disableOriginal && !empty($contentSize))
					{
						$fileModel->deleteFile($engine, $engineOptions, $originalStore);
					}
					$fileModel->deleteFile($engine, $engineOptions, $largeStore);
					$fileModel->deleteFile($engine, $engineOptions, $mediumStore);
					$fileModel->deleteFile($engine, $engineOptions, $smallStore);

					$this->bulkSet(array(
						'bdattachmentstore_engine' => '',
						'bdattachmentstore_options' => 'a:0:{}'
					));
				}
			}
		}
	}
	
	protected function _writeDataFile($tempFile, array $data, $thumbnail = false)
	{
		if (empty($data['bdattachmentstore_engine']) || !class_exists('bdAttachmentStore_Model_File'))
		{
			return $this->_writeDataFileLocal($tempFile, $data, $thumbnail);
		}

		$fileModel = $this->_bdAttachmentStore_getFileModel();
		$engine = $data['bdattachmentstore_engine'];
		$engineOptions = @unserialize($data['bdattachmentstore_options']);
		$keepLocalCopy = !empty($engineOptions['keepLocalCopy']);
		$contentDataModel = $this->_getContentDataModel();

		if ($keepLocalCopy)
		{
			$tempFile2 = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			$tempFile3 = tempnam(XenForo_Helper_File::getTempDir(), 'xf');

			if ($this->_moveFile($tempFile, $tempFile2) === false)
			{
				return false;
			}

			if (@copy($tempFile2, $tempFile3) === false)
			{
				return false;
			}

			$this->_writeDataFileLocal($tempFile2, $data, $thumbnail);
			$tempFile = $tempFile3;
		}

		switch ($thumbnail)
		{
            case sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_SMALL:
				$filePath = $contentDataModel->getStoreContentDataSmallThumbnailFile($data);
				break;
            case sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_MEDIUM:
				$filePath = $contentDataModel->getStoreContentDataMediumThumbnailFile($data);
				break;
            case sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_LARGE:
				$filePath = $contentDataModel->getStoreContentDataLargeThumbnailFile($data);
				break;
			default:
				$filePath = $contentDataModel->getStoreContentDataFile($data);
				break;
		}

		$success = $fileModel->saveFile($engine, $engineOptions, $tempFile, $filePath, basename($filePath));

		@unlink($tempFile);

		return $success;
	}

	protected function _writeDataFileLocal($tempFile, array $data, $thumbnail = false)
	{
		$success = false;

		if ($tempFile && is_file($tempFile) && is_readable($tempFile))
		{
			$contentDataModel = $this->_getContentDataModel();

			switch ($thumbnail)
			{
                case sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_SMALL:
					$filePath = $contentDataModel->getContentDataSmallThumbnailFile($data, true);
					break;
                case sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_MEDIUM:
					$filePath = $contentDataModel->getContentDataMediumThumbnailFile($data, true);
					break;
                case sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_LARGE:
					$filePath = $contentDataModel->getContentDataLargeThumbnailFile($data, true);
					break;
				default:
					$filePath = $contentDataModel->getContentDataFile($data, true);
					break;
			}

			$directory = dirname($filePath);

			if (XenForo_Helper_File::createDirectory($directory, true))
			{
				$success = $this->_moveFile($tempFile, $filePath);
			}
		}

		@unlink($tempFile);

		return $success;
	}
	
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'content_data_id'))
		{
			return false;
		}
	
		return array('sonnb_xengallery_content_data' => $this->_getContentDataModel()->getDataByDataId($id));
	}
	
	protected function _getUpdateCondition($tableName)
	{
		return 'content_data_id = ' . $this->_db->quote($this->getExisting('content_data_id'));
	}
}