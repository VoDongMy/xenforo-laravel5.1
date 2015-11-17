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
class sonnb_XenGallery_ModerationQueueHandler_Comment extends XenForo_ModerationQueueHandler_Abstract
{
	/**
	 * @var sonnb_XenGallery_Model_Comment
	 */
	protected $_commentModel = null;
	/**
	 * @var sonnb_XenGallery_Model_Gallery
	 */
	protected $_galleryModel = null;

	/**
	 * @param array $contentIds
	 * @param array $viewingUser
	 * @return array
	 */
	public function getVisibleModerationQueueEntriesForUser(array $contentIds, array $viewingUser)
	{
		$galleryModel = $this->_getGalleryModel();
		$commentModel = $this->_getCommentModel();
		
		$comments = $commentModel->getCommentsByIds($contentIds);
		$comments = $commentModel->prepareComments($comments, array(), $viewingUser);
		$comments = $galleryModel->groupContentsContentType($comments);

		$output = array();
		foreach($comments as $commentId=>$comment)
		{
			if ($commentModel->canViewCommentAndContainer($comment, $null, $viewingUser))
			{
				$output[$comment['comment_id']] = array(
						'message' => $comment['message'],
						'user' => array(
								'user_id' => $comment['user_id'],
								'username' => $comment['username']
						),
						'title' => $comment['comment_id'],
						'link' => XenForo_Link::buildPublicLink('gallery/comments', $comment),
						'contentTypeTitle' => new XenForo_Phrase('sonnb_xengallery_comment'),
						'titleEdit' => false
				);
			}
		}

		return $output;
	}

	/**
	 * @param int $contentId
	 * @param string $message
	 * @param string $title
	 * @return bool
	 */
	public function approveModerationQueueEntry($contentId, $message, $title)
	{
		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Comment', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('comment_state', 'visible');
		$dw->set('message', $message);

		if ($dw->save())
		{
			XenForo_Model_Log::logModeratorAction(sonnb_XenGallery_Model_Comment::$xfContentType, $dw->getMergedData(), 'approve');
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @param int $contentId
	 * @return bool
	 */
	public function deleteModerationQueueEntry($contentId)
	{
		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Comment', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('comment_state', 'deleted');

		if ($dw->save())
		{
			XenForo_Model_Log::logModeratorAction(sonnb_XenGallery_Model_Comment::$xfContentType, $dw->getMergedData(), 'delete_soft', array('reason' => ''));
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * 
	 * @return sonnb_XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		if (!$this->_commentModel)
		{
			$this->_commentModel = XenForo_Model::create('sonnb_XenGallery_Model_Comment');
		}

		return $this->_commentModel;
	}

	/**
	 *
	 * @return sonnb_XenGallery_Model_Gallery
	 */
	protected function _getGalleryModel()
	{
		if (!$this->_galleryModel)
		{
			$this->_galleryModel = XenForo_Model::create('sonnb_XenGallery_Model_Gallery');
		}

		return $this->_galleryModel;
	}
}