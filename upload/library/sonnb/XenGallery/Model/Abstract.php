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
class sonnb_XenGallery_Model_Abstract extends XenForo_Model
{

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

	protected function _formatNumberCount($count, $precision = 1)
	{
		$units = array(
			'', //Less than thousand
			'K', //Thousands
			'M', //Millions
			'B', //Billions
			'T' //Trillions
		);

		$index = 0;
		while ($count > 1000)
		{
			$count = $count/1000;
			$index++;
		}

		return round($count, $precision) . $units[$index];
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
	 * @return sonnb_XenGallery_Model_History
	 */
	protected function _getHistoryModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_History');
	}

	/**
	 * @return bdAttachmentStore_Model_File
	 */
	protected function _bdAttachmentStore_getFileModel()
	{
		return $this->getModelFromCache('bdAttachmentStore_Model_File');
	}

	/**
	 * @return sonnb_XenGallery_Model_Field
	 */
	protected function _getFieldModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Field');
	}
}
