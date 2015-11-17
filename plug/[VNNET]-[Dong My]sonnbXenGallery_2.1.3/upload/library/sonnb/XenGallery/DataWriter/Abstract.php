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
abstract class sonnb_XenGallery_DataWriter_Abstract extends XenForo_DataWriter
{
	protected $_taggedUsers = array();

	protected function _rebuildContentIndex()
	{
		switch ($this->get('content_type'))
		{
			case sonnb_XenGallery_Model_Video::$contentType:
				$handleKey = 'sonnb_XenGallery_Search_DataHandler_Video';
				break;
			case sonnb_XenGallery_Model_Photo::$contentType:
				$handleKey = 'sonnb_XenGallery_Search_DataHandler_Photo';
				break;
			default:
				$handleKey = '';
				break;
		}

		if (empty($handleKey))
		{
			return;
		}

		$indexer = new XenForo_Search_Indexer();
		$dataHandler = XenForo_Search_DataHandler_Abstract::create($handleKey);
		$dataHandler->insertIntoIndex($indexer, $this->getMergedData());
	}

	/**
	 * @param $contentType
	 * @return string
	 */
	protected function _getXfContentType($contentType)
	{
		$xfContentType = '';

		switch ($contentType)
		{
			case sonnb_XenGallery_Model_Album::$contentType:
				$xfContentType = sonnb_XenGallery_Model_Album::$xfContentType;
				break;
			case sonnb_XenGallery_Model_Photo::$contentType:
				$xfContentType = sonnb_XenGallery_Model_Photo::$xfContentType;
				break;
			case sonnb_XenGallery_Model_Video::$contentType:
				$xfContentType = sonnb_XenGallery_Model_Video::$xfContentType;
				break;
			case sonnb_XenGallery_Model_Comment::$contentType:
				$xfContentType = sonnb_XenGallery_Model_Comment::$xfContentType;
				break;
		}

		return $xfContentType;
	}

	/**
	 * @param null $contentType
	 * @param int $state
	 * @return XenForo_DataWriter
	 */
	protected function _getXfContentDw($contentType = null, $state = self::ERROR_ARRAY)
	{
		if ($contentType === null)
		{
			$contentType = $this->get('content_type');
		}

		switch ($contentType)
		{
			case sonnb_XenGallery_Model_Album::$contentType:
				$class = 'sonnb_XenGallery_DataWriter_Album';
				break;
			case sonnb_XenGallery_Model_Photo::$contentType:
				$class = 'sonnb_XenGallery_DataWriter_Photo';
				break;
			case sonnb_XenGallery_Model_Video::$contentType:
				$class = 'sonnb_XenGallery_DataWriter_Video';
				break;
			case sonnb_XenGallery_Model_Comment::$contentType:
				$class = 'sonnb_XenGallery_DataWriter_Comment';
				break;
		}

		return self::create($class, $state);
	}

	/**
	 * @return string
	 * @throws XenForo_Exception
	 */
	protected function _getContentDwClass()
	{
		$dwClass = null;
		switch($this->get('content_type'))
		{
			case sonnb_XenGallery_Model_Photo::$contentType:
				$dwClass = "sonnb_XenGallery_DataWriter_Photo";
				break;
			case sonnb_XenGallery_Model_Video::$contentType:
				$dwClass = "sonnb_XenGallery_DataWriter_Video";
				break;
			case sonnb_XenGallery_Model_Comment::$contentType:
				$dwClass = "sonnb_XenGallery_DataWriter_Comment";
				break;
			case sonnb_XenGallery_Model_Album::$contentType:
				$dwClass = "sonnb_XenGallery_DataWriter_Album";
				break;
			default:
				throw new XenForo_Exception('Invalid content type.');
				break;
		}

		return $dwClass;
	}

	/**
	 * @param $contentType
	 * @param $action
	 * @return string
	 */
	protected function _getDefaultPrivacy($contentType, $action)
	{
		return $this->_getContentModel()->getDefaultPrivacy($contentType, $action);
	}

