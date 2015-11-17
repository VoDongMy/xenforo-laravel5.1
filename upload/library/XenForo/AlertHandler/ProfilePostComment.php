<?php

class XenForo_AlertHandler_ProfilePostComment extends XenForo_AlertHandler_Abstract
{
	/**
	 * @var XenForo_Model_ProfilePost
	 */
	protected $_profilePostModel = null;

	/**
	 * Gets the profile post comment content.
	 * @see XenForo_AlertHandler_Abstract::getContentByIds()
	 */
	public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
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

		$userIds = array();
		foreach ($profilePosts AS $profilePost)
		{
			$userIds[$profilePost['profile_user_id']] = true;
		}
		$users = $profilePostModel->getModelFromCache('XenForo_Model_User')->getUsersByIds(array_keys($userIds), array(
			'join' => XenForo_Model_User::FETCH_USER_PRIVACY,
			'followingUserId' => $viewingUser['user_id']
		));

		foreach ($comments AS $key => &$comment)
		{
			if (!isset($profilePosts[$comment['profile_post_id']]))
			{
				unset ($comments[$key]);
				continue;
			}

			$profilePost = $profilePosts[$comment['profile_post_id']];

			if (isset($users[$profilePost['profile_user_id']]))
			{
				$user = $users[$profilePost['profile_user_id']];
				if (!$profilePostModel->canViewProfilePostAndContainer(
					$profilePost, $user, $null, $viewingUser
				))
				{
					unset($comments[$key]);
					continue;
				}
				else
				{
					$comment['profileUser'] = $user;
				}
			}

			$comment['profilePost'] = $profilePost;
		}

		return $comments;
	}

	/**
	 * Determines if the profile post comment is viewable.
	 * @see XenForo_AlertHandler_Abstract::canViewAlert()
	 */
	public function canViewAlert(array $alert, $content, array $viewingUser)
	{
		$profilePostModel = $this->_getProfilePostModel();

		return (
			$profilePostModel->canViewProfilePostComment(
				$content, $content['profilePost'], $content, $null, $viewingUser
			) && $profilePostModel->canViewProfilePostAndContainer(
				$content['profilePost'], $content['profileUser'], $null, $viewingUser
			)
		);
	}

	/**
	 * @return XenForo_Model_ProfilePost
	 */
	protected function _getProfilePostModel()
	{
		if (!$this->_profilePostModel)
		{
			$this->_profilePostModel = XenForo_Model::create('XenForo_Model_ProfilePost');
		}

		return $this->_profilePostModel;
	}
}