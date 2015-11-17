<?php

class XenForo_WarningHandler_ProfilePostComment extends XenForo_WarningHandler_Abstract
{
	protected function _canView(array $content, array $viewingUser)
	{
		$profilePostModel = $this->_getProfilePostModel();

		$profilePost = $profilePostModel->getProfilePostById($content['profile_post_id'], array(
			'join' => XenForo_Model_ProfilePost::FETCH_USER_RECEIVER
		));
		$user = $profilePostModel->getProfileUserFromProfilePost($profilePost, $viewingUser);

		return $profilePostModel->canViewProfilePostAndContainer($profilePost, $user, $null, $viewingUser);
	}

	protected function _canWarn($userId, array $content, array $viewingUser)
	{
		$profilePostModel = $this->_getProfilePostModel();

		$user = $profilePostModel->getProfileUserFromProfilePost($content, $viewingUser);

		return $profilePostModel->canWarnProfilePost($content, $user, $null, $viewingUser);
	}

	protected function _canDeleteContent(array $content, array $viewingUser)
	{
		$profilePostModel = $this->_getProfilePostModel();

		$user = $profilePostModel->getProfileUserFromProfilePost($content, $viewingUser);

		return $profilePostModel->canDeleteProfilePost($content, $user, 'soft', $null, $viewingUser);
	}

	protected function _getContent(array $contentIds, array $viewingUser)
	{
		return $this->_getProfilePostModel()->getProfilePostCommentsByIds($contentIds, array());
	}

	public function getContentTitle(array $content)
	{
		return $content['username'];
	}

	public function getContentDetails(array $content)
	{
		return $content['message'];
	}

	public function getContentTitleForDisplay($title)
	{
		// will be escaped in template
		return new XenForo_Phrase('profile_post_comment_by_x', array('username' => $title), false);
	}

	public function getContentUrl(array $content, $canonical = false)
	{
		return XenForo_Link::buildPublicLink(($canonical ? 'canonical:' : '') . 'profile-posts/comments', $content);
	}

	protected function _warn(array $warning, array $content, $publicMessage, array $viewingUser)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ProfilePostComment', XenForo_DataWriter::ERROR_SILENT);
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
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_ProfilePostComment', XenForo_DataWriter::ERROR_SILENT);
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
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ProfilePostComment', XenForo_DataWriter::ERROR_SILENT);
		if ($dw->setExistingData($content))
		{
			$dw->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_DELETE_REASON, $reason);
			$dw->set('message_state', 'deleted');
			$dw->save();
		}

		XenForo_Model_Log::logModeratorAction(
			'profile_post_comment', $content, 'delete_soft', array('reason' => $reason),
			$this->_getProfilePostModel()->getProfilePostById($content['profile_post_id'])
		);
	}

	public function canPubliclyDisplayWarning()
	{
		return true;
	}

	/**
	 * @return XenForo_Model_ProfilePost
	 */
	protected function _getProfilePostModel()
	{
		return XenForo_Model::create('XenForo_Model_ProfilePost');
	}
}