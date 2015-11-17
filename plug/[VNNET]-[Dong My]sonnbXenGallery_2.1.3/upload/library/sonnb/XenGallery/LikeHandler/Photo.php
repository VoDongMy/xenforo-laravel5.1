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
class sonnb_XenGallery_LikeHandler_Photo extends XenForo_LikeHandler_Abstract
{

	/**
	 * @param int $contentId
	 * @param array $latestLikes
	 * @param int $adjustAmount
	 */
	public function incrementLikeCounter($contentId, array $latestLikes, $adjustAmount = 1)
	{
		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Photo');
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
		/* @var $photoModel sonnb_XenGallery_Model_Photo */
		$photoModel = XenForo_Model::create('sonnb_XenGallery_Model_Photo');
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Photo::FETCH_USER
						| sonnb_XenGallery_Model_Photo::FETCH_ALBUM
						| sonnb_XenGallery_Model_Photo::FETCH_DATA,
			'followingUserId' => XenForo_Visitor::getUserId()
		);

		$photos = $photoModel->getContentsByIds($contentIds, $fetchOptions);
		$photos = $photoModel->prepareContents($photos, $fetchOptions);

		$output = array();
		foreach ($photos AS $photoId => $photo)
		{
			if (!$photoModel->canViewContentAndContainer($photo, $photo['album'], $errorPhraseKey, $viewingUser))
			{
				continue;
			}

			$output[$photoId] = $photo;
		}

		return $output;
	}

	/**
	 * @return string
	 */
	public function getListTemplateName()
	{
		return 'sonnb_xengallery_news_feed_photo_like';
	}
}