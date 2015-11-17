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
class sonnb_XenGallery_Model_Import extends XenForo_Model_Import
{
	public function importXenGalleryCategory($oldId, array $import, $importMode = true)
	{
        $newId = $this->importXenGallery($oldId, $import, 'sonnb_XenGallery_DataWriter_Category', 'category_id', $importMode);

        XenForo_Model::create('sonnb_XenGallery_Model_Category')->rebuildCategoryStructure();

		return $newId;
	}

	public function importXenGalleryCollection($oldId, array $import, $importMode = true)
	{
        return $this->importXenGallery($oldId, $import, 'sonnb_XenGallery_Model_Collection', 'collection_id', $importMode);
	}

	public function importXenGalleryAlbum($oldId, array $import, $importMode = true)
	{
        return $this->importXenGallery($oldId, $import, 'sonnb_XenGallery_DataWriter_Album', 'album_id', $importMode);
	}

	public function importXenGalleryPhoto($oldId, array $import, $importMode = true)
	{
        $import['content_type'] = sonnb_XenGallery_Model_Photo::$contentType;

        return $this->importXenGallery($oldId, $import, 'sonnb_XenGallery_DataWriter_Photo', 'content_id', $importMode);
	}

	public function importXenGalleryVideo($oldId, array $import, $importMode = true)
	{
		$import['content_type'] = sonnb_XenGallery_Model_Video::$contentType;

		return $this->importXenGallery($oldId, $import, 'sonnb_XenGallery_DataWriter_Video', 'content_id', $importMode);
	}

	public function importXenGalleryPhotoData($oldId, array $import, $importMode = true)
	{
        return $this->importXenGalleryContentData($oldId, $import, sonnb_XenGallery_Model_Photo::$contentType, $importMode);
	}

	public function importXenGalleryVideoData($oldId, array $import, $importMode = true)
	{
		return $this->importXenGalleryContentData($oldId, $import, sonnb_XenGallery_Model_Video::$contentType, $importMode);
	}

	public function importXenGalleryContentData($oldId, array $import, $contentType, $importMode = true)
	{
        $import['content_type'] = $contentType;

        return $this->importXenGallery($oldId, $import, 'sonnb_XenGallery_DataWriter_ContentData', 'content_data_id', $importMode, true);
	}

	public function importXenGalleryComment($oldId, array $import, $importMode = true)
	{
        return $this->importXenGallery($oldId, $import, 'sonnb_XenGallery_DataWriter_Comment', 'comment_id', $importMode);
	}

    public function importXenGalleryField($oldId, array $import, $importMode = true)
    {
        return $this->importXenGallery($oldId, $import, 'sonnb_XenGallery_DataWriter_Field', 'field_id', $importMode);
    }

	public function importXenGalleryStream($oldId, array $import, $importMode = true)
	{
        return $this->importXenGallery($oldId, $import, 'sonnb_XenGallery_DataWriter_Stream', 'stream_id', $importMode);
	}

	public function importXenGalleryTag($oldId, array $import, $importMode = true)
	{
        return $this->importXenGallery($oldId, $import, 'sonnb_XenGallery_DataWriter_Tag', 'tag_id', $importMode);
	}

	public function importXenGalleryWatch($oldId, array $import, $importMode = true)
	{
        return $this->importXenGallery($oldId, $import, 'sonnb_XenGallery_DataWriter_Watch', 'watch_id', $importMode);
	}

	public function importXenGalleryLocation($oldId, array $import, $importMode = true)
	{
        return $this->importXenGallery($oldId, $import, 'sonnb_XenGallery_DataWriter_Location', 'location_id', $importMode);
	}

    public function importXenGallery($oldId, array $import, $dwClass, $key, $importMode = true, $returnFullData = false)
    {
        XenForo_Db::beginTransaction();

        $dw = XenForo_DataWriter::create($dwClass, XenForo_DataWriter::ERROR_SILENT);
        if ($importMode)
        {
            $dw->setImportMode(true);
        }
        $dw->bulkSet($import, array('ignoreInvalidFields' => true));

        $newId = false;
        if ($dw->save())
        {
            if ($returnFullData)
            {
                $newId = $dw->getMergedData();
            }
            else
            {
                $newId = $dw->get($key);
            }
        }

        XenForo_Db::commit();

        return $newId;
    }

    public function importXenGalleryContentDataConfirm(array $contentData)
    {
        $db = $this->_getDb();

        $db->update(
            'sonnb_xengallery_content_data',
            array(
                'unassociated' => 0
            ),
            'content_data_id = '. $db->quote($contentData['content_data_id'])
        );
    }

