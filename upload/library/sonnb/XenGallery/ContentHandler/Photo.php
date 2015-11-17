<?php

/**
 * Product: sonnb - XenGallery
 * Version: 1.1.3
 * Date: 28th September 2013
 * Author: sonnb
 * Website: www.sonnb.com
 * License: One license is valid for only one nominated domain.
 * You might not copy or redistribute this addon.
 * Any action to public or redistribute must be authorized from author
 */

class sonnb_XenGallery_ContentHandler_Photo extends sonnb_XenGallery_ContentHandler_Abstract
{
	/**
	 * @var sonnb_XenGallery_Model_Photo
	 */
	protected $_photoModel = null;

	public function getContentById($contentId, array $viewingUser = null)
	{
		if (!$contentId)
		{
			return array();
		}

		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Photo::FETCH_ALBUM
				| sonnb_XenGallery_Model_Photo::FETCH_DATA
				| sonnb_XenGallery_Model_Photo::FETCH_USER,
			'followingUserId' => XenForo_Visitor::getUserId()
		);

		$photo = $this->_getPhotoModel()->getPhotoById($contentId, $fetchOptions);
		$photo = $this->_getPhotoModel()->preparePhoto($photo, $fetchOptions, $viewingUser);

		return $photo;
	}

	public function getContentsByIds(array $contentIds, array $viewingUser = null)
	{
		if (!$contentIds)
		{
			return array();
		}

		$conditions = $this->_getPhotoModel()->getPermissionBasedPhotoFetchConditions()+array(
			'photo_id' => $contentIds
		);
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Photo::FETCH_ALBUM
					| sonnb_XenGallery_Model_Photo::FETCH_DATA
					| sonnb_XenGallery_Model_Photo::FETCH_USER,
			'followingUserId' => XenForo_Visitor::getUserId()
		);

		$photos = $this->_getPhotoModel()->getPhotos($conditions, $fetchOptions);
		$photos = $this->_getPhotoModel()->preparePhotos($photos, $fetchOptions, $viewingUser);

		return $photos;
	}

	public function canViewContent(array $photo, array $viewingUser = null)
	{
		$album = null;
		if (isset($photo['album']))
		{
			$album = $photo['album'];
		}

		return $this->_getPhotoModel()->canViewPhotoAndContainer($photo, $album, $null, $viewingUser);
	}

	public function getContentLink(array $photo)
	{
		return XenForo_Link::buildPublicLink('gallery/photos', $photo);
	}

	public function renderHtml(array $photo, XenForo_View $view)
	{
		$album = null;
		if (isset($photo['album']))
		{
			$album = $photo['album'];
		}

		return $view->createTemplateObject(
			'sonnb_xengallery_photo_list_item',
			array(
				'photo' => $photo,
				'album' => $album
			)
		);
	}

	/**
	 * @return sonnb_XenGallery_Model_Photo
	 */
	protected function _getPhotoModel()
	{
		if ($this->_photoModel == null)
		{
			$this->_photoModel = XenForo_Model::create('sonnb_XenGallery_Model_Photo');
		}

		return $this->_photoModel;
	}
}