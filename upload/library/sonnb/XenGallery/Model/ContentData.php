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
class sonnb_XenGallery_Model_ContentData extends sonnb_XenGallery_Model_Abstract
{
    const CONTENT_FILE_TYPE_SMALL = 'small';
    const CONTENT_FILE_TYPE_MEDIUM = 'medium';
    const CONTENT_FILE_TYPE_LARGE = 'large';
    const CONTENT_FILE_TYPE_ORIGINAL = 'original';

	public static $imageExtension = array('jpg','jpeg','gif','png', 'jpe');
	public static $videoExtension = array('flv','wmv','avi','mpeg', 'mkv', 'mov', 'mp4', '3gp');
	public static $videoEmbedExtension = 'jpg';

	public static $typeMap = array(
		'gif' => IMAGETYPE_GIF,
		'jpg' => IMAGETYPE_JPEG,
		'jpeg' => IMAGETYPE_JPEG,
		'jpe' => IMAGETYPE_JPEG,
		'png' => IMAGETYPE_PNG
	);

	public static $extensionMap = array(
		IMAGETYPE_GIF => 'gif',
		IMAGETYPE_JPEG => 'jpg',
		IMAGETYPE_PNG => 'png'
	);
	public static $imageMimes = array(
		'gif' => 'image/gif',
		'jpg' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'jpe' => 'image/jpeg',
		'png' => 'image/png'
	);
	public static $videoMimes = array(
		'flv' => 'video/x-flv',
		'wmv' => 'video/x-ms-wmv',
		'avi' => 'video/x-msvideo',
		'mpeg' => 'video/mpeg',
		'mkv' => 'video/x-matroska',
		'mov' => 'video/quicktime',
		'mp4' => 'video/mp4',
		'3gp' => 'video/3gpp',
	);

	//Just for fallback
	public static $thumbnailSmall = 120;
	public static $thumbnailMedium = 300;
	public static $thumbnailLarge = 1280;

	public static $videoNoThumbnail = 'styles/sonnb/XenGallery/video-thumbnail.jpg';

	public function getThumbnailSize($thumbnailType)
	{
		$xenOptions = XenForo_Application::getOptions();
		switch ($thumbnailType)
		{
			case self::CONTENT_FILE_TYPE_LARGE:
				$size = $xenOptions->get('sonnbXG_thumbnailSizeLarge');
				if ($size < 10)
				{
					$size = self::$thumbnailLarge;
				}
				break;
			case self::CONTENT_FILE_TYPE_MEDIUM:
				$size = $xenOptions->get('sonnbXG_thumbnailSizeMedium');
				if ($size < 10)
				{
					$size = self::$thumbnailMedium;
				}
				break;
			case self::CONTENT_FILE_TYPE_SMALL:
			default:
				$size = $xenOptions->get('sonnbXG_thumbnailSizeSmall');
				if ($size < 10)
				{
					$size = self::$thumbnailSmall;
				}
				break;
		}

		return $size;
	}

	public function getDataByDataId($id, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
		 
		$conditions['content_data_id'] = $id;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getData($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}
	
	public function getDataByDataIds($ids, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$ids)
        {
            return array();
        }
        
        $conditions['content_data_id'] = $ids;
        
        return $this->getData($conditions, $fetchOptions);
	}
	
	public function getDataByHash($id, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
		
		$conditions['temp_hash'] = $id;
		
		return $this->getData($conditions, $fetchOptions);
	}
	
	public function getData(array $conditions = array(), array $fetchOptions = array())
	{		 
		$whereConditions = $this->prepareDataConditions($conditions, $fetchOptions);
		
		$sqlClauses = $this->prepareDataFetchOptions($fetchOptions);
		
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		
		return $this->fetchAllKeyed(
					$this->limitQueryResults(
						'
		                   SELECT content_data.*
		                        ' . $sqlClauses['selectFields'] . '
		                   FROM `sonnb_xengallery_content_data` AS content_data
		                    	' . $sqlClauses['joinTables'] . '
		                   WHERE ' . $whereConditions . '
		                    	' . $sqlClauses['orderClause'] . '
	                	', $limitOptions['limit'], $limitOptions['offset']
					), 'content_data_id'
				);
	}
	