    public function importXenGalleryCollectionItem($oldId, array $import, $importMode = true)
    {
        $db = XenForo_Application::getDb();
        XenForo_Db::beginTransaction($db);

        $db->insert('sonnb_xengallery_collection_item', $import);

        return 0;
    }

	public function getAlbumIdsMapFromArray(array $source, $key)
	{
		return $this->getXenGalleryImportContentMap('sonnb_xengallery_album', $this->_getItemIds($source, $key));
	}

	public function getPhotoIdsMapFromArray(array $source, $key)
	{
		return $this->getXenGalleryImportContentMap('sonnb_xengallery_photo', $this->_getItemIds($source, $key));
	}

	public function getVideoIdsMapFromArray(array $source, $key)
	{
		return $this->getXenGalleryImportContentMap('sonnb_xengallery_video', $this->_getItemIds($source, $key));
	}

	public function getCategoryIdsMapFromArray(array $source, $key)
	{
		return $this->getXenGalleryImportContentMap('sonnb_xengallery_category', $this->_getItemIds($source, $key));
	}

    public function getCategoryAlbumIdsMapFromArray(array $source, $key)
    {
        return $this->getXenGalleryImportContentMap('sonnb_xengallery_catalbum', $this->_getItemIds($source, $key));
    }

    public function getContentIdsMapFromArray(array $source, $key)
    {
        return $this->getXenGalleryImportContentMap('sonnb_xengallery_content', $this->_getItemIds($source, $key));
    }

    public function getFieldsIdsMapFromArray(array $source, $key)
    {
        return $this->getXenGalleryImportContentMap('sonnb_xengallery_field', $this->_getItemIds($source, $key));
    }

	public function getCollectionIdsMapFromArray(array $source, $key)
	{
		return $this->getXenGalleryImportContentMap('sonnb_xengallery_col', $this->_getItemIds($source, $key));
	}

    protected function _getItemIds(array $source, $key)
    {
        $itemIds = array();
        foreach ($source AS $data)
        {
            if (is_array($key))
            {
                foreach ($key AS $_key)
                {
                    $itemIds[] = $data[$_key];
                }
            }
            else
            {
                $itemIds[] = $data[$key];
            }
        }

        return array_unique($itemIds);
    }

