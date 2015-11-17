<?php

/**
 * News feed handler for media comment actions
 */
class XenGallery_NewsFeedHandler_Comment extends XenForo_NewsFeedHandler_Abstract
{
	protected $_commentModel;
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
		$albumModel = $this->_getAlbumModel();
		$commentModel = $this->_getCommentModel();
		$mediaModel = $this->_getMediaModel();

		$fetchOptions = array(
			'privacyUserId' => $viewingUser['user_id'],
			'viewAlbums' => XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewAlbums'),
			'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor(),
			'comment_id' => $contentIds
		);
		$comments = $commentModel->getCommentsForBlockOrFeed(0, $fetchOptions);

		foreach ($comments AS &$comment)
		{
			if ($comment['media_id'])
			{
				$comment = $mediaModel->prepareMedia($comment);
			}
			else
			{
				$comment = $albumModel->prepareAlbum($comment);
			}
		}

		return $comments;
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

		return true;
	}

	/**
	 * @return XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		if (!$this->_commentModel)
		{
			$this->_commentModel = XenForo_Model::create('XenGallery_Model_Comment');
		}

		return $this->_commentModel;
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