<?php

class XenGallery_Model_InlineMod_Comment extends XenForo_Model
{
	/**
	 * From a List of IDs, gets info about the comment.
	 *
	 * @param array $commentIds List of IDs
	 *
	 * @return array Format: list of comment
	 */
	public function getCommentData(array $commentIds, array $fetchOptions = array())
	{
		if (!$commentIds)
		{
			return array();
		}

		if (!$fetchOptions)
		{
			$fetchOptions = array(
				'join' => XenGallery_Model_Comment::FETCH_USER
					| XenGallery_Model_Comment::FETCH_MEDIA
					| XenGallery_Model_Comment::FETCH_ALBUM_CONTENT
			);
		}

		return $this->_getCommentModel()->getCommentsByIds($commentIds, $fetchOptions);
	}

	/**
	 * Determines if the selected comment IDs can be deleted.
	 *
	 * @param array $commentIds List of IDs check
	 * @param string $deleteType The type of deletion being requested (soft or hard)
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canDeleteComment(array $commentIds, $deleteType = 'soft', &$errorKey = '', array $viewingUser = null)
	{
		$comment = $this->getCommentData($commentIds);
		return $this->canDeleteCommentData($comment, $deleteType, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected comment data can be deleted.
	 *
	 * @param array $comment List of data to be deleted
	 * @param string $deleteType Type of deletion (soft or hard)
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canDeleteCommentData(array $comment, $deleteType, &$errorKey = '', array $viewingUser = null)
	{
		if (!$comment)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);
		$commentModel = $this->_getCommentModel();

		foreach ($comment AS $_comment)
		{
			if ($_comment['comment_state'] != 'deleted' && !$commentModel->canDeleteComment($_comment, $deleteType, $errorKey, $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Deletes the specified comment if permissions are sufficient.
	 *
	 * @param array $commentIds List of IDs to delete
	 * @param array $options Options that control the delete. Supports deleteType (soft or hard).
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function deleteComment(array $commentIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$options = array_merge(
			array(
				'deleteType' => '',
				'reason' => '',

				'authorAlert' => false,
				'authorAlertReason' => ''
			), $options
		);

		if (!$options['deleteType'])
		{
			throw new XenForo_Exception('No deletion type specified.');
		}

		$fetchOptions = array(
			'join' => XenGallery_Model_Comment::FETCH_USER
				| XenGallery_Model_Comment::FETCH_MEDIA
				| XenGallery_Model_Comment::FETCH_ALBUM_CONTENT
		);

		$comment = $this->getCommentData($commentIds, $fetchOptions);

		if (empty($options['skipPermissions']) && !$this->canDeleteCommentData($comment, $options['deleteType'], $errorKey, $viewingUser))
		{
			return false;
		}

		foreach ($comment AS $_comment)
		{
			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Comment');
			$dw->setExistingData($_comment);
			if (!$dw->get('comment_id'))
			{
				continue;
			}

			if ($options['deleteType'] == 'hard')
			{
				$dw->delete();
			}
			else
			{
				$dw->setExtraData(XenGallery_DataWriter_Comment::DATA_DELETE_REASON, $options['reason']);
				$dw->set('comment_state', 'deleted');
				$dw->save();
			}

			XenForo_Model_Log::logModeratorAction('xengallery_comment', $_comment, 'delete_' . $options['deleteType'], array('reason' => $options['reason']));

			if ($_comment['content_type'] == 'media')
			{
				$content = $this->_getMediaModel()->getMediaById($_comment['content_id']);
				if (!$content)
				{
					continue;
				}
				$content['content_title'] = $content['media_title'];
				$content['isAlbum'] = false;
			}
			else
			{
				$content = $this->_getAlbumModel()->getAlbumByIdSimple($_comment['content_id']);
				if (!$content)
				{
					continue;
				}
				$content['content_title'] = $content['album_title'];
				$content['isAlbum'] = true;
			}

			if ($_comment['comment_state'] == 'visible' && $options['authorAlert'])
			{
				$this->_getMediaModel()->sendAuthorAlert(
					$_comment, 'xengallery_comment', 'delete', $options, array(
						'content' => $content,
						'comment' => $_comment
					)
				);
			}
		}

		return true;
	}

	/**
	 * Undeletes the specified comment if permissions are sufficient.
	 *
	 * @param array $commentIds List of IDs to undelete
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function undeleteComment(array $commentIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$comments = $this->getCommentData($commentIds);

		if (empty($options['skipPermissions']) && !$this->canDeleteCommentData($comments, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateCommentCommentState($comments, 'visible', 'deleted');

		return true;
	}

	/**
	 * Determines if the selected comment IDs can be approved.
	 *
	 * @param array $commentIds List of IDs to check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canApproveComment(array $commentIds, &$errorKey = '', array $viewingUser = null)
	{
		$comment = $this->getCommentData($commentIds);
		return $this->canApproveCommentData($comment, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected comment data can be approved.
	 *
	 * @param array $comment List of data to be checked
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canApproveCommentData(array $comment, &$errorKey = '', array $viewingUser = null)
	{
		if (!$comment)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);
		$commentModel = $this->_getCommentModel();

		foreach ($comment AS $_comment)
		{
			if ($_comment['comment_state'] == 'moderated' && !$commentModel->canApproveComment($_comment,$errorKey, $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Approves the specified comment if permissions are sufficient.
	 *
	 * @param array $commentIds List of IDs to approve
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function approveComment(array $commentIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$comment = $this->getCommentData($commentIds);

		if (empty($options['skipPermissions']) && !$this->canApproveCommentData($comment, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateCommentCommentState($comment, 'visible', 'moderated');

		return true;
	}

	/**
	 * Determines if the selected comment IDs can be unapproved.
	 *
	 * @param array $commentIds List of IDs to check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canUnapproveComment(array $commentIds, &$errorKey = '', array $viewingUser = null)
	{
		$comment = $this->getCommentData($commentIds);
		return $this->canUnapproveCommentData($comment, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected comment data can be unapproved.
	 *
	 * @param array $comment List of data to be checked
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canUnapproveCommentData(array $comment, &$errorKey = '', array $viewingUser = null)
	{
		if (!$comment)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);
		$commentModel = $this->_getCommentModel();

		foreach ($comment AS $_comment)
		{
			if ($_comment['comment_state'] == 'visible' && !$commentModel->canUnapproveComment($_comment, $errorKey, $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Unapproves the specified comment if permissions are sufficient.
	 *
	 * @param array $commentIds List of IDs to unapprove
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function unapproveComment(array $commentIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$comment = $this->getCommentData($commentIds);

		if (empty($options['skipPermissions']) && !$this->canUnapproveCommentData($comment, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateCommentCommentState($comment, 'moderated', 'visible');

		return true;
	}

	/**
	 * Internal helper to update the comment_state of a collection of comment items.
	 *
	 * @param array $comment Information about the comment to update
	 * @param string $newState New comment state (visible, moderated, deleted)
	 * @param string|bool $expectedOldState If specified, only updates if the old state matches
	 */
	protected function _updateCommentCommentState(array $comment, $newState, $expectedOldState = false)
	{
		switch ($newState)
		{
			case 'visible':
				switch (strval($expectedOldState))
				{
					case 'visible': return;
					case 'moderated': $logAction = 'approve'; break;
					case 'deleted': $logAction = 'undelete'; break;
					default: $logAction = 'undelete'; break;
				}
				break;

			case 'moderated':
				switch (strval($expectedOldState))
				{
					case 'visible': $logAction = 'unapprove'; break;
					case 'moderated': return;
					case 'deleted': $logAction = 'unapprove'; break;
					default: $logAction = 'unapprove'; break;
				}
				break;

			case 'deleted':
				switch (strval($expectedOldState))
				{
					case 'visible': $logAction = 'delete_soft'; break;
					case 'moderated': $logAction = 'delete_soft'; break;
					case 'deleted': return;
					default: $logAction = 'delete_soft'; break;
				}
				break;

			default: return;
		}

		foreach ($comment AS $_comment)
		{
			if ($expectedOldState && $_comment['comment_state'] != $expectedOldState)
			{
				continue;
			}

			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Comment', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($_comment);
			$dw->set('comment_state', $newState);
			$dw->save();

			XenForo_Model_Log::logModeratorAction('xengallery_comment', $_comment, $logAction);
		}
	}

	public function canSendActionAlert(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return (
			XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteCommentAny')
			|| XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editCommentAny')
		);
	}

	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Media');
	}

	/**
	 * @return XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Album');
	}

	/**
	 * @return XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Comment');
	}
}