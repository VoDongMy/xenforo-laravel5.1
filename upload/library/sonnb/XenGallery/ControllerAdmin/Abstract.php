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
class sonnb_XenGallery_ControllerAdmin_Abstract extends XenForo_ControllerAdmin_Abstract
{
	/**
	 * @param $action
	 */
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('xengalleryManager');
	}

	/**
	 * @return sonnb_XenGallery_Model_Field
	 */
	protected function _getFieldModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Field');
	}

	/**
	 * @return sonnb_XenGallery_Model_Category
	 */
	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Category');
	}

	/**
	 * @return sonnb_XenGallery_Model_Collection
	 */
	protected function _getCollectionModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Collection');
	}

	/**
	 * @return sonnb_XenGallery_Model_Camera
	 */
	protected function _getCameraModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Camera');
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
	 * @return sonnb_XenGallery_Model_Photo
	 */
	protected function _getPhotoModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Photo');
	}

	/**
	 * @return sonnb_XenGallery_Model_PhotoData
	 */
	protected function _getPhotoDataModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_PhotoData');
	}

	/**
	 * @return sonnb_XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Comment');
	}

	/**
	 * @return sonnb_XenGallery_Model_Location
	 */
	protected function _getLocationModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Location');
	}

	/**
	 * @return sonnb_XenGallery_Model_Watch
	 */
	protected function _getWatchModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Watch');
	}

	/**
	 * @return sonnb_XenGallery_Model_Stream
	 */
	protected function _getStreamModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Stream');
	}
}