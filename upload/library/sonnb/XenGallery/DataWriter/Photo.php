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
class sonnb_XenGallery_DataWriter_Photo extends sonnb_XenGallery_DataWriter_Content
{
	protected function _getFields()
	{
		$fields = parent::_getFields();

		$fields['sonnb_xengallery_photo'] = array(
			'content_id' => array(
				'type' => self::TYPE_UINT,
				'default' => array('sonnb_xengallery_content', 'content_id')
			),
			'photo_exif' => array(
				'type' => self::TYPE_SERIALIZED,
				'default' => 'a:0:{}'
			)
		);

		return $fields;
	}

	protected function _getDefaultPrivacy($contentType, $action)
	{
		if (empty($contentType))
		{
			$contentType = sonnb_XenGallery_Model_Photo::$contentType;
		}

		return parent::_getDefaultPrivacy($contentType, $action);
	}

	protected function _preSave()
	{
		$this->set('content_type', sonnb_XenGallery_Model_Photo::$contentType);

		parent::_preSave();
	}

	protected function _postSave()
	{
		parent::_postSave();

		if ($this->isInsert())
		{
			//TODO: process EXIF
			if ($exif = @unserialize($this->get('photo_exif')))
			{
				if (!empty($exif['Model']) && utf8_strlen($exif['Model']))
				{
					$this->_db->insert('sonnb_xengallery_photo_camera', array(
						'photo_id' => $this->get('content_id'),
						'camera_name' => $exif['Model'],
						'camera_url' => sonnb_XenGallery_Model_Gallery::getTitleForUrl($exif['Model'])
					));
				}
			}
		}
	}
	
	public function rebuildCounters(array $options = array(), array $contentData = null)
	{
		$contentDataModel = $this->_getPhotoDataModel();
		$xenOptions = XenForo_Application::getOptions();

		$options = array_merge(array(
			'exif' => false,
			'user' => false,
			'index' => true,
			'streams' => false,
			'tags' => false,
			'thumbnail' => false,

			'thumbnail_information' => false,
			'apply_watermark' => false,

			'move' => false,
			'move_target' => 'local',

			'delete_original' => false
		), $options);

		if ($contentData === null)
		{
			$contentData = $contentDataModel->getDataByDataId($this->get('content_data_id'));
		}

		parent::rebuildCounters($options, $contentData);

		if (!empty($contentData)
				&& (empty($contentData['large_height']) || empty($contentData['large_width']) || $options['thumbnail_information']))
		{
			/* @var sonnb_XenGallery_DataWriter_ContentData $dataDw */
			$dataDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_ContentData');
			$dataDw->setExistingData($contentData['content_data_id']);
			$dataDw->rebuildThumbnailSize();
			$dataDw->save();
		}

		if (!XenForo_Application::getOptions()->sonnbXG_disableOriginal && !empty($contentData) && $options['exif'])
		{
			$db = $this->_db;

			$filePath =  $this->_getContentDataModel()->getContentDataFile($contentData);
			$exif = $this->_getPhotoModel()->getPhotoExif($contentData, $filePath);

			$this->set('is_animated', $this->_getPhotoModel()->isAnimatedGif($filePath) ? 1 : 0);
			$this->set('photo_exif', $exif);

			if (!empty($exif['Model']) && utf8_strlen($exif['Model']))
			{
				$photoId = $this->get('content_id');

				$db->insert('sonnb_xengallery_photo_camera', array(
					'photo_id' => $photoId,
					'camera_name' => $exif['Model'],
					'camera_url' => sonnb_XenGallery_Model_Gallery::getTitleForUrl($exif['Model'])
				));
			}
		}

		if ($options['delete_original'] && $xenOptions->sonnbXG_disableOriginal)
		{
			//Remove original photos only if disabled original is selected.
			$contentDataModel->deleteContentDataFile($contentData, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_ORIGINAL);
		}

		if (!empty($contentData) && $options['thumbnail'] && !$xenOptions->sonnbXG_disableOriginal)
		{
			$originalFile = $contentDataModel->getContentDataFile($contentData);
			if (is_file($originalFile))
			{
				$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
				$contentDataModel->copyFile($originalFile, $tempFile);
				$outputType = sonnb_XenGallery_Model_ContentData::$typeMap[$contentData['extension']];

				$contentDataModel->exifRotate($tempFile, $outputType, $tempFile);

				$largeFile = $contentDataModel->getContentDataLargeThumbnailFile($contentData, true);
				$mediumFile = $contentDataModel->getContentDataMediumThumbnailFile($contentData, true);
				$smallFile = $contentDataModel->getContentDataSmallThumbnailFile($contentData, true);

				$dimensions = array();
				$contentDataModel->createContentDataThumbnailFile($tempFile, $largeFile, $outputType, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_LARGE, $fallback, $dimensions, true);
				$contentDataModel->createContentDataThumbnailFile($largeFile, $mediumFile, $outputType, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_MEDIUM, $fallback, $dimensions);
				$contentDataModel->createContentDataThumbnailFile($largeFile, $smallFile, $outputType, sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_SMALL, $fallback, $dimensions);

				$engine = $contentData['bdattachmentstore_engine'];
				$storeOptions = @unserialize($contentData['bdattachmentstore_options']);

				if (!empty($engine) && !empty($storeOptions))
				{
                    $fileModel = $this->_bdAttachmentStore_getFileModel();
					$smallThumbFileStore = $contentDataModel->getStoreContentDataSmallThumbnailFile($contentData);
					$mediumThumbFileStore = $contentDataModel->getStoreContentDataMediumThumbnailFile($contentData);
					$largeThumbFileStore = $contentDataModel->getStoreContentDataLargeThumbnailFile($contentData);

					$fileModel->saveFile($engine, $storeOptions, $largeFile, $largeThumbFileStore, basename($largeThumbFileStore));
					$fileModel->saveFile($engine, $storeOptions, $mediumFile, $mediumThumbFileStore, basename($mediumThumbFileStore));
					$fileModel->saveFile($engine, $storeOptions, $smallFile, $smallThumbFileStore, basename($smallThumbFileStore));

					if (empty($storeOptions['keepLocalCopy']))
					{
						@unlink($largeFile);
						@unlink($mediumFile);
						@unlink($smallFile);
					}
				}

				if (!empty($dimensions))
				{
					$this->_db->update(
						'sonnb_xengallery_content_data',
						$dimensions,
						array(
							'content_data_id = ?' => $contentData['content_data_id']
						)
					);
				}

				@unlink($tempFile);
			}
		}
	}

	protected function _getExistingData($data)
	{
		$content = false;
		$contentId = $this->_getExistingPrimaryKey($data, 'content_id');
		$contentDataId = $this->_getExistingPrimaryKey($data, 'content_data_id');
		$fetchOptions = array('join' => sonnb_XenGallery_Model_Photo::FETCH_PHOTO);

		if ($contentId)
		{
			$content = $this->_getPhotoModel()->getContentByContentId(sonnb_XenGallery_Model_Photo::$contentType, $contentId, $fetchOptions);
		}

		if (empty($content) && $contentDataId)
		{
			$content = $this->_getPhotoModel()->getContentByDataId($contentDataId, $fetchOptions);
		}

		if (!$content)
		{
			return false;
		}

		return $this->getTablesDataFromArray($content);
	}
}