<?php

/**
 * News feed handler for media actions
 */
class XenGallery_NewsFeedHandler_Media extends XenForo_NewsFeedHandler_Abstract
{
	protected $_mediaModel;
	protected $_albumModel;
	protected $_categoryModel;
	protected $_attachmentModel;

	/**
	 * Just returns a value for each requested ID
	 * but does no actual DB work
	 *
	 * @param array $contentIds
	 * @param XenForo_Model_NewsFeed $model
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, array $viewingUser)
	{
		$mediaModel = $this->_getMediaModel();
		$media = $mediaModel->getMedia(array(
			'media_id' => $contentIds,
			'privacyUserId' => $viewingUser['user_id'],
			'viewAlbums' => $this->_getAlbumModel()->canViewAlbums($null, $viewingUser),
			'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($viewingUser),
			'media_state' => 'visible'
		), array(
			'join' => XenGallery_Model_Media::FETCH_CATEGORY
				| XenGallery_Model_Media::FETCH_USER
				| XenGallery_Model_Media::FETCH_ATTACHMENT
				| XenGallery_Model_Media::FETCH_ALBUM
				| XenGallery_Model_Media::FETCH_PRIVACY
		));
		
		$media = $mediaModel->prepareMediaItems($media);

		return $media;
	}

	/**
	 * Determines if the given news feed item is viewable.
	 *
	 * @param array $item
	 * @param mixed $content
	 * @param array $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewNewsFeedItem(array $item, $content, array $viewingUser)
	{
		$mediaModel = $this->_getMediaModel();

		$media = $item['content'];

		if (!$mediaModel->canViewMediaItem($media, $null, $viewingUser))
		{
			return false;
		}

		if ($media['album_id'] > 0)
		{
			$albumModel = $this->_getAlbumModel();

			$media = $albumModel->prepareAlbum($media);
			$media['albumPermissions']['view'] = array(
				'permission' => 'view',
				'access_type' => $media['access_type'],
				'share_users' => $media['share_users']
			);

			if (!$albumModel->canViewAlbum($media, $null, $viewingUser))
			{
				return false;
			}
		}

		if ($media['category_id'] > 0)
		{
			if (!$this->_getCategoryModel()->canViewCategory($media, $null, $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * @return XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		if (!$this->_albumModel)
		{
			$this->_albumModel = XenForo_Model::create('XenGallery_Model_Album');
		}

		return $this->_albumModel;
	}

	/**
	 * @return XenGallery_Model_Category
	 */
	protected function _getCategoryModel()
	{
		if (!$this->_categoryModel)
		{
			$this->_categoryModel = XenForo_Model::create('XenGallery_Model_Category');
		}

		return $this->_categoryModel;
	}

	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		if (!$this->_mediaModel)
		{
			$this->_mediaModel = XenForo_Model::create('XenGallery_Model_Media');
		}

		return $this->_mediaModel;
	}
	
	/**
	 * @return XenForo_Model_Attachment
	 */
	protected function _getAttachmentModel()
	{
		if (!$this->_attachmentModel)
		{
			$this->_attachmentModel = XenForo_Model::create('XenForo_Model_Attachment');
		}

		return $this->_attachmentModel;
	}
}