	public function countData(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareDataConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareDataFetchOptions($fetchOptions);
		
		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_content_data` AS content_data
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
            ');
	}

	public function countDataByHash($hash, array $conditions = array(), array $fetchOptions = array())
	{
		if (empty($hash))
		{
			return 0;
		}

		$conditions['temp_hash'] = $hash;

		return $this->countData($conditions, $fetchOptions);
	}

	public function prepareDataSingle(array $data, array $fetchOptions = array())
	{
		if (!empty($data))
		{
			$data['thumbnailUrl'] = $this->getContentDataMediumThumbnailUrl($data);
		}

		return $data;
	}
	
	public function prepareData(array $data, array $fetchOptions = array())
	{
		if (!empty($data))
		{
			foreach ($data as &$_data)
			{
				$_data = $this->prepareDataSingle($data, $fetchOptions);
			}
		}
		
		return $data;
	}
	
	public function associateContentData($hash)
	{
		if (empty($hash))
		{
			return false;
		}
		
		$this->_db->update('sonnb_xengallery_content_data', array(
				'temp_hash' => '',
				'unassociated' => 0
		), 'temp_hash = ' . $this->_db->quote($hash));
	}
	
	public function getContentDataFile(array $data, $forceLocal = false, $externalDataPath = null)
	{
		if (XenForo_Application::getOptions()->sonnbXG_disableOriginal || empty($data['content_type']))
		{
			return null;
		}

		if (!empty($data['bdattachmentstore_engine']) && $forceLocal === false)
		{
			$fileModel = $this->_bdAttachmentStore_getFileModel();
			$engine = $data['bdattachmentstore_engine'];
			$engineOptions = @unserialize($data['bdattachmentstore_options']);
			$filePath = $this->getStoreContentDataFile($data);

			$file = $fileModel->getAccessibleFilePath($engine, $engineOptions, $filePath, true);

			if (is_file($file))
			{
				return $file;
			}
		}

		if ($externalDataPath === null)
		{
			$externalDataPath = XenForo_Helper_File::getExternalDataPath();
		}

		return sprintf('%s/'.$data['content_type'].'s/o/%d/%d-%d-%s.%s',
			$externalDataPath,
			floor($data['content_data_id'] / 1000),
			$data['content_data_id'],
			$data['upload_date'],
			md5('o'.$data['file_hash']),
			$data['extension']
		);
	}
	
	public function getContentDataSmallThumbnailFile(array $data, $forceLocal = false, $externalDataPath = null)
	{
		if (empty($data['content_type']))
		{
			return null;
		}

		if (!empty($data['bdattachmentstore_engine']) && $forceLocal === false)
		{
			$fileModel = $this->_bdAttachmentStore_getFileModel();
			$engine = $data['bdattachmentstore_engine'];
			$engineOptions = @unserialize($data['bdattachmentstore_options']);
			$filePath = $this->getStoreContentDataSmallThumbnailFile($data);

			$file = $fileModel->getAccessibleFilePath($engine, $engineOptions, $filePath, true);

			if (is_file($file))
			{
				return $file;
			}
		}

		if ($externalDataPath === null)
		{
			$externalDataPath = XenForo_Helper_File::getExternalDataPath();
		}

		if (!empty($data['content_type']) && $data['content_type'] === sonnb_XenGallery_Model_Video::$contentType)
		{
			$data['extension'] = self::$videoEmbedExtension;
		}

		return sprintf('%s/'.$data['content_type'].'s/s/%d/%d-%d-%s.%s',
			$externalDataPath,
			floor($data['content_data_id'] / 1000),
			$data['content_data_id'],
			$data['upload_date'],
			md5('s'.$data['file_hash']),
			$data['extension']
		);
	}
	
	public function getContentDataMediumThumbnailFile(array $data, $forceLocal = false, $externalDataPath = null)
	{
		if (empty($data['content_type']))
		{
			return null;
		}

		if (!empty($data['bdattachmentstore_engine']) && $forceLocal === false)
		{
			$fileModel = $this->_bdAttachmentStore_getFileModel();
			$engine = $data['bdattachmentstore_engine'];
			$engineOptions = @unserialize($data['bdattachmentstore_options']);
			$filePath = $this->getStoreContentDataMediumThumbnailFile($data);

			$file = $fileModel->getAccessibleFilePath($engine, $engineOptions, $filePath, true);

			if (is_file($file))
			{
				return $file;
			}
		}

		if ($externalDataPath === null)
		{
			$externalDataPath = XenForo_Helper_File::getExternalDataPath();
		}

		if (!empty($data['content_type']) && $data['content_type'] === sonnb_XenGallery_Model_Video::$contentType)
		{
			$data['extension'] = self::$videoEmbedExtension;
		}

		return sprintf('%s/'.$data['content_type'].'s/m/%d/%d-%d-%s.%s',
			$externalDataPath,
			floor($data['content_data_id'] / 1000),
			$data['content_data_id'],
			$data['upload_date'],
			md5('m'.$data['file_hash']),
			$data['extension']
		);
	}
	
	public function getContentDataLargeThumbnailFile(array $data, $forceLocal = false, $externalDataPath = null)
	{
		if (empty($data['content_type']))
		{
			return null;
		}

		if (!empty($data['bdattachmentstore_engine']) && $forceLocal === false)
		{
			$fileModel = $this->_bdAttachmentStore_getFileModel();
			$engine = $data['bdattachmentstore_engine'];
			$engineOptions = @unserialize($data['bdattachmentstore_options']);
			$filePath = $this->getStoreContentDataLargeThumbnailFile($data);

			$file = $fileModel->getAccessibleFilePath($engine, $engineOptions, $filePath, true);

			if (is_file($file))
			{
				return $file;
			}
		}

		if ($externalDataPath === null)
		{
			$externalDataPath = XenForo_Helper_File::getExternalDataPath();
		}

		if (!empty($data['content_type']) && $data['content_type'] === sonnb_XenGallery_Model_Video::$contentType)
		{
			$data['extension'] = self::$videoEmbedExtension;
		}

		return sprintf('%s/'.$data['content_type'].'s/l/%d/%d-%d-%s.%s',
			$externalDataPath,
			floor($data['content_data_id'] / 1000),
			$data['content_data_id'],
			$data['upload_date'],
			md5('l'.$data['file_hash']),
			$data['extension']
		);
	}
	
	public function getContentDataUrl(array $data, $forceLocal = false, $externalDataPath = null)
	{
		if (XenForo_Application::getOptions()->sonnbXG_disableOriginal || empty($data['content_type']))
		{
			return null;
		}

		if ($forceLocal === false && !empty($data['bdattachmentstore_engine']))
		{
			$fileModel = $this->_bdAttachmentStore_getFileModel();
			$engine = $data['bdattachmentstore_engine'];
			$engineOptions = @unserialize($data['bdattachmentstore_options']);

			$filePath = $this->getStoreContentDataFile($data);
			$file = $fileModel->getFileUrl($engine, $engineOptions, $filePath);

			if ($file)
			{
				return $file;
			}
		}

		if ($externalDataPath === null)
		{
			$externalDataPath = XenForo_Application::$externalDataUrl;
		}

		return sprintf('%s/'.$data['content_type'].'s/o/%d/%d-%d-%s.%s',
			$externalDataPath,
			floor($data['content_data_id'] / 1000),
			$data['content_data_id'],
			$data['upload_date'],
			md5('o'.$data['file_hash']),
			$data['extension']
		);
	}
	
	public function getContentDataSmallThumbnailUrl(array $data, $forceLocal = false, $externalDataPath = null)
	{
		if (empty($data['content_type']))
		{
			return null;
		}
		if ($forceLocal === false && !empty($data['bdattachmentstore_engine']))
		{
			$fileModel = $this->_bdAttachmentStore_getFileModel();
			$engine = $data['bdattachmentstore_engine'];
			$engineOptions = @unserialize($data['bdattachmentstore_options']);

			$filePath = $this->getStoreContentDataSmallThumbnailFile($data);
			$file = $fileModel->getFileUrl($engine, $engineOptions, $filePath);

			if ($file)
			{
				return $file;
			}
		}

		if (!empty($data['content_type']) && $data['content_type'] === sonnb_XenGallery_Model_Video::$contentType)
		{
			$data['extension'] = self::$videoEmbedExtension;
		}

		if ($externalDataPath === null)
		{
			$externalDataPath = XenForo_Application::$externalDataUrl;
		}

		return sprintf('%s/'.$data['content_type'].'s/s/%d/%d-%d-%s.%s',
			$externalDataPath,
			floor($data['content_data_id'] / 1000),
			$data['content_data_id'],
			$data['upload_date'],
			md5('s'.$data['file_hash']),
			$data['extension']
		);
	}
	
	public function getContentDataMediumThumbnailUrl(array $data, $forceLocal = false, $externalDataPath = null)
	{
		if (empty($data['content_type']))
		{
			return null;
		}
		if ($forceLocal === false && !empty($data['bdattachmentstore_engine']))
		{
			$fileModel = $this->_bdAttachmentStore_getFileModel();
			$engine = $data['bdattachmentstore_engine'];
			$engineOptions = @unserialize($data['bdattachmentstore_options']);

			$filePath = $this->getStoreContentDataMediumThumbnailFile($data);
			$file = $fileModel->getFileUrl($engine, $engineOptions, $filePath);

			if ($file)
			{
				return $file;
			}
		}

		if (!empty($data['content_type']) && $data['content_type'] === sonnb_XenGallery_Model_Video::$contentType)
		{
			$data['extension'] = self::$videoEmbedExtension;
		}

		if ($externalDataPath === null)
		{
			$externalDataPath = XenForo_Application::$externalDataUrl;
		}

		return sprintf('%s/'.$data['content_type'].'s/m/%d/%d-%d-%s.%s',
			$externalDataPath,
			floor($data['content_data_id'] / 1000),
			$data['content_data_id'],
			$data['upload_date'],
			md5('m'.$data['file_hash']),
			$data['extension']
		);
	}
	
	public function getContentDataLargeThumbnailUrl(array $data, $forceLocal = false, $externalDataPath = null)
	{
		if (empty($data['content_type']))
		{
			return null;
		}

		if ($forceLocal === false && !empty($data['bdattachmentstore_engine']))
		{
			$fileModel = $this->_bdAttachmentStore_getFileModel();
			$engine = $data['bdattachmentstore_engine'];
			$engineOptions = @unserialize($data['bdattachmentstore_options']);

			$filePath = $this->getStoreContentDataLargeThumbnailFile($data);
			$file = $fileModel->getFileUrl($engine, $engineOptions, $filePath);

			if ($file)
			{
				return $file;
			}
		}

		if (!empty($data['content_type']) && $data['content_type'] === sonnb_XenGallery_Model_Video::$contentType)
		{
			$data['extension'] = self::$videoEmbedExtension;
		}

		if ($externalDataPath === null)
		{
			$externalDataPath = XenForo_Application::$externalDataUrl;
		}

		return sprintf('%s/'.$data['content_type'].'s/l/%d/%d-%d-%s.%s',
			$externalDataPath,
			floor($data['content_data_id'] / 1000),
			$data['content_data_id'],
			$data['upload_date'],
			md5('l'.$data['file_hash']),
			$data['extension']
		);
	}

	public function getStoreContentDataFile(array $data)
	{
		return sprintf(
			'%s/xengallery_'.$data['content_type'].'s_o_%d_%s.%s',
			date('Y/m', $data['upload_date']),
			$data['content_data_id'],
			md5('o'.$data['file_hash']),
			$data['extension']
		);
	}

	public function getStoreContentDataSmallThumbnailFile(array $data)
	{
		if (!empty($data['content_type']) && $data['content_type'] === sonnb_XenGallery_Model_Video::$contentType)
		{
			$data['extension'] = self::$videoEmbedExtension;
		}

		return sprintf(
			'%s/xengallery_'.$data['content_type'].'s_s_%d_%s.%s',
			date('Y/m', $data['upload_date']),
			$data['content_data_id'],
			md5('s'.$data['file_hash']),
			$data['extension']
		);
	}

	public function getStoreContentDataMediumThumbnailFile(array $data)
	{
		if (!empty($data['content_type']) && $data['content_type'] === sonnb_XenGallery_Model_Video::$contentType)
		{
			$data['extension'] = self::$videoEmbedExtension;
		}

		return sprintf(
			'%s/xengallery_'.$data['content_type'].'s_m_%d_%s.%s',
			date('Y/m', $data['upload_date']),
			$data['content_data_id'],
			md5('m'.$data['file_hash']),
			$data['extension']
		);
	}

	public function getStoreContentDataLargeThumbnailFile(array $data)
	{
		if (!empty($data['content_type']) && $data['content_type'] === sonnb_XenGallery_Model_Video::$contentType)
		{
			$data['extension'] = self::$videoEmbedExtension;
		}

		return sprintf(
			'%s/xengallery_'.$data['content_type'].'s_l_%d_%s.%s',
			date('Y/m', $data['upload_date']),
			$data['content_data_id'],
			md5('l'.$data['file_hash']),
			$data['extension']
		);
	}
	
	public function deleteUnusedContentData($cutoff)
	{
		$conditions = array(
			'unassociated' => 1,
			'upload_date' => array('<', $cutoff)
		);
		
		$unusedPhotoData = $this->getData($conditions);
		
		if ($unusedPhotoData)
		{
			foreach ($unusedPhotoData as $dataId => $data)
			{
				$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_ContentData');
				$dw->setExistingData($data, true);
				$dw->delete();
			}
		}
	}
	
	public function prepareDataFetchOptions(array $fetchOptions)
	{
        $selectFields = '';
        $joinTables = '';
        $orderBy = '';

        if (!empty($fetchOptions['order']))
        {
            $orderBySecondary = '';

            switch ($fetchOptions['order'])
            {
                case 'content_data_id':
                case 'temp_hash':
                case 'unassociated':
                case 'file_size':
                case 'file_hash':
	            case 'extension':
	            case 'duration':
                    $orderBy = 'content_data.' . $fetchOptions['order'];
                    $orderBySecondary = ', content_data.upload_date DESC';
                    break;
                case 'tag_date':
                default:
                    $orderBy = 'content_data.upload_date';
            }
            if (!isset($fetchOptions['orderDirection']) || $fetchOptions['orderDirection'] === 'desc')
            {
                $orderBy .= ' DESC';
            }
            else
            {
                $orderBy .= ' ASC';
            }

            $orderBy .= $orderBySecondary;
        }
        
        return array(
        		'selectFields' => $selectFields,
        		'joinTables' => $joinTables,
        		'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
        );
	}
	
	public function prepareDataConditions(array $conditions, array &$fetchOptions)
	{
        $sqlConditions = array();
        $db = $this->_getDb();
        
        if (!empty($conditions['content_data_id']))
        {
        	if (is_array($conditions['content_data_id']))
        	{
        		$sqlConditions[] = 'content_data.content_data_id IN (' . $db->quote($conditions['content_data_id']) . ')';
        	}
        	else
        	{
        		$sqlConditions[] = 'content_data.content_data_id = ' . $db->quote($conditions['content_data_id']);
        	}
        }

		if (!empty($conditions['content_type']))
		{
			if (is_array($conditions['content_type']))
			{
				$sqlConditions[] = 'content_data.content_type IN (' . $db->quote($conditions['content_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'content_data.content_type = ' . $db->quote($conditions['content_type']);
			}
		}

        if (!empty($conditions['temp_hash']))
        {
            if (is_array($conditions['temp_hash']))
            {
                $sqlConditions[] = 'content_data.temp_hash IN (' . $db->quote($conditions['temp_hash']) . ')';
            }
            else
            {
                $sqlConditions[] = 'content_data.temp_hash = ' . $db->quote($conditions['temp_hash']);
            }
        }
        
        if (!empty($conditions['file_hash']))
        {
            if (is_array($conditions['file_hash']))
            {
                $sqlConditions[] = 'content_data.file_hash IN (' . $db->quote($conditions['file_hash']) . ')';
            }
            else
            {
                $sqlConditions[] = 'content_data.file_hash = ' . $db->quote($conditions['file_hash']);
            }
        }

		if (!empty($conditions['extension']))
		{
			if (is_array($conditions['extension']))
			{
				$sqlConditions[] = 'content_data.extension IN (' . $db->quote($conditions['extension']) . ')';
			}
			else
			{
				$sqlConditions[] = 'content_data.extension = ' . $db->quote($conditions['extension']);
			}
		}

		if (isset($conditions['unassociated']))
		{
			$sqlConditions[] = 'content_data.unassociated = 1';
		}

		if (isset($conditions['is_animated']))
		{
			$sqlConditions[] = 'content_data.is_animated = 1';
		}

		if (!empty($conditions['duration']) && is_array($conditions['duration']))
		{
			list($operator, $cutOff) = $conditions['duration'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "content_data.duration $operator " . $db->quote($cutOff);
		}

        if (!empty($conditions['width']) && is_array($conditions['width']))
        {
            list($operator, $cutOff) = $conditions['width'];

            $this->assertValidCutOffOperator($operator);
            $sqlConditions[] = "content_data.width $operator " . $db->quote($cutOff);
        }

        if (!empty($conditions['height']) && is_array($conditions['height']))
        {
            list($operator, $cutOff) = $conditions['height'];

            $this->assertValidCutOffOperator($operator);
            $sqlConditions[] = "content_data.height $operator " . $db->quote($cutOff);
        }

        if (!empty($conditions['upload_date']) && is_array($conditions['upload_date']))
        {
            list($operator, $cutOff) = $conditions['upload_date'];

            $this->assertValidCutOffOperator($operator);
            $sqlConditions[] = "content_data.upload_date $operator " . $db->quote($cutOff);
        }
        
        return $this->getConditionsForClause($sqlConditions);
	}

    public function createContentDataThumbnailFile($inputFile, $outputFile, $outputType, $thumbnailType, &$fallback = false, &$dimensions = array(), $isRebuild = false)
    {
        $success = false;

        $directory = dirname($outputFile);
        XenForo_Helper_File::createDirectory($directory, true);

	    //TODO: Refactor the fallback.
        $image = XenForo_Image_Abstract::createFromFile($inputFile, $outputType);
        if ($image)
        {
            switch ($thumbnailType)
            {
                case self::CONTENT_FILE_TYPE_LARGE:
	                $largeSize = $this->getThumbnailSize(self::CONTENT_FILE_TYPE_LARGE);
                    if ($image->thumbnail($largeSize, $largeSize))
                    {
                        $success = $image->output($outputType, $outputFile, 100);
                    }
                    else
                    {
                        $success = $this->copyFile($inputFile, $outputFile);
                    }

	                $dimensions[$thumbnailType .'_width'] = $image->getWidth();
	                $dimensions[$thumbnailType .'_height'] = $image->getHeight();

		            //Add watermark if enabled.
	                $this->addWatermark($outputFile, $outputType, null, $isRebuild);
                    break;
                case self::CONTENT_FILE_TYPE_MEDIUM:
                    $realWidth = $image->getWidth();
                    $realHeight = $image->getHeight();

                    $resizeWidth = $this->getThumbnailSize(self::CONTENT_FILE_TYPE_MEDIUM);
                    $resizeHeight = $resizeWidth;

                    if ($realWidth*($resizeHeight/$realHeight) < $resizeWidth)
                    {
                        $resizeHeight = $realHeight*($resizeWidth/$realWidth);
                    }

                    if ($image->thumbnail($resizeWidth, $resizeHeight))
                    {
                        $success = $image->output($outputType, $outputFile, 100);
                    }
                    else
                    {
                        $success = $this->copyFile($inputFile, $outputFile);
                    }

	                $dimensions[$thumbnailType .'_width'] = $image->getWidth();
	                $dimensions[$thumbnailType .'_height'] = $image->getHeight();
                    break;
                case self::CONTENT_FILE_TYPE_SMALL:
	                $smallSize = $this->getThumbnailSize(self::CONTENT_FILE_TYPE_SMALL);
                    if ($image->thumbnail($smallSize*2, $smallSize*2))
                    {
                        $x = floor(($image->getWidth() - $smallSize) /2);
                        $y = floor(($image->getHeight() - $smallSize) /2);
                        $image->crop(
                            $x > 0 ? $x : 0, $y > 0 ? $y : 0,
	                        $smallSize, $smallSize
                        );

                        $success = $image->output($outputType, $outputFile, 100);
                    }
                    else
                    {
                        $success = $this->copyFile($inputFile, $outputFile);
                    }

	                $dimensions[$thumbnailType .'_width'] = $image->getWidth();
	                $dimensions[$thumbnailType .'_height'] = $image->getHeight();
                    break;
                case self::CONTENT_FILE_TYPE_ORIGINAL:
                default:
                    $success = $this->copyFile($inputFile, $outputFile);
                    break;
            }
        }
	    else
	    {
		    $fallback = true;
		    $success = $this->copyFile($inputFile, $outputFile);
	    }

        unset($image);

        return $success;
    }

    public function getImageFileType($image, $fileType = null)
    {
        if ($fileType !== null)
        {
            return $fileType;
        }

        try
        {
            $imageInfo = getimagesize($image);

            return $imageInfo['type'];
        }
        catch(Exception $e)
        {

            return null;
        }
    }

    public function copyFile($source, $destination)
    {
        try
        {
//            if (is_uploaded_file($source))
//            {
//                $success = move_uploaded_file($source, $destination);
//            }
//            else
//            {
                $success = copy($source, $destination);
//            }
        }
        catch (Exception $e)
        {
            $success = false;
        }

        if ($success)
        {
            XenForo_Helper_File::makeWritableByFtpUser($destination);
        }

        return $success;
    }

    public function canUseAttachmentStore($contentData = null)
    {
        try
        {
            $addOns = XenForo_Application::get('addOns');
            $attachmentStoreActive = class_exists('bdAttachmentStore_Model_File') && !empty($addOns['bdAttachmentStore']);

            if ($contentData === null)
            {
                return $attachmentStoreActive;
            }
            else
            {
                if ($attachmentStoreActive && !empty($contentData['bdattachmentstore_engine']))
                {
                    $engine = $contentData['bdattachmentstore_engine'];
                    $engineOptions = @unserialize($contentData['bdattachmentstore_options']);

                    return !empty($engine) && !empty($engineOptions);
                }
            }
        }
        catch(Exception $e)
        {
            return false;
        }

        return false;
    }

    public function deleteContentDataFile(array $contentData, $thumbnailType)
    {
        $isOnCloud = $this->canUseAttachmentStore($contentData);
        $isUsingLocal = !empty($contentData['bdattachmentstore_options']['keepLocalCopy']);

        $filePath = false;
        $filePathStore = false;
        switch($thumbnailType)
        {
            case self::CONTENT_FILE_TYPE_SMALL:
                if ($isOnCloud)
                {
                    $filePathStore = $this->getStoreContentDataSmallThumbnailFile($contentData);
                }

                if (!$isOnCloud || ($isOnCloud && $isUsingLocal))
                {
                    $filePath = $this->getContentDataSmallThumbnailFile($contentData);
                }
                break;
            case self::CONTENT_FILE_TYPE_MEDIUM:
                if ($isOnCloud)
                {
                    $filePathStore = $this->getStoreContentDataMediumThumbnailFile($contentData);
                }

                if (!$isOnCloud || ($isOnCloud && $isUsingLocal))
                {
                    $filePath = $this->getContentDataMediumThumbnailFile($contentData);
                }
                break;
            case self::CONTENT_FILE_TYPE_LARGE:
                if ($isOnCloud)
                {
                    $filePathStore = $this->getStoreContentDataLargeThumbnailFile($contentData);
                }

                if (!$isOnCloud || ($isOnCloud && $isUsingLocal))
                {
                    $filePath = $this->getContentDataLargeThumbnailFile($contentData);
                }
                break;
            case self::CONTENT_FILE_TYPE_ORIGINAL:
                if ($isOnCloud)
                {
                    $filePathStore = $this->getStoreContentDataFile($contentData);
                }

                if (!$isOnCloud || ($isOnCloud && $isUsingLocal))
                {
                    $filePath = $this->getContentDataFile($contentData);
                }
                break;
            default:
                $this->deleteContentDataFile($contentData, self::CONTENT_FILE_TYPE_SMALL);
                $this->deleteContentDataFile($contentData, self::CONTENT_FILE_TYPE_MEDIUM);
                $this->deleteContentDataFile($contentData, self::CONTENT_FILE_TYPE_LARGE);
                $this->deleteContentDataFile($contentData, self::CONTENT_FILE_TYPE_ORIGINAL);
        }

        if ($filePathStore !== false)
        {
            $this->deleteContentDataFileByPath($filePathStore, true, $contentData);
        }

        if ($filePath !== false)
        {
            $this->deleteContentDataFileByPath($filePath);
        }
    }

    public function deleteContentDataFileByPath($filePath, $isOnCloud = false, $contentData = null)
    {
        if ($isOnCloud === true)
        {
            $this->_bdAttachmentStore_getFileModel()->deleteFile($contentData['bdattachmentstore_engine'], $contentData['bdattachmentstore_options'], $filePath);
        }
        else
        {
            @unlink($filePath);
        }
    }

	public function addWatermark($inputImage, $fileType = null, $viewingUser = null, $isRebuild = false)
	{
		$this->standardizeViewingUserReference($viewingUser);
		$fileType = $this->getImageFileType($inputImage, $fileType);
		if ($fileType === null)
		{
			return false;
		}

		$xenOptions = XenForo_Application::getOptions();
		if (empty($xenOptions->sonnbXG_watermark['enabled']))
		{
			return false;
		}

		$isImagick = $xenOptions->imageLibrary['class'] === 'imPecl';
		$watermarkOptions = $this->getWatermarkSettings($isRebuild);

		try
		{
			switch($watermarkOptions['overlay'])
			{
				case 'image':
					$watermarkFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
					if (Zend_Uri::check($watermarkOptions['url']))
					{
						$client = XenForo_Helper_Http::getClient($watermarkOptions['url']);
						$response = $client->request('GET');

						if ($response->isSuccessful())
						{
							@file_put_contents($watermarkFile, $response->getBody());
						}
						else
						{
							return false;
						}
					}
                    //TODO: double-check the relative path
					elseif (is_file($watermarkOptions['url']))
					{
						$this->copyFile($watermarkOptions['url'], $watermarkFile);
					}
					else
					{
						return false;
					}

					if (!($watermarkFileInfo = @getimagesize($watermarkFile)))
					{
						return false;
					}

					if ($isImagick)
					{
						$srcResource = new Imagick($inputImage);
						$wtmResource = new Imagick($watermarkFile);
					}
					else
					{
						$srcResource = $this->createImageFromFile($inputImage, $fileType);
						//TODO: Check watermark image size against input image.
						$wtmResource = $this->createImageFromFile($watermarkFile, $watermarkFileInfo[2]);
					}

					$this->addWatermarkBySource($srcResource, $wtmResource, $watermarkOptions['position'], $watermarkOptions['margin'], $inputImage, $fileType);
					@unlink($watermarkFile);
					break;
				case 'text':
				default:
					$findArray = array('{username}', '{user_id}', '{email}');
					$replaceArray = array($viewingUser['username'], $viewingUser['user_id'], $viewingUser['email']);
					$watermarkOptions['text'] = str_replace($findArray, $replaceArray, $watermarkOptions['text']);

					if (empty($watermarkOptions['text']))
					{
						return false;
					}

					if ($isImagick)
					{
						$wtmResource = new Imagick();
						$draw = new ImagickDraw();
						$color = new ImagickPixel($watermarkOptions['textColor']);
						$background = new ImagickPixel('none');

						$draw->setFontSize($watermarkOptions['textSize']);
						$draw->setFillColor($color);
						$draw->setStrokeAntialias(true);
						$draw->setTextAntialias(true);

						$metrics = $wtmResource->queryFontMetrics($draw, $watermarkOptions['text']);
						$draw->annotation(0, $metrics['ascender'], $watermarkOptions['text']);
						$wtmResource->newImage($metrics['textWidth'], $metrics['textHeight'], $background);
						$wtmResource->setImageFormat('png');
						$wtmResource->drawImage($draw);

						$srcResource = new Imagick($inputImage);
					}
					else
					{
						$padding = 10;
						$font = 'styles/sonnb/XenGallery/watermark.ttf';
						if (!empty($watermarkOptions['font']) && is_file($watermarkOptions['font']))
						{
							$font = $watermarkOptions['font'];
						}

                        if (function_exists('imagettfbbox'))
                        {
                            $textDimension = imagettfbbox($watermarkOptions['textSize'], 0, $font, $watermarkOptions['text']);
                            $width = abs($textDimension[4] - $textDimension[0]) + $padding;
                            $height = abs($textDimension[5] - $textDimension[1]) + $padding;
                        }
                        else
                        {
                            $width = ImageFontWidth($watermarkOptions['textSize']) * strlen($watermarkOptions['text']);
                            $height = ImageFontHeight($watermarkOptions['textSize']);
                        }

						$wtmResource = @imagecreatetruecolor ($width,$height);

						if (strtolower($watermarkOptions['bgColor']) === 'transparent')
						{
							imagesavealpha($wtmResource, true);
							$bgColor = imagecolorallocatealpha($wtmResource, 0, 0, 0, 127);
							imagefill($wtmResource, 0, 0, $bgColor);
						}
						else
						{
							$bgColorRbg = $this->hex2rgb($watermarkOptions['bgColor']);
							$bgColor = imagecolorallocate($wtmResource, $bgColorRbg['red'], $bgColorRbg['green'], $bgColorRbg['blue']);
							imagefill($wtmResource, 0, 0, $bgColor);
						}

						$txtColorRbg = $this->hex2rgb($watermarkOptions['textColor']);
						$txtColor = imagecolorallocate ($wtmResource, $txtColorRbg['red'], $txtColorRbg['green'], $txtColorRbg['blue']);
						imagettftext($wtmResource, $watermarkOptions['textSize'], 0, $padding/2, $height - $padding/2, $txtColor, $font, $watermarkOptions['text']);
						$srcResource = $this->createImageFromFile($inputImage, $fileType);
					}

					$this->addWatermarkBySource($srcResource, $wtmResource, $watermarkOptions['position'], $watermarkOptions['margin'], $inputImage, $fileType);
					break;
			}
		}
		catch (Exception $e)
		{
			XenForo_Error::logException($e);
			return false;
		}
	}

	public function addWatermarkBySource($srcImage, $wtmImage, $position, $margin, $outputFile, $outputType)
	{
		$isImagick = XenForo_Application::getOptions()->imageLibrary['class'] === 'imPecl';
		if ($isImagick)
		{
			$sWidth = $srcImage->getImageWidth();
			$sHeight = $srcImage->getImageHeight();
			$wWidth = $wtmImage->getImageWidth();
			$wHeight = $wtmImage->getImageHeight();
		}
		else
		{
			$wWidth = imagesx($wtmImage);
			$wHeight = imagesy($wtmImage);
			$sWidth = imagesx($srcImage);
			$sHeight = imagesy($srcImage);
		}

		$frameX = $frameY = $margin;
		switch($position)
		{
			case 'tr':
				$frameX = $sWidth - $wWidth - $margin;
				break;
			case 'bl':
				$frameY = $sHeight - $wHeight - $margin;
				break;
			case 'br':
				$frameX = $sWidth - $wWidth - $margin;
				$frameY = $sHeight - $wHeight - $margin;
				break;
			case 'c':
				$frameX = ($sWidth - $wWidth)/2;
				$frameY = ($sHeight - $wHeight)/2;
				break;
			case 'ct':
				$frameY = ($sHeight - $wHeight)/2;
				break;
			case 'cb':
				$frameX = ($sWidth - $wWidth)/2;
				$frameY = $sHeight - $wHeight - $margin;
				break;
			case 'tl':
			default:
				break;
		}

		if ($isImagick)
		{
			$srcImage->compositeImage($wtmImage, imagick::COMPOSITE_OVER, $frameX, $frameY);
			$srcImage->setimageformat(self::$extensionMap[$outputType]);
			$srcImage->writeImages($outputFile, true);
			$srcImage->destroy();
			$wtmImage->destroy();
		}
		else
		{
			imagecopy($srcImage, $wtmImage, $frameX, $frameY, 0, 0, $wWidth, $wHeight);
			$this->outputImage($srcImage, $outputType, $outputFile);
			imagedestroy($srcImage);
			imagedestroy($wtmImage);
		}
	}

	public function getWatermarkSettings($isRebuild = false)
	{
		if ($isRebuild !== false && $this->_getGalleryModel()->canCustomizeWatermark())
		{
			$watermarkOptions = $this->getWatermarkSettingsForUser();
		}
		else
		{
			$watermarkOptions = $this->getWatermarkSettingsDefault();
		}

		return $watermarkOptions;
	}

	public function getWatermarkSettingsDefault()
	{
		$xenOptions = XenForo_Application::getOptions();
		$watermark = $xenOptions->sonnbXG_watermark;

		return array(
			'overlay' => isset($watermark['overlay']) ? $watermark['overlay'] : 'text',
			'url' => isset($watermark['url']) ? $watermark['url'] : '',
			'text' => isset($watermark['text']) ? $watermark['text'] : '',
			'textSize' => isset($watermark['textSize']) ? $watermark['textSize'] : 5,
			'textColor' => isset($watermark['textColor']) ? $watermark['textColor'] : '#000',
			'bgColor' => isset($watermark['bgColor']) ? $watermark['bgColor'] : 'transparent',
			'font' => isset($watermark['font']) ? $watermark['font'] : '',
			'position' => isset($watermark['position']) ? $watermark['position'] : 'tl',
			'margin' => isset($watermark['margin']) ? $watermark['margin'] : 10
		);
	}

	public function getWatermarkSettingsForUser(array $viewingUser = null)
	{
		$xenOptions = XenForo_Application::getOptions();
		$watermark = $xenOptions->sonnbXG_watermark;
		$this->standardizeViewingUserReference($viewingUser);

		if (!empty($viewingUser['sonnb_xengallery_watermark']))
		{
			return array(
				'overlay' => isset($watermark['overlay']) ? $watermark['overlay'] : 'text',
				'url' => isset($watermark['url']) ? $watermark['url'] : '',
				'text' => $viewingUser['sonnb_xengallery_watermark']['text'],
				'textSize' => $viewingUser['sonnb_xengallery_watermark']['textSize'],
				'textColor' => $viewingUser['sonnb_xengallery_watermark']['textColor'],
				'bgColor' => $viewingUser['sonnb_xengallery_watermark']['bgColor'],
				'font' => isset($watermark['font']) ? $watermark['font'] : '',
				'position' => $viewingUser['sonnb_xengallery_watermark']['position'],
				'margin' => $viewingUser['sonnb_xengallery_watermark']['margin']
			);
		}
		else
		{
			return $this->getWatermarkSettingsDefault();
		}
	}

	public function hex2rgb($color)
	{
		if (strtolower($color) === 'transparent')
		{
			return $color;
		}

        //PHP 5.2.x compatible
        $color = str_split($color);
		if ($color[0] == '#')
		{
			$color = array_slice($color, 1);
		}

		if (count($color) == 6)
		{
			list($r, $g, $b) = array($color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5]);
		}
		elseif (count($color) == 3)
		{
			list($r, $g, $b) = array($color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]);
		}
		else
		{
			return array(
				'red' => '0',
				'green' => '0',
				'blue' => '0'
			);
		}

		return array(
			'red' => hexdec($r),
			'green' => hexdec($g),
			'blue' => hexdec($b)
		);
	}

	public function createImageFromFile($fileName, $type)
	{
		$image = false;

		switch ($type)
		{
			case IMAGETYPE_GIF:
				if (function_exists('imagecreatefromgif'))
				{
					$image = imagecreatefromgif($fileName);
				}
				break;
			case IMAGETYPE_JPEG:
				if (function_exists('imagecreatefromjpeg'))
				{
					$image = imagecreatefromjpeg($fileName);
				}
				break;
			case IMAGETYPE_PNG:
				if (function_exists('imagecreatefrompng'))
				{
					$image = imagecreatefrompng($fileName);
					imagealphablending($image, false);
					imagesavealpha($image, true);
				}
				break;
		}

		return $image;
	}

	public function outputImage($image, $outputType, $outputFile)
	{
		$success = false;

		switch ($outputType)
		{
			case IMAGETYPE_GIF:
				$success = imagegif($image, $outputFile);
				break;
			case IMAGETYPE_JPEG:
				$success = imagejpeg($image, $outputFile, 100);
				break;
			case IMAGETYPE_PNG:
				imagealphablending($image, false);
				imagesavealpha($image, true);
				$success = imagepng($image, $outputFile, 9, PNG_ALL_FILTERS);
				break;
		}

		return $success;
	}

    /**
     * @return bdAttachmentStore_Model_File
     */
    protected function _bdAttachmentStore_getFileModel()
    {
        return $this->getModelFromCache('bdAttachmentStore_Model_File');
    }
}
