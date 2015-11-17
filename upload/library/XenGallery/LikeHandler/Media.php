<?php

/**
 * Handler for XenForo Media Gallery Media Items
 *
 * @package XenForo_Like
 */
class XenGallery_LikeHandler_Media extends XenForo_LikeHandler_Abstract
{
	/**
	 * Increments the like counter.
	 * @see XenForo_LikeHandler_Abstract::incrementLikeCounter()
	 */
	public function incrementLikeCounter($contentId, array $latestLikes, $adjustAmount = 1)
	{
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
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
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		$media = $mediaModel->getMediaByIds($contentIds, array(
			'join' => XenGallery_Model_Media::FETCH_ATTACHMENT
				| XenGallery_Model_Media::FETCH_CATEGORY
				| XenGallery_Model_Media::FETCH_USER
				| XenGallery_Model_Media::FETCH_ALBUM
		));
		
		foreach ($media AS $key => &$_media)
		{
			if (!$mediaModel->canViewMedia($null, $viewingUser))
			{
				unset($media[$key]);
			}
			else
			{
				$_media = $mediaModel->prepareMedia($_media);
			}
		}

		return $media;
	}

	/**
	 * @see XenForo_LikeHandler_Abstract::batchUpdateContentUser()
	 */
	public function batchUpdateContentUser($oldUserId, $newUserId, $oldUsername, $newUsername)
	{
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');
		$mediaModel->batchUpdateLikeUser($oldUserId, $newUserId, $oldUsername, $newUsername);
	}

	/**
	 * Gets the name of the template that will be used when listing likes of this type.
	 *
	 * @return string news_feed_item_post_like
	 */
	public function getListTemplateName()
	{
		return 'news_feed_item_xengallery_media_like';
	}
}