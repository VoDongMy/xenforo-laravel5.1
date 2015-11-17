<?php

/**
 * Moderation queue for profile post comments.
 *
 * @package XenForo_Moderation
 */
class XenForo_ModerationQueueHandler_ProfilePostComment extends XenForo_ModerationQueueHandler_Abstract
{
	/**
	 * Gets visible moderation queue entries for specified user.
	 *
	 * @see XenForo_ModerationQueueHandler_Abstract::getVisibleModerationQueueEntriesForUser()
	 */
	public function getVisibleModerationQueueEntriesForUser(array $contentIds, array $viewingUser)
	{
		/** @var XenForo_Model_ProfilePost $profilePostModel */
		$profilePostModel = XenForo_Model::create('XenForo_Model_ProfilePost');

		$comments = $profilePostModel->getProfilePostCommentsByIds($contentIds);

		$profilePostIds = array();
		foreach ($comments AS $comment)
		{
			$profilePostIds[] = $comment['profile_post_id'];
		}
		$profilePosts = $profilePostModel->getProfilePostsByIds($profilePostIds, array(
			'join' => XenForo_Model_ProfilePost::FETCH_USER_RECEIVER
		));

		$profileUserIds = array();
		foreach ($profilePosts AS $profilePost)
		{
			$profileUserIds[] = $profilePost['profile_user_id'];
		}

		$users = XenForo_Model::create('XenForo_Model_User')->getUsersByIds($profileUserIds, array(
			'join' => XenForo_Model_User::FETCH_USER_PRIVACY,
			'followingUserId' => $viewingUser['user_id']
		));

		$output = array();
		foreach ($comments AS $comment)
		{
			if (!isset($profilePosts[$comment['profile_post_id']]))
			{
				continue;
			}

			$profilePost = $profilePosts[$comment['profile_post_id']];
			if (!isset($users[$profilePost['profile_user_id']]))
			{
				continue;
			}

			$user = $users[$profilePost['profile_user_id']];

			$canManage = true;
			if (!$profilePostModel->canViewProfilePostComment($comment, $profilePost, $user, $null, $viewingUser))
			{
				$canManage = false;
			}
			else if (!$profilePostModel->canViewProfilePostAndContainer($profilePost, $user, $null, $viewingUser))
			{
				$canManage = false;
			}
			else if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'editAny')
				|| !XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'deleteAny')
			)
			{
				$canManage = false;
			}

			if ($canManage)
			{
				$output[$comment['profile_post_comment_id']] = array(
					'message' => $comment['message'],
					'user' => array(
						'user_id' => $comment['user_id'],
						'username' => $comment['username']
					),
					'title' => new XenForo_Phrase('profile_post_comment_by_x', array('username' => $comment['username'])),
					'link' => XenForo_Link::buildPublicLink('profile-posts/comments', $profilePost),
					'contentTypeTitle' => new XenForo_Phrase('profile_post_comment'),
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
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ProfilePostComment', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('message_state', 'visible');
		$dw->set('message', $message);

		if ($dw->save())
		{
			XenForo_Model_Log::logModeratorAction('profile_post_comment', $dw->getMergedData(), 'approve');

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
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ProfilePostComment', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('message_state', 'deleted');

		if ($dw->save())
		{
			XenForo_Model_Log::logModeratorAction('profile_post_comment', $dw->getMergedData(), 'delete_soft', array('reason' => ''));
			return true;
		}
		else
		{
			return false;
		}
	}
}