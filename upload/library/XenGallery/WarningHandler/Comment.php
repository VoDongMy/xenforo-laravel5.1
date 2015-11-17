<?php

class XenGallery_WarningHandler_Comment extends XenForo_WarningHandler_Abstract
{
	protected function _canView(array $content, array $viewingUser)
	{
		$mediaModel = $this->_getMediaModel();
		$categoryModel = $this->_getCategoryModel();
		$albumModel = $this->_getAlbumModel();
		$commentModel = $this->_getCommentModel();

		$comment = $commentModel->getCommentById($content['comment_id'], array('join' => XenGallery_Model_Comment::FETCH_USER));
		if (!$comment)
		{
			return false;
		}

		if ($comment['content_type'] == 'album')
		{
			$album = $albumModel->getAlbumById($comment['content_id']);
			$album['albumPermissions']['view'] = array(
				'permission' => 'view',
				'access_type' => $album['access_type'],
				'share_users' => $album['share_users']
			);
			if ($albumModel->canViewAlbum($album, $null, $viewingUser))
			{
				return true;
			}
		}
		else
		{
			$media = $mediaModel->getMediaById($comment['content_id']);

			$canViewMedia = false;
			if ($mediaModel->canViewMediaItem($media, $null, $viewingUser))
			{
				$canViewMedia = true;
			}
			if ($media['category_id'])
			{
				$category = $categoryModel->getCategoryById($media['category_id']);
				if ($categoryModel->canViewCategory($category, $null, $viewingUser) && $canViewMedia)
				{
					return true;
				}
			}
			else if ($media['album_id'])
			{
				$album = $albumModel->getAlbumById($media['album_id']);
				$album['albumPermissions']['view'] = array(
					'permission' => 'view',
					'access_type' => $album['access_type'],
					'share_users' => $album['share_users']
				);
				if ($albumModel->canViewAlbum($album, $null, $viewingUser))
				{
					return true;
				}
			}
		}

		return false;
	}

	protected function _canWarn($userId, array $content, array $viewingUser)
	{
		return $this->_getCommentModel()->canWarnComment($content, $null, $viewingUser);
	}

	protected function _canDeleteContent(array $content, array $viewingUser)
	{
		return $this->_getCommentModel()->canDeleteComment($content);
	}

	protected function _getContent(array $contentIds, array $viewingUser)
	{
		$commentModel = $this->_getCommentModel();

		$conditions = array(
			'comment_id' => $contentIds,
			'deleted' => $commentModel->canViewDeletedComment($null, $viewingUser),
			'moderated' => $commentModel->canViewUnapprovedComment($null, $viewingUser),
		);

		return $commentModel->getComments($conditions);
	}

	public function getContentTitle(array $content)
	{
		return XenForo_Template_Helper_Core::helperSnippet($content['message'], 150, array('stripQuote' => true));
	}

	public function getContentTitleForDisplay($title)
	{
		// will be escaped in template
		return $title;
	}

	public function getContentUrl(array $content, $canonical = false)
	{
		return XenForo_Link::buildPublicLink(($canonical ? 'canonical:' : '') . 'xengallery/comments', $content);
	}

	protected function _warn(array $warning, array $content, $publicMessage, array $viewingUser)
	{
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Comment', XenForo_DataWriter::ERROR_SILENT);
		if ($dw->setExistingData($content))
		{
			$dw->set('warning_id', $warning['warning_id']);
			$dw->set('warning_message', $publicMessage);
			$dw->save();
		}
	}

	protected function _reverseWarning(array $warning, array $content)
	{
		if ($content)
		{
			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Comment', XenForo_DataWriter::ERROR_SILENT);
			if ($dw->setExistingData($content))
			{
				$dw->set('warning_id', 0);
				$dw->set('warning_message', '');
				$dw->save();
			}
		}
	}

	protected function _deleteContent(array $content, $reason, array $viewingUser)
	{
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Comment', XenForo_DataWriter::ERROR_SILENT);
		if ($dw->setExistingData($content))
		{
			$dw->setExtraData(XenGallery_DataWriter_Comment::DATA_DELETE_REASON, $reason);
			$dw->set('comment_state', 'deleted');
			$dw->save();
		}
	}

	public function canPubliclyDisplayWarning()
	{
		return true;
	}

	/**
	 * @return XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		return XenForo_Model::create('XenGallery_Model_Comment');
	}

	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		return XenForo_Model::create('XenGallery_Model_Media');
	}

	/**
	 * @return XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return XenForo_Model::create('XenGallery_Model_Album');
	}

	/**
	 * @return XenGallery_Model_Category
	 */
	protected function _getCategoryModel()
	{
		return XenForo_Model::create('XenGallery_Model_Category');
	}
}