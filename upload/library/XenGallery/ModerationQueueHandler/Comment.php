<?php

class XenGallery_ModerationQueueHandler_Comment extends XenForo_ModerationQueueHandler_Abstract
{
	/**
	 * Gets visible moderation queue entries for specified user.
	 *
	 * @see XenForo_ModerationQueueHandler_Abstract::getVisibleModerationQueueEntriesForUser()
	 */
	public function getVisibleModerationQueueEntriesForUser(array $contentIds, array $viewingUser)
	{
		$commentModel = $this->_getCommentModel();
		$mediaModel = $this->_getMediaModel();
		$albumModel = $this->_getAlbumModel();

		$comments = $commentModel->getCommentsByIds($contentIds);

		$output = array();
		foreach ($comments AS $comment)
		{
			$canManage = $commentModel->canManageModeratedComment($comment);

			$item['content_title'] = '';
			if ($comment['content_type'] == 'album')
			{
				$album = $albumModel->getAlbumById($comment['content_id']);
				$album = $albumModel->prepareAlbumWithPermissions($album);
				if ($album && $albumModel->canViewAlbum($album, $null, $viewingUser))
				{
					$comment['content_title'] = $album['album_title'];
				}
				else
				{
					continue;
				}
			}
			else
			{
				$media = $mediaModel->getMediaById($comment['content_id']);
				if ($media && $mediaModel->canViewMediaItem($media, $null, $viewingUser))
				{
					$comment['content_title'] = $media['media_title'];
				}
				else
				{
					continue;
				}
			}

			if ($canManage)
			{
				$output[$comment['comment_id']] = array(
					'message' => $comment['message'],
					'user' => array(
						'user_id' => $comment['user_id'],
						'username' => $comment['username']
					),
					'title' => new XenForo_Phrase('xengallery_comment_on_x_y', array('type' => $comment['content_type'], 'title' => $comment['content_title'])),
					'link' => XenForo_Link::buildPublicLink('xengallery/comments', $comment),
					'contentTypeTitle' => new XenForo_Phrase('xengallery_moderated_comment'),
					'titleEdit' => false
				);
			}
		}

		return $output;
	}

	/**
	 * Approves the specified moderation queue entry.
	 *
	 * @see XenForo_ModerationQueueHandler_Abstract::approveModerationQueueEntry()
	 */
	public function approveModerationQueueEntry($contentId, $message, $title)
	{
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Comment', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('comment_state', 'visible');

		if ($dw->save())
		{
			$comment = $this->_getCommentModel()->getCommentById($contentId, array(
				'join' =>	XenGallery_Model_Comment::FETCH_MEDIA
					| XenGallery_Model_Comment::FETCH_ALBUM_CONTENT
					| XenGallery_Model_Comment::FETCH_USER
			));

			XenForo_Model_Log::logModeratorAction('xengallery_comment', $comment, 'approve');

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Deletes the specified moderation queue entry.
	 *
	 * @see XenForo_ModerationQueueHandler_Abstract::deleteModerationQueueEntry()
	 */
	public function deleteModerationQueueEntry($contentId)
	{
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Comment', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('comment_state', 'deleted');

		if ($dw->save())
		{
			$comment = $this->_getCommentModel()->getCommentById($contentId, array(
				'join' =>	XenGallery_Model_Comment::FETCH_MEDIA
					| XenGallery_Model_Comment::FETCH_ALBUM_CONTENT
					| XenGallery_Model_Comment::FETCH_USER
			));
			XenForo_Model_Log::logModeratorAction('xengallery_comment', $comment, 'delete_soft', array('reason' => ''));

			return true;
		}
		else
		{
			return false;
		}
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