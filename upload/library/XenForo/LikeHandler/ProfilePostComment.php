<?php

/**
 * Handler for the specific profile post comment-related like aspects.
 *
 * @package XenForo_Like
 */
class XenForo_LikeHandler_ProfilePostComment extends XenForo_LikeHandler_Abstract
{
	/**
	 * Increments the like counter.
	 * @see XenForo_LikeHandler_Abstract::incrementLikeCounter()
	 */
	public function incrementLikeCounter($contentId, array $latestLikes, $adjustAmount = 1)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ProfilePostComment');
		if ($dw->setExistingData($contentId))
		{
			$dw->set('likes', $dw->get('likes') + $adjustAmount);
			$dw->set('like_users', $latestLikes);
			$dw->save();
		}
	}

	/**
	 * Gets content data (if viewable).
	 * @see XenForo_LikeHandler_Abstract::getContentData()
	 */
	public function getContentData(array $contentIds, array $viewingUser)
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
	 * @see XenForo_LikeHandler_Abstract::batchUpdateContentUser()
	 */
	public function batchUpdateContentUser($oldUserId, $newUserId, $oldUsername, $newUsername)
	{
		$profilePostModel = XenForo_Model::create('XenForo_Model_ProfilePost');
		$profilePostModel->batchUpdateProfilePostCommentLikeUser($oldUserId, $newUserId, $oldUsername, $newUsername);
	}

	/**
	 * Gets the name of the template that will be used when listing likes of this type.
	 *
	 * @return string news_feed_item_profile_post_like
	 */
	public function getListTemplateName()
	{
		return 'news_feed_item_profile_post_comment_like';
	}
}