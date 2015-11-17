<?php

class XenGallery_StatsHandler_Gallery extends XenForo_StatsHandler_Abstract
{
	public function getStatsTypes()
	{
		return array(
			'media' => new XenForo_Phrase('xengallery_media'),
			'media_disk' => new XenForo_Phrase('xengallery_media_disk_usage_mb'),			
			'media_like' => new XenForo_Phrase('xengallery_media_likes'),
			'media_rating' => new XenForo_Phrase('xengallery_media_ratings'),
			'media_comment' => new XenForo_Phrase('xengallery_media_comments'),
			'comment_like' => new XenForo_Phrase('xengallery_media_comment_likes')
		);
	}

	public function getData($startDate, $endDate)
	{
		$db = $this->_getDb();

		$media = $db->fetchPairs(
			$this->_getBasicDataQuery('xengallery_media', 'media_date', 'media_state = ?'),
			array($startDate, $endDate, 'visible')
		);
		
		$mediaLikes = $db->fetchPairs(
			$this->_getBasicDataQuery('xf_liked_content', 'like_date', 'content_type = ?'),
			array($startDate, $endDate, 'xengallery_media')
		);
		
		$mediaRatings = $db->fetchPairs(
			$this->_getBasicDataQuery('xengallery_rating', 'rating_date'),
			array($startDate, $endDate)
		);
		
		$mediaDiskUsage = $db->fetchPairs(
			$this->_getBasicDataQuery('
				xf_attachment AS attachment
				INNER JOIN xf_attachment_data AS attachdata ON
					(attachment.attachment_id = attachdata.data_id)',
				'attachdata.upload_date',
				'attachdata.attach_count > ? AND attachment.content_type = ?',
				'SUM(file_size)'),
			array($startDate, $endDate, 0, 'xengallery_media')
		);
		
		$mediaComments = $db->fetchPairs(
			$this->_getBasicDataQuery('xengallery_comment', 'comment_date'),
			array($startDate, $endDate)
		);
		
		$commentLikes = $db->fetchPairs(
			$this->_getBasicDataQuery('xf_liked_content', 'like_date', 'content_type = ?'),
			array($startDate, $endDate, 'xengallery_comment')
		);		

		return array(
			'media' => $media,
			'media_like' => $mediaLikes,
			'media_rating' => $mediaRatings,
			'media_disk' => $mediaDiskUsage,
			'media_comment' => $mediaComments,
			'media_comment_like' => $commentLikes
		);
	}
	
	/**
	 * Catches the attachment_disk_usage stats type and format the bytes integer into a megabytes float
	 *
	 * @see XenForo_StatsHandler_Abstract::getCounterForDisplay()
	 */
	public function getCounterForDisplay($statsType, $counter)
	{
		if ($statsType == 'media_disk')
		{
			return round($counter / 1048576, 3); // megabytes
		}

		return parent::getCounterForDisplay($statsType, $counter);
	}	
}