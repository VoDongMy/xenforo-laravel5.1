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
class sonnb_XenGallery_StatsHandler_Album extends XenForo_StatsHandler_Abstract
{
	/**
	 * @return array
	 */
	public function getStatsTypes()
	{
		return array(
			'xengallery_album' => new XenForo_Phrase('sonnb_xengallery_stats_albums'),
			'xengallery_album_like' => new XenForo_Phrase('sonnb_xengallery_stats_album_likes')
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

		$albums = $db->fetchPairs(
			$this->_getBasicDataQuery('sonnb_xengallery_album', 'album_date', 'album_state = ?'),
			array($startDate, $endDate, 'visible')
		);

		$albumLikes = $db->fetchPairs(
			$this->_getBasicDataQuery('xf_liked_content', 'like_date', 'content_type = ?'),
			array($startDate, $endDate, 'sonnb_xengallery_album')
		);

		return array(
			'xengallery_album' => $albums,
			'xengallery_album_like' => $albumLikes
		);
	}
}