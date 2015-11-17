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
class sonnb_XenGallery_Model_InlineMod_Comment extends XenForo_Model
{
	public $enableLogging = true;

	public function canEditComments(array $commentIds, &$errorKey = '', $viewingUser = null)
	{
		return $this->_getCommentModel()->canEditAnyComment($viewingUser);
	}

	public function canDeleteComments(array $commentIds, $deleteType = 'soft', &$errorKey = '', array $viewingUser = null)
	{
		return $this->_getCommentModel()->canDeleteAnyComment($deleteType, $viewingUser);
	}

	public function deleteComments(array $commentIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$options = array_merge(
			array(
				'deleteType' => '',
				'reason' => ''
			), $options
		);

		if (!$options['deleteType'])
		{
			throw new XenForo_Exception('No deletion type specified.');
		}

		$comments = $this->getCommentsAndParentData($commentIds, $viewingUser);

		if (!$this->canDeleteComments($comments, $options['deleteType'], $errorKey, $viewingUser))
		{
			return false;
		}

		foreach ($comments AS $comment)
		{
			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Comment', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($comment);
			if (!$dw->get('comment_id'))
			{
				continue;
			}

			if ($options['deleteType'] === 'hard')
			{
				$dw->delete();

				if ($this->enableLogging)
				{
					XenForo_Model_Log::logModeratorAction('sonnb_xengallery_comment', $comment, 'delete_hard');
				}
			}
			else
			{
				$dw->setExtraData(sonnb_XenGallery_DataWriter_Comment::DATA_DELETE_REASON, $options['reason']);
				$dw->set('comment_state', 'deleted');
				$dw->save();

				if ($this->enableLogging)
				{
					XenForo_Model_Log::logModeratorAction('sonnb_xengallery_comment', $comment, 'delete_soft', array('reason' => $options['reason']));
				}
			}
		}

		return true;
	}

	public function canUndeleteComments(array $commentIds, &$errorKey = '', array $viewingUser = null)
	{
		return $this->_getCommentModel()->canDeleteAnyComment('soft', $viewingUser);
	}

	public function undeleteComments(array $commentIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$comments = $this->getCommentsAndParentData($commentIds, $viewingUser);

		if (!$this->canUndeleteComments($comments, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateCommentsState($comments, 'visible', 'deleted');

		return true;
	}

	public function canApproveUnapproveComments(array $commentIds, &$errorKey = '', array $viewingUser = null)
	{
		return $this->_getCommentModel()->canEditAnyComment($viewingUser);
	}

	public function approveComments(array $commentIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$comments = $this->getCommentsAndParentData($commentIds, $viewingUser);

		if (!$this->canApproveUnapproveComments($comments, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateCommentsState($comments, 'visible', 'moderated');

		return true;
	}


	public function unapproveComments(array $commentIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$comments = $this->getCommentsAndParentData($commentIds, $viewingUser);

		if (!$this->canApproveUnapproveComments($comments, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateCommentsState($comments, 'moderated', 'visible');

		return true;
	}

	protected function _updateCommentsState(array $comments, $newState, $expectedOldState = false)
	{
		switch ($newState)
		{
			case 'visible':
				switch (strval($expectedOldState))
				{
					case 'visible':
						return;
					case 'moderated':
						$logAction = 'approve';
						break;
					case 'deleted':
						$logAction = 'undelete';
						break;
					default:
						$logAction = 'undelete';
						break;
				}
				break;

			case 'moderated':
				switch (strval($expectedOldState))
				{
					case 'visible':
						$logAction = 'unapprove';
						break;
					case 'moderated':
						return;
					case 'deleted':
						$logAction = 'unapprove';
						break;
					default:
						$logAction = 'unapprove';
						break;
				}
				break;

			case 'deleted':
				switch (strval($expectedOldState))
				{
					case 'visible':
						$logAction = 'delete_soft';
						break;
					case 'moderated':
						$logAction = 'delete_soft';
						break;
					case 'deleted':
						return;
					default:
						$logAction = 'delete_soft';
						break;
				}
				break;

			default:
				return;
		}

		foreach ($comments AS $comment)
		{
			if ($expectedOldState && $comment['comment_state'] != $expectedOldState)
			{
				continue;
			}

			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Comment', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($comment);
			$dw->set('comment_state', $newState);

			$dw->save();

			if ($this->enableLogging)
			{
				XenForo_Model_Log::logModeratorAction('sonnb_xengallery_comment', $comment, $logAction);
			}
		}
	}

	protected function _updateCommentsBulk(array $comments, array $updates, $logAction = '')
	{
		foreach ($comments AS $comment)
		{
			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Comment', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($comment);
			$dw->bulkSet($updates);

			$dw->save();

			if ($dw->hasChanges() && $this->enableLogging && $logAction)
			{
				XenForo_Model_Log::logModeratorAction('sonnb_xengallery_comment', $comment, $logAction);
			}
		}
	}

	public function getCommentsAndParentData(array $commentIds, array $viewingUser = null)
	{
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Comment::FETCH_USER
		);

		$comments = $this->_getCommentModel()->getCommentsByIds($commentIds, $fetchOptions);

		return $this->_getCommentModel()->prepareComments($comments, $fetchOptions, $viewingUser);
	}

	/**
	 * @return sonnb_XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Comment');
	}

	/**
	 * @return sonnb_XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Album');
	}

	/**
	 * @return sonnb_XenGallery_Model_Gallery
	 */
	protected function _getGalleryModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Gallery');
	}
}