	public function getXenGalleryImportContentMap($contentType, $ids = null)
	{
		$logTable = 'xf_import_log';

		$db = $this->_getDb();

		if ($ids === null)
		{
			return $db->fetchPairs('
				SELECT old_id, new_id
				FROM ' . $logTable . '
				WHERE content_type = ?
			', $contentType);
		}

		if (!is_array($ids))
		{
			$ids = array($ids);
		}
		if (!$ids)
		{
			return array();
		}

		$final = array();
		if (isset($this->_contentMapCache[$contentType]))
		{
			$lookup = $this->_contentMapCache[$contentType];
			foreach ($ids AS $key => $id)
			{
				if (isset($lookup[$id]))
				{
					$final[$id] = $lookup[$id];
					unset($ids[$key]);
				}
			}
		}

		if (!$ids)
		{
			return $final;
		}

		foreach ($ids AS &$id)
		{
			$id = strval($id);
		}

		$merge = $db->fetchPairs('
			SELECT old_id, new_id
			FROM ' . $logTable . '
			WHERE content_type = ?
				AND old_id IN (' . $db->quote($ids) . ')
		', $contentType);

		if (isset($this->_contentMapCache[$contentType]))
		{
			$this->_contentMapCache[$contentType] += $merge;
		}
		else
		{
			$this->_contentMapCache[$contentType] = $merge;
		}

		return $final + $merge;
	}

	public function importContentLike($contentType, $contentId, $newContentType, $newContentId, $userId)
	{
		$db = $this->_getDb();

		$likes = $db->fetchAll("
				SELECT *
				  FROM xf_liked_content
				WHERE content_id = ? AND content_type = ?
			", array($contentId, $contentType));

		if ($likes)
		{
			$db->update(
				'xf_user',
				array(
					'like_count' => new Zend_Db_Expr('like_count + '.count($likes))
				),
				'user_id = '.$userId
			);

			foreach ($likes as $like)
			{
				$db->insert('xf_liked_content', array(
					'content_type' => $newContentType,
					'content_id' => $newContentId,
					'like_user_id' => $like['like_user_id'],
					'like_date' => $like['like_date'],
					'content_user_id' => $userId
				));
			}
		}
	}

	public function createPhotoThumbnails($filename, $data, $importToStore = false)
	{
        $xenOptions = XenForo_Application::getOptions();
		$photoDataModel = $this->_getPhotoDataModel();
		$extensionType = sonnb_XenGallery_Model_ContentData::$typeMap;

		$smallThumbFile = $photoDataModel->getContentDataSmallThumbnailFile($data);
		$mediumThumbFile = $photoDataModel->getContentDataMediumThumbnailFile($data);
		$largeThumbFile = $photoDataModel->getContentDataLargeThumbnailFile($data);
		$originalFile = $photoDataModel->getContentDataFile($data);

		if ($originalFile && !$xenOptions->sonnbXG_disableOriginal)
		{
			$directory = dirname($originalFile);
			if (XenForo_Helper_File::createDirectory($directory, true))
			{
				@copy($filename, $originalFile);
				XenForo_Helper_File::makeWritableByFtpUser($originalFile);
			}
		}

		$photoDataModel->createContentDataThumbnailFile($filename, $largeThumbFile, $extensionType[$data['extension']], sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_LARGE);
		$photoDataModel->createContentDataThumbnailFile($largeThumbFile, $mediumThumbFile, $extensionType[$data['extension']], sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_MEDIUM);
		$photoDataModel->createContentDataThumbnailFile($largeThumbFile, $smallThumbFile, $extensionType[$data['extension']], sonnb_XenGallery_Model_ContentData::CONTENT_FILE_TYPE_SMALL);

		$db = $this->_getDb();
        if ($importToStore !== false)
        {
            $useAttachmentStore = $xenOptions->sonnbXG_useBdStore;
            $engine = '';

            if (XenForo_Application::isRegistered('addOns'))
            {
                $addOns = XenForo_Application::get('addOns');
            }

            if (!class_exists('bdAttachmentStore_Option') || empty($addOns['bdAttachmentStore']))
            {
                $useAttachmentStore = false;
            }

            if ($useAttachmentStore)
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
                    $engine = $defaultEngine;
                }
            }

            if (!empty($engine))
            {
                $fileModel = $this->_bdAttachmentStore_getFileModel();
                $storeOptions = $fileModel->getStorageOptions($engine);
                if (!isset($storeOptions['keepLocalCopy']))
                {
                    $storeOptions['keepLocalCopy'] = bdAttachmentStore_Option::get('keepLocalCopy');
                }

                $smallThumbFileStore = $photoDataModel->getStoreContentDataSmallThumbnailFile($data);
                $mediumThumbFileStore = $photoDataModel->getStoreContentDataMediumThumbnailFile($data);
                $largeThumbFileStore = $photoDataModel->getStoreContentDataLargeThumbnailFile($data);
                $originalFileStore = $photoDataModel->getStoreContentDataFile($data);

                if (!$xenOptions->sonnbXG_disableOriginal)
                {
                    $fileModel->saveFile($engine, $storeOptions, $originalFile, $originalFileStore, basename($originalFileStore));
                }

	            $fileModel->saveFile($engine, $storeOptions, $largeThumbFile, $largeThumbFileStore, basename($largeThumbFileStore));
	            $fileModel->saveFile($engine, $storeOptions, $mediumThumbFile, $mediumThumbFileStore, basename($mediumThumbFileStore));
	            $fileModel->saveFile($engine, $storeOptions, $smallThumbFile, $smallThumbFileStore, basename($smallThumbFileStore));

	            $db->update(
		            'sonnb_xengallery_content_data',
		            array(
			            'bdattachmentstore_engine' => $engine,
			            'bdattachmentstore_options' => serialize($storeOptions)
		            ),
	                array(
		                'content_data_id = ?' => $data['content_data_id']
	                )
	            );

                if (empty($storeOptions['keepLocalCopy']))
                {
                    @unlink($smallThumbFile);
                    @unlink($mediumThumbFile);
                    @unlink($largeThumbFile);
                    @unlink($originalFile);
                }
            }
        }

		$db->update(
			'sonnb_xengallery_content_data',
			array(
				'unassociated' => 0
			),
			array(
				'content_data_id = ?' => $data['content_data_id']
			)
		);
	}

	/**
	 * @return sonnb_XenGallery_Model_PhotoData
	 */
	protected function _getPhotoDataModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_PhotoData');
	}

    /**
     * @return bdAttachmentStore_Model_File
     */
    protected function _bdAttachmentStore_getFileModel()
    {
        return $this->getModelFromCache('bdAttachmentStore_Model_File');
    }
}