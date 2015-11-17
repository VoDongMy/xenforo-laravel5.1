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
class sonnb_XenGallery_StatsHandler_Video extends XenForo_StatsHandler_Abstract
{
	/**
	 * @return array
	 */
	public function getStatsTypes()
	{
		return array(
			'xengallery_video' => new XenForo_Phrase('sonnb_xengallery_stats_videos'),
			'xengallery_video_like' => new XenForo_Phrase('sonnb_xengallery_stats_video_likes')
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

		$video = $db->fetchPairs(
			$this->_getBasicDataQuery('sonnb_xengallery_content', 'content_date', 'content_type = ? AND content_state = ?'),
			array($startDate, $endDate, sonnb_XenGallery_Model_Video::$contentType, 'visible')
		);

		$videoLikes = $db->fetchPairs(
			$this->_getBasicDataQuery('xf_liked_content', 'like_date', 'content_type = ?'),
			array($startDate, $endDate, 'sonnb_xengallery_content')
		);

		return array(
			'xengallery_video' => $video,
			'xengallery_video_like' => $videoLikes
		);
	}
}