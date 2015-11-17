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
class sonnb_XenGallery_StatsHandler_Photo extends XenForo_StatsHandler_Abstract
{
	/**
	 * @return array
	 */
	public function getStatsTypes()
	{
		return array(
			'xengallery_photo' => new XenForo_Phrase('sonnb_xengallery_stats_photos'),
			'xengallery_photo_like' => new XenForo_Phrase('sonnb_xengallery_stats_photo_likes')
		);
	}

	/**
	 * @param int $startDate
	 * @param int $endDate
	 * @return array
	 */
	public function getData($startDate, $endDate)
	{
		$db = $this->_getDb();

		$photo = $db->fetchPairs(
			$this->_getBasicDataQuery('sonnb_xengallery_content', 'content_date', 'content_type = ? AND content_state = ?'),
			array($startDate, $endDate, sonnb_XenGallery_Model_Photo::$contentType, 'visible')
		);

		$photoLikes = $db->fetchPairs(
			$this->_getBasicDataQuery('xf_liked_content', 'like_date', 'content_type = ?'),
			array($startDate, $endDate, 'sonnb_xengallery_content')
		);

		return array(
			'xengallery_photo' => $photo,
			'xengallery_photo_like' => $photoLikes
		);
	}
}