	protected final function _logIp($contentType, $contentId, $action)
	{
		/* @var $logModel XenForo_Model_Ip */
		$logModel = $this->getModelFromCache('XenForo_Model_Ip');
		$logModel->logIp(
			$this->get('user_id'),
			$contentType,
			$contentId,
			$action
		);
	}

	/**
	 * @param $source
	 * @param $destination
	 * @return bool
	 */
	protected function _moveFile($source, $destination)
	{
		try
		{
			if (is_uploaded_file($source))
			{
				$success = move_uploaded_file($source, $destination);
			}
			else
			{
				$success = rename($source, $destination);
			}
		}
		catch (Exception $e)
		{
			$success = false;
		}

		if (!$success)
		{
			$success = copy($source, $destination);
			if ($success)
			{
				@unlink($source);
			}
		}

		if ($success)
		{
			XenForo_Helper_File::makeWritableByFtpUser($destination);
		}

		return $success;
	}

	/**
	 * @return sonnb_XenGallery_Model_Gallery
	 */
	protected function _getGalleryModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Gallery');
	}

	/**
	 * @return sonnb_XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Album');
	}

	/**
	 * @return sonnb_XenGallery_Model_Content
	 */
	protected function _getContentModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Content');
	}

	/**
	 * @return sonnb_XenGallery_Model_ContentData
	 */
	protected function _getContentDataModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_ContentData');
	}
	
	/**
	 * @return sonnb_XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Comment');
	}
	
	/**
	 * @return sonnb_XenGallery_Model_Photo
	 */
	protected function _getPhotoModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Photo');
	}

	/**
	 * @return sonnb_XenGallery_Model_Video
	 */
	protected function _getVideoModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Video');
	}

	/**
	 * @return sonnb_XenGallery_Model_VideoData
	 */
	protected function _getVideoDataModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_VideoData');
	}

	/**
	 * @return sonnb_XenGallery_Model_Category
	 */
	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Category');
	}
	
	/**
	 * @return sonnb_XenGallery_Model_Tag
	 */
	protected function _getTagModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Tag');
	}
	
	/**
	 * @return sonnb_XenGallery_Model_PhotoData
	 */
	protected function _getPhotoDataModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_PhotoData');
	}
	
	/**
	 * @return sonnb_XenGallery_Model_Watch
	 */
	protected function _getWatchModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Watch');
	}
	
	/**
	 * @return sonnb_XenGallery_Model_Location
	 */
	protected function _getLocationModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Location');
	}

	/**
	 * @return sonnb_XenGallery_Model_Collection
	 */
	protected function _getCollectionModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Collection');
	}

	/**
	 * @return sonnb_XenGallery_Model_Stream
	 */
	protected function _getStreamModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Stream');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * @return XenForo_Model_ModerationQueue
	 */
	protected function _getModerationQueueModel()
	{
		return $this->getModelFromCache('XenForo_Model_ModerationQueue');
	}

	/**
	 * @return bdAttachmentStore_Model_File
	 */
	protected function _bdAttachmentStore_getFileModel()
	{
		return $this->getModelFromCache('bdAttachmentStore_Model_File');
	}

	/**
	 * @return sonnb_XenGallery_Model_History
	 */
	protected function _getHistoryModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_History');
	}

	/**
	 * @return sonnb_XenGallery_Model_MyPlaylist
	 */
	protected function _getMyPlaylistModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_MyPlaylist');
	}

	/**
	 * @return XenForo_Model_ModerationQueue
	 */
	protected function _getModerationQueue()
	{
		return $this->getModelFromCache('XenForo_Model_ModerationQueue');
	}

	/**
	 * @return sonnb_XenGallery_Model_Field
	 */
	protected function _getFieldModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Field');
	}

	/**
	 * @return sonnb_XenGallery_Model_UserTagging
	 */
	protected function _getUserTaggingModel()
	{
        if (XenForo_Application::$versionId >= 1020000)
        {
		    return $this->getModelFromCache('sonnb_XenGallery_Model_UserTagging');
        }
        else
        {
            return $this->getModelFromCache('sonnb_XenGallery_Model_UserTaggingOld');
        }
	}
}