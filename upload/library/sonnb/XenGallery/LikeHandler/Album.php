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
class sonnb_XenGallery_LikeHandler_Album extends XenForo_LikeHandler_Abstract
{

	/**
	 * @param int $contentId
	 * @param array $latestLikes
	 * @param int $adjustAmount
	 */
	public function incrementLikeCounter($contentId, array $latestLikes, $adjustAmount = 1)
	{
		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
		$dw->setExistingData($contentId);
		
		$likes = 0;
		if ($dw->get('likes') + $adjustAmount > 0)
		{
			$likes = $dw->get('likes') + $adjustAmount;
		}
		
		$dw->set('likes', $likes);
		$dw->set('like_users', $latestLikes);
		$dw->save();
	}

	/**
	 * @param array $contentIds
	 * @param array $viewingUser
	 * @return array
	 */
	public function getContentData(array $contentIds, array $viewingUser)
	{
		/* @var $albumModel sonnb_XenGallery_Model_Album */
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Album::FETCH_USER,
			'followingUserId' => XenForo_Visitor::getUserId()
		);
		$albumModel = XenForo_Model::create('sonnb_XenGallery_Model_Album');
		$albums = $albumModel->getAlbumsByIds($contentIds, $fetchOptions);
		$albums = $albumModel->prepareAlbums($albums);
		$albums = $albumModel->attachContentsToAlbums($albums);

		$output = array();
		foreach ($albums AS $albumId => $album)
		{
			if (!$albumModel->canViewAlbum($album, $errorPhraseKey, $viewingUser))
			{
				continue;
			}

			if (empty($album['contents']))
			{
				continue;
			}

			$output[$albumId] = $album;
		}

		return $output;
	}

	/**
	 * @return string
	 */
	public function getListTemplateName()
	{
		return 'sonnb_xengallery_news_feed_album_like';
	}
}