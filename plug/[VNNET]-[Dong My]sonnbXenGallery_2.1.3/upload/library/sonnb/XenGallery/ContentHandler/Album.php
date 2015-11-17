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
class sonnb_XenGallery_ContentHandler_Album extends sonnb_XenGallery_ContentHandler_Abstract
{

	/**
	 * @var sonnb_XenGallery_Model_Album
	 */
	protected $_albumModel = null;

	/**
	 * @param $contentId
	 * @param array $viewingUser
	 * @return array
	 */
	public function getContentById($contentId, array $viewingUser = null)
	{
		if (!$contentId)
		{
			return array();
		}

		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Album::FETCH_COVER_PHOTO
				| sonnb_XenGallery_Model_Album::FETCH_USER,
			'followingUserId' => XenForo_Visitor::getUserId()
		);

		$album = $this->_getAlbumModel()->getAlbumById($contentId, $fetchOptions);
		$album = $this->_getAlbumModel()->prepareAlbum($album, $fetchOptions, $viewingUser);
		$album = $this->_getAlbumModel()->attachCoverToAlbum($album, $fetchOptions);

		return $album;
	}

	/**
	 * @param array $contentIds
	 * @param array $viewingUser
	 * @return array
	 */
	public function getContentsByIds(array $contentIds, array $viewingUser = null)
	{
		if (!$contentIds)
		{
			return array();
		}

		$conditions = $this->_getAlbumModel()->getPermissionBasedAlbumFetchConditions()+array(
			'album_id' => $contentIds
		);
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Album::FETCH_COVER_PHOTO
				| sonnb_XenGallery_Model_Album::FETCH_USER,
			'followingUserId' => XenForo_Visitor::getUserId()
		);

		$albums = $this->_getAlbumModel()->getAlbums($conditions, $fetchOptions);
		$albums = $this->_getAlbumModel()->prepareAlbums($albums, $fetchOptions, $viewingUser);
		$albums = $this->_getAlbumModel()->attachCoversToAlbums($albums, $fetchOptions);

		return $albums;
	}

	/**
	 * @param array $album
	 * @param array $viewingUser
	 * @return bool
	 */
	public function canViewContent(array $album, array $viewingUser = null)
	{
		return $this->_getAlbumModel()->canViewAlbum($album, $null, $viewingUser);
	}

	/**
	 * @param array $album
	 * @return string
	 */
	public function getContentLink(array $album)
	{
		return XenForo_Link::buildPublicLink('gallery/albums', $album);
	}

	/**
	 * @param array $album
	 * @param XenForo_View $view
	 * @return XenForo_Template_Abstract
	 */
	public function renderHtml(array $album, XenForo_View $view)
	{
		return $view->createTemplateObject(
			'sonnb_xengallery_album_list_item',
			array(
				'album' => $album
			)
		);
	}

	/**
	 * @return sonnb_XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		if ($this->_albumModel === null)
		{
			$this->_albumModel = XenForo_Model::create('sonnb_XenGallery_Model_Album');
		}

		return $this->_albumModel;
	}
}