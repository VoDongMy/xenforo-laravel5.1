<?php

/**
 * Handler for XenForo Media Gallery Albums
 * 
 * @package XenForo_Like
 */
class XenGallery_LikeHandler_Album extends XenForo_LikeHandler_Abstract
{
	/**
	 * Increments the like counter.
	 * @see XenForo_LikeHandler_Abstract::incrementLikeCounter()
	 */
	public function incrementLikeCounter($contentId, array $latestLikes, $adjustAmount = 1)
	{
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
		$dw->setExistingData($contentId);
		$dw->set('album_likes', $dw->get('album_likes') + $adjustAmount);
		$dw->set('album_like_users', $latestLikes);
		$dw->save();
	}
	
	/**
	 * Gets content data (if viewable).
	 * @see XenForo_LikeHandler_Abstract::getContentData()
	 */
	public function getContentData(array $contentIds, array $viewingUser)
	{
		$albumModel = XenForo_Model::create('XenGallery_Model_Album');
	
		$albums = $albumModel->getAlbumsByIds(
			$contentIds, array()
		);
	
		foreach ($albums AS $key => &$album)
		{
			$album = $albumModel->prepareAlbumWithPermissions($album);
			if (!$albumModel->canViewAlbum($album, $null, $viewingUser))
			{
				unset($albums[$key]);
			}
		}
	
		return $albums;
	}

	/**
	 * @see XenForo_LikeHandler_Abstract::batchUpdateContentUser()
	 */
	public function batchUpdateContentUser($oldUserId, $newUserId, $oldUsername, $newUsername)
	{
		$albumModel = XenForo_Model::create('XenGallery_Model_Album');
		$albumModel->batchUpdateLikeUser($oldUserId, $newUserId, $oldUsername, $newUsername);
	}
	
	/**
	 * Gets the name of the template that will be used when listing likes of this type.
	 *
	 * @return string news_feed_item_post_like
	 */
	public function getListTemplateName()
	{
		return 'news_feed_item_xengallery_album_like';
	}
}