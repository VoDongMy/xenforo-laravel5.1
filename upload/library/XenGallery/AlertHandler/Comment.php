<?php

class XenGallery_AlertHandler_Comment extends XenForo_AlertHandler_Abstract
{
	protected $_commentModel;

	/**
	 * Fetches the content required by alerts.
	 *
	 * @param array $contentIds
	 * @param XenForo_Model_Alert $model Alert model invoking this
	 * @param integer $userId User ID the alerts are for
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
	{
		$commentModel = $this->_getCommentModel();
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');
		
		$conditions = array(
			'comment_id' => $contentIds
		);
		
		$fetchOptions = array(
			'join' => XenGallery_Model_Comment::FETCH_USER | XenGallery_Model_Comment::FETCH_MEDIA
		);
		
		$comments = $commentModel->getComments($conditions, $fetchOptions);

		$mediaIds = array();
		$albumIds = array();
		foreach ($comments AS $key => &$comment)
		{
			if (!$mediaModel->canViewMedia($null, $viewingUser))
			{
				unset($comments[$key]);
			}

			if ($comment['content_type'] == 'media')
			{
				$mediaIds[$comment['content_id']] = array(
					'comment_id' => $key,
					'content_id' => $comment['content_id']
				);
			}

			if ($comment['content_type'] == 'album')
			{
				$albumIds[$comment['content_id']] = array(
					'comment_id' => $key,
					'content_id' => $comment['content_id']
				);
			}
		}

		$media = array();
		if ($mediaIds && is_array($mediaIds))
		{
			/** @var $mediaModel XenGallery_Model_Media */
			$mediaModel = XenForo_Model::create('XenGallery_Model_Media');
			$media = $mediaModel->getMediaByIds(array_keys($mediaIds));
		}

		$albums = array();
		if ($albumIds && is_array($albumIds))
		{
			/** @var $albumModel XenGallery_Model_Album */
			$albumModel = XenForo_Model::create('XenGallery_Model_Album');
			$albums = $albumModel->getAlbumsByIds(array_keys($albumIds));
		}

		foreach ($comments AS &$comment)
		{
			if ($comment['content_type'] == 'media')
			{
				if (!empty($media[$comment['content_id']]))
				{
					$comment['content'] = $media[$comment['content_id']];
				}
			}

			if ($comment['content_type'] == 'album')
			{
				if (!empty($albums[$comment['content_id']]))
				{
					$comment['content'] = $albums[$comment['content_id']];
				}
			}
		}
	
		return $comments;
	}

	/**
	* Determines if the comment is viewable.
	* @see XenForo_AlertHandler_Abstract::canViewAlert()
	*/
	public function canViewAlert(array $alert, $content, array $viewingUser)
	{	
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
}
