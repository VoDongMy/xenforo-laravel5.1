<?php

/**
 * Handler for XenForo Media Gallery Comment Items
 *
 * @package XenForo_Like
 */
class XenGallery_LikeHandler_Comment extends XenForo_LikeHandler_Abstract
{
	/**
	 * Increments the like counter.
	 * @see XenForo_LikeHandler_Abstract::incrementLikeCounter()
	 */
	public function incrementLikeCounter($contentId, array $latestLikes, $adjustAmount = 1)
	{
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Comment');
		$dw->setExistingData($contentId);
		$dw->set('likes', $dw->get('likes') + $adjustAmount);
		$dw->set('like_users', $latestLikes);
		$dw->save();
	}

	/**
	 * Gets content data (if viewable).
	 * @see XenForo_LikeHandler_Abstract::getContentData()
	 */
	public function getContentData(array $contentIds, array $viewingUser)
	{
		$commentModel = XenForo_Model::create('XenGallery_Model_Comment');

		$conditions = array(
			'comment_id' => $contentIds	
		);
		$comments = $commentModel->getComments($conditions, array(
			'join' => XenGallery_Model_Comment::FETCH_MEDIA | XenGallery_Model_Comment::FETCH_ATTACHMENT | XenGallery_Model_Comment::FETCH_CATEGORY | XenGallery_Model_Comment::FETCH_USER
		));

		return $comments;
	}

	/**
	 * @see XenForo_LikeHandler_Abstract::batchUpdateContentUser()
	 */
	public function batchUpdateContentUser($oldUserId, $newUserId, $oldUsername, $newUsername)
	{
		$commentModel = XenForo_Model::create('XenGallery_Model_Comment');
		$commentModel->batchUpdateLikeUser($oldUserId, $newUserId, $oldUsername, $newUsername);
	}

	/**
	 * Gets the name of the template that will be used when listing likes of this type.
	 *
	 * @return string news_feed_item_post_like
	 */
	public function getListTemplateName()
	{
		return 'news_feed_item_xengallery_comment_like';
	}
}