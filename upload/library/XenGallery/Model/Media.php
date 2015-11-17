<?php

class XenGallery_Model_Media extends XenForo_Model
{
	const FETCH_USER = 0x01;
	const FETCH_USER_OPTION = 0x02;
	const FETCH_ATTACHMENT = 0x04;
	const FETCH_DELETION_LOG = 0x08;
	const FETCH_CATEGORY = 0x10;
	const FETCH_ALBUM = 0x20;
	const FETCH_PRIVACY = 0x40;
	const FETCH_TAGGING = 0x80;
	const FETCH_LAST_VIEW = 0x100;

	public static $voteThreshold = 10;
	public static $averageVote = 3;

	protected $_thumbnailPath = '';
		
	/**
	 * Gets a single media record specified by its ID
	 *
	 * @param integer $mediaId
	 *
	 * @return array
	 */
	public function getMediaById($mediaId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareMediaFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT media.*
				' . $joinOptions['selectFields'] . '
			FROM xengallery_media AS media
				' . $joinOptions['joinTables'] . '
			WHERE media.media_id = ?
		', $mediaId);
	}
	
	public function getCategoryIdByMediaId($mediaId)
	{
		return $this->_getDb()->fetchOne('
			SELECT category_id
			FROM xengallery_media
			WHERE media_id = ?
		', $mediaId);
	}

	public function getIpIdFromMediaId($mediaId)
	{
		return $this->_getDb()->fetchOne('
			SELECT ip_id
			FROM xengallery_media
			WHERE media_id = ?
		', $mediaId);
	}
	
	/**
	 * Gets media records specified by their IDs
	 *
	 * @param array $mediaIds
	 *
	 * @return array
	 */
	public function getMediaByIds($mediaIds, array $fetchOptions = array(), array $conditions = array())
	{
		$db = $this->_getDb();
		$joinOptions = $this->prepareMediaFetchOptions($fetchOptions, $conditions);

		return $this->fetchAllKeyed('
			SELECT media.*
				' . $joinOptions['selectFields'] . '
			FROM xengallery_media AS media
				' . $joinOptions['joinTables'] . '
			WHERE media.media_id IN (' . $db->quote($mediaIds) . ')
		', 'media_id');
	}	
	
	/**
	 * Gets a single media record specified by its ID
	 *
	 * @param integer $mediaId
	 *
	 * @return array
	 */
	public function getMedia(array $conditions = array(), array $fetchOptions = array())
	{
		$whereClause = $this->prepareMediaConditions($conditions, $fetchOptions);

		$joinOptions = $this->prepareMediaFetchOptions($fetchOptions, $conditions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$sqlClauses = $this->prepareMediaFetchOptions($fetchOptions, $conditions);

		$media = $this->fetchAllKeyed($this->limitQueryResults('
			SELECT media.*
				' . $joinOptions['selectFields'] . '
			FROM xengallery_media AS media
				' . $joinOptions['joinTables'] . '
				WHERE ' . $whereClause . '
				' . $sqlClauses['orderClause'] . '			
			', $limitOptions['limit'], $limitOptions['offset']
		), 'media_id');
		
		return $media;
	}

	public function getMediaForBbCode(array $conditions = array(), array $fetchOptions = array())
	{
		$whereClause = $this->prepareMediaConditions($conditions, $fetchOptions);
		$joinOptions = $this->prepareMediaFetchOptions($fetchOptions, $conditions);

		$db = $this->_getDb();
		$media = $db->fetchAll('
			SELECT media.*
				' . $joinOptions['selectFields'] . '
			FROM xengallery_media AS media
				' . $joinOptions['joinTables'] . '
			WHERE ' . $whereClause
		);

		return $media;
	}

	public function getNextPrevMedia($id, $containerId, $containerType = 'category', $type = 'prev', $limit = 0, array $conditions = array(), array $fetchOptions = array(), $customOrder = false)
	{
		$key = 'media_id';
		if ($customOrder === false)
		{
			if ($type == 'prev')
			{
				$operator = '>';
				$orderClause = 'ORDER BY media.media_id ASC';
			}
			else
			{
				$operator = '<';
				$orderClause = 'ORDER BY media.media_id DESC';
			}

		}
		else
		{
			$key = 'position';
			if ($type == 'prev')
			{
				$operator = '<';
				$orderClause = 'ORDER BY media.position DESC';
			}
			else
			{
				$operator = '>';
				$orderClause = 'ORDER BY media.position ASC';
			}
		}

		$whereClause = $this->prepareMediaConditions($conditions, $fetchOptions);
		$joinOptions = $this->prepareMediaFetchOptions($fetchOptions, $conditions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
			SELECT media.media_id, media.media_title, media.media_type, media.last_edit_date,
			media.media_description, media.user_id, media.username, media.position, media.media_date
				' . $joinOptions['selectFields'] . '
			FROM xengallery_media AS media
			' . $joinOptions['joinTables'] . '
			WHERE media.' . $key . ' ' . $operator . ' ?
				AND media.' . $containerType . '_id = ?
				AND ' . $whereClause . '
				' . $orderClause . '
			', $limit, 0),
			$id, array($id, $containerId)
		);
	}
	
	public function getMediaIdByAttachmentId($attachmentId)
	{
 		return $this->_getDb()->fetchOne('
 			SELECT media_id
 			FROM xengallery_media
 			WHERE attachment_id = ?
 		', $attachmentId);
	}
	
	public function getMediaForAlbumCache($albumId)
	{
		$db = $this->_getDb();

		$limit = XenForo_Application::getOptions()->xengalleryThumbnailLimit;

		$media = $this->fetchAllKeyed($db->limit('
			SELECT
				media.media_id, media.media_type, media.media_tag,
				media.album_id, media.attachment_id, media.last_edit_date,
				attachment.data_id, attachment_data.thumbnail_width,
				attachment_data.data_id, attachment_data.file_hash
			FROM xengallery_media AS media
			LEFT JOIN xf_attachment AS attachment ON
				(attachment.attachment_id 	= media.attachment_id)
			LEFT JOIN xf_attachment_data AS attachment_data ON
				(attachment_data.data_id = attachment.data_id)
			WHERE media.album_id = ?
			AND media.media_state = \'visible\'
			ORDER BY RAND()
		', $limit), 'media_id', $albumId);
		
		foreach ($media AS &$item)
		{
			$item['thumbnailUrl'] = $this->getMediaThumbnailUrl($item);
		}
		
		return $media;
	}

	public function getMediaForAlbumCacheByMediaIds(array $mediaIds)
	{
		$db = $this->_getDb();

		$media = $this->fetchAllKeyed('
			SELECT
				media.media_id, media.media_type, media.media_tag,
				media.album_id, media.attachment_id, media.last_edit_date,
				attachment.data_id, attachment_data.thumbnail_width,
				attachment_data.data_id, attachment_data.file_hash
			FROM xengallery_media AS media
			LEFT JOIN xf_attachment AS attachment ON
				(attachment.attachment_id 	= media.attachment_id)
			LEFT JOIN xf_attachment_data AS attachment_data ON
				(attachment_data.data_id = attachment.data_id)
			WHERE media.media_id IN(' . $db->quote($mediaIds) . ')
		', 'media_id');

		foreach ($media AS &$item)
		{
			$item['thumbnailUrl'] = $this->getMediaThumbnailUrl($item);
		}

		return $media;
	}
	
	public function countMedia(array $conditions = array(), $fetchOptions = array())
	{
		$whereClause = $this->prepareMediaConditions($conditions, $fetchOptions);

		$joinOptions = $this->prepareMediaFetchOptions($fetchOptions, $conditions);
				
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xengallery_media AS media
			' . $joinOptions['joinTables'] . '
			WHERE ' . $whereClause
		);
	}

	public function getMaxMediaId($conditions = array(), $fetchOptions = array())
	{
		$whereClause = $this->prepareMediaConditions($conditions, $fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT MAX(media_id)
			FROM xengallery_media AS media
			WHERE ' . $whereClause
		);
	}

	public function getMediaHomeCutOff()
	{
		$cutOff = XenForo_Application::getOptions()->xengalleryMediaHomeCutOff;
		$time = XenForo_Application::$time;

		if (!$cutOff)
		{
			return false;
		}

		return $time - ($cutOff * 86400);
	}

	public function canBypassMediaPrivacy($viewingUser = array())
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewOverride'))
		{
			return true;
		}

		return false;
	}

	public function calculateMediaDiskUsage()
	{
		return $this->_getDb()->fetchOne('
			SELECT SUM(attdata.file_size)
			FROM xf_attachment AS attachment
			INNER JOIN xf_attachment_data AS attdata ON
				(attachment.data_id = attdata.data_id)
			LEFT JOIN xengallery_media AS media ON
				(attachment.attachment_id = media.attachment_id)
			LEFT JOIN xengallery_album AS album ON
				(media.album_id = album.album_id)
			WHERE attachment.content_type = \'xengallery_media\'
				AND media.media_state = \'visible\'
				AND IF(media.album_id > 0, album.album_state = \'visible\', 1=1)
		');
	}
	
	public function getTopContributors($limit)
	{
		return $this->_getDb()->fetchAll('
			SELECT user.user_id, user.username,
				user.xengallery_media_count, user.avatar_date,
				user.gravatar, user.avatar_width,
				user.avatar_height, user.display_style_group_id
			FROM xf_user AS user
			WHERE user.xengallery_media_count > 0
				AND user.is_banned = 0
			ORDER BY user.xengallery_media_count DESC
			LIMIT ?
		', $limit);
	}
	
	/**
	 * Gets media IDs in the specified range. The IDs returned will be those immediately
	 * after the "start" value (not including the start), up to the specified limit.
	 *
	 * @param integer $start IDs greater than this will be returned
	 * @param integer $limit Number of media items to return
	 *
	 * @return array List of IDs
	 */
	public function getMediaIdsInRange($start, $limit, $mediaType = 'image_upload')
	{
		$db = $this->_getDb();
		
		$mediaTypes = array(
			'image_upload',
			'video_upload',
			'video_embed'
		);
		
		if ($mediaType == 'all')
		{
			return $db->fetchCol($db->limit('
				SELECT media_id
				FROM xengallery_media
				WHERE media_id > ?
				AND media_type IN (' . $db->quote($mediaTypes) .')
				ORDER BY media_id
			', $limit), $start);
		}
		else
		{
			return $db->fetchCol($db->limit('
				SELECT media_id
				FROM xengallery_media
				WHERE media_id > ?
				AND media_type = ?
				ORDER BY media_id
			', $limit), array($start, $mediaType));
		}
	}

	public function getTagIdsInRange($start, $limit)
	{
		$db = $this->_getDb();

		return $db->fetchCol($db->limit('
			SELECT tag_id
			FROM xengallery_content_tag
			WHERE tag_id > ?
			ORDER BY tag_id
		', $limit), $start);
	}

	public function getPostIdsInRangeContaining($start, $limit, $containing = '')
	{
		$db = $this->_getDb();

		return $db->fetchCol($db->limit('
			SELECT post_id
			FROM xf_post
			WHERE post_id > ?
			AND message LIKE (' . XenForo_Db::quoteLike($containing, 'lr', $db) . ')
			ORDER BY post_id
		', $limit), $start);
	}

	/**
	 * Gets the IDs of threads that the specified user has not read. Doesn't not work for guests.
	 * Doesn't include deleted or moderated.
	 *
	 * @param integer $userId
	 * @param array $fetchOptions Fetching options
	 *
	 * @return array List of thread IDs
	 */
	public function getUnviewedMediaIds($userId = '', array $fetchOptions = array(), $viewingUser = array())
	{
		if (!$userId)
		{
			return array();
		}

		$containerWhereClause = '';
		if (!empty($fetchOptions['category_id']))
		{
			$containerWhereClause = 'AND category.category_id = ' . $fetchOptions['category_id'];
		}

		if (!empty($fetchOptions['album_id']))
		{
			$containerWhereClause = 'AND album.album_id = ' . $fetchOptions['album_id'];
		}

		$db = $this->_getDb();

		$viewingUser = $this->standardizeViewingUserReference($viewingUser);
		$privacyWhereClause = '1=1';
		$albumViewClause = ' AND 1=1';
		if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewOverride'))
		{
			$categoryClause = '';
			if (!empty($fetchOptions['viewCategoryIds']))
			{
				$categoryClause = 'OR IF(media.category_id > 0, media.category_id IN (' . $db->quote($fetchOptions['viewCategoryIds']) . '), NULL)';
			}

			if (isset($fetchOptions['viewAlbums']))
			{
				if (!$fetchOptions['viewAlbums'])
				{
					$albumViewClause = 'AND media.album_id = 0';
				}
			}

			$privacyWhereClause = '
				private.private_user_id IS NOT NULL
					OR shared.shared_user_id IS NOT NULL
					OR media.media_privacy = \'public\'
					OR media.media_privacy = \'members\'
					' . $categoryClause;

		}

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$autoReadDate = XenForo_Application::$time - (XenForo_Application::getOptions()->readMarkingDataLifetime * 86400);

		return $db->fetchCol($this->limitQueryResults(
			'
				SELECT media.media_id
				FROM xengallery_media AS media
				LEFT JOIN xengallery_media_user_view AS media_view ON
					(media_view.media_id = media.media_id AND media_view.user_id = ?
					AND media_view.media_view_date >= media.last_comment_date)
				LEFT JOIN xengallery_album AS album ON
					(album.album_id = media.album_id)
				LEFT JOIN xengallery_category AS category ON
					(category.category_id = media.category_id)
				LEFT JOIN xengallery_shared_map AS shared ON
					(shared.album_id = media.album_id AND shared.shared_user_id = ' . $db->quote($userId) . ')
				LEFT JOIN xengallery_private_map AS private ON
					(private.album_id = media.album_id AND private.private_user_id = ' . $db->quote($userId) . ')					
				WHERE (' . $privacyWhereClause . ')
					AND media_view.media_view_date IS NULL
					AND media.media_date > ?
					AND IF (media.imported, media.last_comment_date > media.imported, 1=1)
					AND IF (media.album_id > 0, album.album_state = \'visible\', 1=1)
					AND media.media_state = \'visible\'
			' . $containerWhereClause .
			$albumViewClause . '
				ORDER BY media.media_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), array($userId, $autoReadDate));
	}
	
	/**
	 * Marks the given media as viewed.
	 *
	 * @param array $media Media info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if marked as viewed
	 */
	public function markMediaViewed(array $media, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$userId = $viewingUser['user_id'];
		if (!$userId)
		{
			return false;
		}

		if (!array_key_exists('media_view_date', $media))
		{
			$media['media_view_date'] = $this->getUserMediaViewDate($userId, $media['media_id']);
		}

		$this->_getDb()->query('
			INSERT INTO xengallery_media_user_view
				(user_id, media_id, media_view_date)
			VALUES
				(?, ?, ?)
			ON DUPLICATE KEY UPDATE media_view_date = VALUES(media_view_date)
		', array($userId, $media['media_id'], XenForo_Application::$time));

		if (XenForo_Application::getOptions()->xengalleryUnviewedCounter['enabled'] && XenForo_Application::isRegistered('session'))
		{
			$session = XenForo_Application::get('session');
			$mediaUnviewed = $session->get('mediaUnviewed');

			if (isset($mediaUnviewed['unviewed']))
			{
				unset ($mediaUnviewed['unviewed'][$media['media_id']]);
			}
			$session->set('mediaUnviewed', $mediaUnviewed);
		}

		return true;
	}
	
	/**
	 * Get the time when a user viewed the given media.
	 *
	 * @param integer $userId
	 * @param integer $mediaId
	 *
	 * @return integer|null Null if guest; timestamp otherwise
	 */
	public function getUserMediaViewDate($userId, $mediaId)
	{
		if (!$userId)
		{
			return null;
		}

		$readDate = $this->_getDb()->fetchOne('
			SELECT media_view_date
			FROM xengallery_media_user_view
			WHERE user_id = ?
				AND media_id = ?
		', array($userId, $mediaId));

		$autoReadDate = XenForo_Application::$time - (XenForo_Application::getOptions()->readMarkingDataLifetime * 86400);
		return max($readDate, $autoReadDate);
	}

	/**
	 * Gets a list of BB Code Media Sites as options (for checkbox/multi-select usage).
	 *
	 * @param string|array $selectedGroupIds Array or comma delimited list
	 *
	 * @return array
	 */
	public function getUserGroupOptions($selectedMediaSiteIds)
	{
		if (!is_array($selectedMediaSiteIds))
		{
			$selectedMediaSiteIds = ($selectedMediaSiteIds ? explode(',', $selectedMediaSiteIds) : array());
		}

		$mediaSites = array();
		foreach ($this->getModelFromCache('XenForo_Model_BbCode')->getAllBbCodeMediaSites() AS $mediaSite)
		{
			$mediaSites[] = array(
				'label' => $mediaSite['site_title'],
				'value' => $mediaSite['media_site_id'],
				'selected' => in_array($mediaSite['media_site_id'], $selectedMediaSiteIds)
			);
		}

		return $mediaSites;
	}
	
	public function rebuildUserMediaCounts(array $userIds)
	{
		if (!is_array($userIds))
		{
			return false;
		}
		
		$db = $this->_getDb();
		
		XenForo_Db::beginTransaction($db);
		
		foreach ($userIds AS $userId)
		{
			$mediaCount = $this->countMedia(
				array('user_id' => $userId),
				array('join' => self::FETCH_ALBUM)
			);
			
			$db->update('xf_user', array('xengallery_media_count' => $mediaCount), 'user_id = ' . $db->quote($userId));
		}
		
		XenForo_Db::commit($db);
		
		return true;
	}

	public function rebuildUserMediaQuota(array $userIds)
	{
		if (!is_array($userIds))
		{
			return false;
		}

		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		foreach ($userIds AS $userId)
		{
			$diskUsage = $this->calculateMediaDiskUsageForUser($userId);

			$db->update('xf_user', array('xengallery_media_quota' => $diskUsage ? $diskUsage : 0), 'user_id = ' . $db->quote($userId));
		}

		XenForo_Db::commit($db);

		return true;
	}

	public function calculateMediaDiskUsageForUser($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT SUM(attdata.file_size)
			FROM xf_attachment AS attachment
			INNER JOIN xf_attachment_data AS attdata ON
				(attachment.data_id = attdata.data_id)
			LEFT JOIN xengallery_media AS media ON
				(attachment.attachment_id = media.attachment_id)
			LEFT JOIN xengallery_album AS album ON
				(media.album_id = album.album_id)
			WHERE attachment.content_type = \'xengallery_media\'
				AND media.media_state = \'visible\'
				AND IF(media.album_id > 0, album.album_state = \'visible\', 1=1)
				AND media.user_id = ?
		', $userId);
	}
	
	/**
	 * Gets the average rating based on the sum and count stored.
	 *
	 * @param integer $sum
	 * @param integer $count
	 * @param boolean $round If true, return rating to the nearest 0.5, otherwise full float.
	 *
	 * @return float
	 */
	public function getRatingAverage($sum, $count, $round = false)
	{
		if ($count == 0)
		{
			return 0;
		}

		$average = $sum / $count;

		if ($round)
		{
			$average = round($average / 0.5, 0) * 0.5;
		}

		return $average;
	}

	public function getWeightedRating($count, $sum)
	{
		return (self::$voteThreshold * self::$averageVote + $sum) / (self::$voteThreshold + $count);
	}

	public function generateRandomMediaCache()
	{
		$options = XenForo_Application::getOptions();

		$categoryIds = $options->xengalleryRecentMediaCategories;
		$showAlbums = (bool)$options->xengallerRecentMediaAlbums;

		$randFunction = 'rand';
		if (function_exists('mt_rand'))
		{
			$randFunction = 'mt_rand';
		}

		$limit = 5;
		$iterations = 20;

		$conditions = array(
			'category_id' => $categoryIds,
			'mediaBlock' => $showAlbums,
			'skipVisibility' => true
		);

		$fetchOptions = array(
			'limit' => $limit
		);

		$maxId = (int)$this->getMaxMediaId($conditions);

		$mediaIds = array();
		while ($iterations > 0)
		{
			$iterations--;

			$conditions['media_id_gt'] = $randFunction(0, max(0, $maxId - $limit));

			$media = $this->getMedia($conditions, $fetchOptions);
			$mediaIds = array_merge($mediaIds, array_keys($media));
		}

		return array_unique($mediaIds);
	}
	
	/**
	 * Logs the viewing of a media item.
	 *
	 * @param integer $mediaId
	 */
	public function logMediaView($mediaId)
	{
		$this->_getDb()->query('
			INSERT ' . (XenForo_Application::getOptions()->enableInsertDelayed ? 'DELAYED' : '') . ' INTO xengallery_media_view
				(media_id)
			VALUES
				(?)
		', $mediaId);
	}

	/**
	 * Updates media views in bulk.
	 */
	public function updateMediaViews()
	{
		$db = $this->_getDb();

		$updates = $db->fetchPairs('
			SELECT media_id, COUNT(*)
			FROM xengallery_media_view
			GROUP BY media_id
		');

		XenForo_Db::beginTransaction($db);

		$db->query('TRUNCATE TABLE xengallery_media_view');

		foreach ($updates AS $mediaId => $views)
		{
			$db->query('
				UPDATE xengallery_media SET
					media_view_count = media_view_count + ?
				WHERE media_id = ?
			', array($views, $mediaId));
		}

		XenForo_Db::commit($db);
	}

	public function rotateMedia(array $media, $rotation = 90)
	{
		/** @var $watermarkModel XenGallery_Model_Watermark */
		$watermarkModel = $this->getModelFromCache('XenGallery_Model_Watermark');

		if ($media['watermark_id'])
		{
			$watermarkModel->removeWatermarkFromImage($media);
		}

		$originalPath = $this->getOriginalDataFilePath($media, true);
		$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');

		$image = new XenGallery_Helper_Image($originalPath);
		$rotated = $image->rotate($rotation);

		if ($rotated)
		{
			$this->deleteTagsByMediaId($media['media_id']);

			$image->saveToPath($tempFile);

			$imageInfo = $image->getImageInfo();
			$imageInfo['file_hash'] = $image->getFileHash();
			$media['file_hash'] = $imageInfo['file_hash'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');
			$dw->setExistingData($media['data_id']);
			$dw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_FILE, $tempFile);
			$dw->save();

			/** @var $watermarkModel XenGallery_Model_Watermark */
			$watermarkModel = $this->getModelFromCache('XenGallery_Model_Watermark');
			if (!$watermarkModel->canBypassWatermark() || $media['watermark_id'])
			{
				$watermarked = $watermarkModel->addWatermarkToImage($media);
				if ($watermarked)
				{
					$imageInfo = $watermarked;
					$media['file_hash'] = $imageInfo['file_hash'];
				}
			}

			return $imageInfo;
		}

		return false;
	}

	public function flipMedia(array $media, $direction = 'horizontal')
	{
		/** @var $watermarkModel XenGallery_Model_Watermark */
		$watermarkModel = $this->getModelFromCache('XenGallery_Model_Watermark');

		if ($media['watermark_id'])
		{
			$watermarkModel->removeWatermarkFromImage($media);
		}

		$originalPath = $this->getOriginalDataFilePath($media, true);
		$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');

		$image = new XenGallery_Helper_Image($originalPath);
		$flipped = $image->flip($direction);

		if ($flipped)
		{
			$this->deleteTagsByMediaId($media['media_id']);

			$image->saveToPath($tempFile);

			$imageInfo = $image->getImageInfo();
			$imageInfo['file_hash'] = $image->getFileHash();
			$media['file_hash'] = $imageInfo['file_hash'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');
			$dw->setExistingData($media['data_id']);
			$dw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_FILE, $tempFile);
			$dw->save();

			if (!$watermarkModel->canBypassWatermark() || $media['watermark_id'])
			{
				$watermarked = $watermarkModel->addWatermarkToImage($media);
				if ($watermarked)
				{
					$imageInfo = $watermarked;
					$media['file_hash'] = $imageInfo['file_hash'];
				}
			}

			return $imageInfo;
		}

		return false;
	}

	public function cropMedia(array $media, array $cropInfo)
	{
		/** @var $watermarkModel XenGallery_Model_Watermark */
		$watermarkModel = $this->getModelFromCache('XenGallery_Model_Watermark');

		if ($media['watermark_id'])
		{
			$watermarkModel->removeWatermarkFromImage($media);
		}

		$originalPath = $this->getOriginalDataFilePath($media, true);
		$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');

		$image = new XenGallery_Helper_Image($originalPath);
		$cropped = $image->cropExact($cropInfo['crop_x1'], $cropInfo['crop_y1'], $cropInfo['crop_width'], $cropInfo['crop_height']);

		if ($cropped)
		{
			$this->deleteTagsByMediaId($media['media_id']);

			$image->saveToPath($tempFile);

			$imageInfo = $image->getImageInfo();
			$imageInfo['file_hash'] = $image->getFileHash();
			$media['file_hash'] = $imageInfo['file_hash'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');
			$dw->setExistingData($media['data_id']);
			$dw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_FILE, $tempFile);
			$dw->save();

			if (!$watermarkModel->canBypassWatermark() || $media['watermark_id'])
			{
				$imageInfo = $watermarkModel->addWatermarkToImage($media);
				$media['file_hash'] = $imageInfo['file_hash'];
			}

			return $imageInfo;
		}

		return false;
	}

	public function deleteTagsByMediaId($mediaId)
	{
		$db = $this->_getDb();

		return $db->delete('xengallery_user_tag', 'media_id = ' . $db->quote($mediaId));
	}

	public function uploadMediaThumbnail(XenForo_Upload $upload, array $media)
	{
		if (!$media)
		{
			throw new XenForo_Exception('Missing media record.');
		}

		if (!$upload->isValid())
		{
			throw new XenForo_Exception($upload->getErrors(), true);
		}

		if (!$upload->isImage())
		{
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		};

		$baseTempFile = $upload->getTempFile();

		$imageType = $upload->getImageInfoField('type');
		$width = $upload->getImageInfoField('width');
		$height = $upload->getImageInfoField('height');

		return $this->processMediaThumbnail($media, $baseTempFile, $imageType, $width, $height);
	}

	public function deleteMediaThumbnail(array $media)
	{
		if ($media['media_type'] == 'image_upload' || $media['media_type'] == 'video_upload')
		{
			$media['thumbnail_date'] = 0;

			return $this->rebuildThumbnail($media, $media, false);
		}
		else if ($media['media_type'] == 'video_embed')
		{
			preg_match('/\[media=(.*?)\](.*?)\[\/media\]/is', $media['media_tag'], $parts);

			$this->getVideoThumbnailUrlFromParts($parts, true);

			/** @var $mediaWriter XenGallery_DataWriter_Media */
			$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');

			$mediaWriter->setExistingData($media['media_id']);

			$time = XenForo_Application::$time;

			$mediaWriter->bulkSet(array(
				'last_edit_date' => $time,
				'thumbnail_date' => 0
			));

			$mediaWriter->save();
		}
	}

	public function processMediaThumbnail(array $media, $fileName, $imageType = false, $width = false, $height = false)
	{
		if (!$imageType || !$width || !$height)
		{
			$imageInfo = getimagesize($fileName);
			if (!$imageInfo)
			{
				throw new XenForo_Exception('Non-image passed in to mediaThumbnail');
			}
			$imageType = $imageInfo[2];
		}

		if (!in_array($imageType, array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG)))
		{
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		}

		$options = XenForo_Application::getOptions();

		if ($media['media_type'] == 'image_upload' || $media['media_type'] == 'video_upload')
		{
			$thumbFile = $this->getMediaThumbnailFilePath($media);
		}
		else if ($media['media_type'] == 'video_embed')
		{
			$thumbFile = $this->getMediaThumbnailFilePath($media['media_tag']);
			$thumbFile = $thumbFile[0];
		}

		XenForo_Helper_File::createDirectory(dirname($thumbFile));

		$thumbImage = new XenGallery_Helper_Image($fileName);
		if ($thumbImage)
		{
			$thumbImage->resize(
				$options->xengalleryThumbnailDimension['width'],
				$options->xengalleryThumbnailDimension['height'], 'crop'
			);

			$thumbnailed = $thumbImage->saveToPath($thumbFile);

			if ($thumbnailed)
			{
				@unlink($fileName);
				$writeSuccess = true;
			}
			else
			{
				$writeSuccess = false;
			}

			if ($writeSuccess)
			{
				$mediaDw = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
				$mediaDw->setExistingData($media);

				$time = XenForo_Application::$time;

				$mediaDw->bulkSet(array(
					'last_edit_date' => $time,
					'thumbnail_date' => $time
				));

				$mediaDw->save();
			}

			return $writeSuccess;
		}

		return false;
	}

	public function prepareMedia($media)
	{
		if (!empty($media['thumbnail_width']))
		{
			if ($media['thumbnail_width'])
			{
				$media['thumbnailUrl'] = $this->getMediaThumbnailUrl($media);
			}
			else
			{
				$media['thumbnailUrl'] = '';
			}

			$media['deleteUrl'] = XenForo_Link::buildPublicLink('attachments/delete', $media);
			$media['viewUrl'] = XenForo_Link::buildPublicLink('attachments', $media);

			$media['extension'] = strtolower(substr(strrchr($media['filename'], '.'), 1));
		}
		
		$this->standardizeViewingUserReference($viewingUser);
		
		if (isset($media['user_id']))
		{
			$media['isIgnored'] = array_key_exists($media['user_id'], $viewingUser['ignoredUsers']);
			$media['customUserFields'] = @unserialize($media['custom_fields']);
		}
		
		if (isset($media['media_title']))
		{
			$media['media_title'] = XenForo_Helper_String::censorString($media['media_title']);
		}

		if (isset($media['media_description']))
		{
			$media['media_description'] = XenForo_Helper_String::censorString($media['media_description']);			
		}

		if (isset($media['data_id']) && isset($media['media_type']) && $media['media_type'] == 'video_upload')
		{
			$media['videoUrl'] = $this->getVideoUrl($media);
		}

		$media['mediaTag'] = false;
		if (!empty($media['media_type'])
			&& $media['media_type'] == 'video_embed'
			&& isset($media['media_tag'])
		)
		{
			preg_match('/\[media=(.*?)\](.*?)\[\/media\]/is', $media['media_tag'], $parts);
			$media['mediaSite'] = "$parts[1].$parts[2]";

			$videoThumbnail = $this->getVideoThumbnailUrlFromParts($parts);
			$media['thumbnailUrl'] = $videoThumbnail;

			if (!$videoThumbnail)
			{
				$media['noThumb'] = true;
			}
		}

		if (empty($media['username']))
		{
			$deletedUserPhrase = new XenForo_Phrase('xengallery_deleted_user');
			$media['username'] = $deletedUserPhrase->render();
		}

		$media['likeUsers'] = false;
		$media['liked'] = false;

		if (!empty($media['like_users']))
		{
			$media['likeUsers'] = @unserialize($media['like_users']);

			if (is_array($media['likeUsers']))
			{
				foreach ($media['likeUsers'] AS $likeUser)
				{
					if ($likeUser['user_id'] == $viewingUser['user_id'])
					{
						$media['liked'] = true;
					}
				}
			}
		}

		$media['canLikeMedia'] = $this->canLikeMedia($media, $null, $viewingUser);
		$media['canRateMedia'] = $this->canRateMedia($media, $null, $viewingUser);

		if (!empty($media['findNewPage']))
		{
			if (!isset($media['media_view_date']))
			{
				$media['media_view_date'] = 0;
			}
			if (!isset($media['last_comment_date']))
			{
				$media['last_comment_date'] = 0;
			}
			$media['newComment'] = ($media['last_comment_date'] > $media['media_view_date']);
		}

		if (!empty($media['tags']))
		{
			$media['tagsList'] = $media['tags'] ? @unserialize($media['tags']) : array();
		}

		return $media;
	}
	
	public function prepareMediaItems(array $media)
	{
		foreach ($media AS &$_media)
		{
			$_media = $this->prepareMedia($_media);
		}
		
		return $media;
	}

	/**
	 * Attempts to update any instances of an old username in like_users with a new username
	 *
	 * @param integer $oldUserId
	 * @param integer $newUserId
	 * @param string $oldUsername
	 * @param string $newUsername
	 */
	public function batchUpdateLikeUser($oldUserId, $newUserId, $oldUsername, $newUsername)
	{
		$db = $this->_getDb();

		// note that xf_liked_content should have already been updated with $newUserId

		$db->query('
			UPDATE (
				SELECT content_id FROM xf_liked_content
				WHERE content_type = \'xengallery_media\'
				AND like_user_id = ?
			) AS temp
			INNER JOIN xengallery_media AS media ON (media.media_id = temp.content_id)
			SET like_users = REPLACE(like_users, ' .
				$db->quote('i:' . $oldUserId . ';s:8:"username";s:' . strlen($oldUsername) . ':"' . $oldUsername . '";') . ', ' .
				$db->quote('i:' . $newUserId . ';s:8:"username";s:' . strlen($newUsername) . ':"' . $newUsername . '";') . ')
		', $newUserId);
	}

	public function prepareMediaExifData(array $media)
	{
		$media['exifData'] = @unserialize($media['media_exif_data_cache']);
		if (!is_array($media['exifData']))
		{
			$media['exifData'] = array();
		}

		if (isset($media['exifData']['Flash']))
		{
			$media['exifData']['Flash']['value'] = new XenForo_Phrase('xengallery_exif_flash_' . $media['exifData']['Flash']['value']);
		}

		foreach ($media['exifData'] AS $key => &$exifData)
		{
			$cleanKey = preg_replace('/[^a-zA-Z0-9_]+/', '', strtolower($key));
			$exifData['title'] = new XenForo_Phrase('xengallery_exif_title_' . $cleanKey);
			if ($exifData['format'])
			{
				if (strstr($exifData['format'], 'xen:calc'))
				{
					try
					{
						$compiler = new XenForo_Template_Compiler();
						$safeValue = $compiler->compileFunction('calc', array($exifData['value']), array());
						$safeValue = eval("return ($safeValue);");

						$exifData['format'] = preg_replace('/{xen:calc(.*?)\'}/i', '{value}', $exifData['format']);

						$exifData['value'] = $safeValue;
						$exifData['value'] = str_replace('{value}', $exifData['value'], $exifData['format']);
					}
					catch(Exception $e) {}

					continue;
				}

				if (strstr($exifData['format'], 'xen:number'))
				{
					$exifData['isNumber'] = true;

					if (strstr($exifData['format'], 'size'))
					{
						$exifData['isSize'] = true;
					}

					continue;
				}

				if (is_array($exifData['value']))
				{
					$exifData['value'] = @json_encode($exifData['value']);
					continue;
				}

				$exifData['value'] = str_replace('{value}', $exifData['value'], $exifData['format']);
			}
		}

		$media['exifDataFull'] = @json_decode($media['media_exif_data_cache_full'], true);
		if (!is_array($media['exifDataFull']))
		{
			$media['exifDataFull'] = array();
		}

		return $media;
	}

	public function setMediaPosition(array $mediaIds)
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		foreach ($mediaIds AS $mediaId => $position)
		{
			$db->update('xengallery_media', array('position' => $position), 'media_id = ' . $db->quote($mediaId));
		}

		XenForo_Db::commit($db);

		return true;
	}

	public function prepareMediaCustomFields(array $media, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$media['customFields'] = @unserialize($media['custom_media_fields']);
		if (!is_array($media['customFields']))
		{
			$media['customFields'] = array();
		}

		$fieldCacheKey = 'categoryFieldCache';
		if (!empty($media['album_id']))
		{
			$fieldCacheKey = 'albumFieldCache';
		}

		$media['showExtraInfoTab'] = false;

		if (!isset($media[$fieldCacheKey]))
		{
			$media[$fieldCacheKey] = @unserialize($media['field_cache']);
			if (!is_array($media[$fieldCacheKey]))
			{
				$media[$fieldCacheKey] = array();
			}
		}
		if (!empty($media[$fieldCacheKey]['extra_tab']))
		{
			foreach ($media[$fieldCacheKey]['extra_tab'] AS $fieldId)
			{
				if (isset($media['customFields'][$fieldId]) && $media['customFields'][$fieldId] !== '')
				{
					$media['showExtraInfoTab'] = true;
					break;
				}
			}
		}

		$media['customFieldTabs'] = array();
		if (!empty($media[$fieldCacheKey]['new_tab']))
		{
			foreach ($media[$fieldCacheKey]['new_tab'] AS $fieldId)
			{
				if (isset($media['customFields'][$fieldId])
					&& (
						(is_string($media['customFields'][$fieldId]) && $media['customFields'][$fieldId] !== '')
						|| (is_array($media['customFields'][$fieldId]) && count($media['customFields'][$fieldId]))
					)
				)
				{
					$media['customFieldTabs'][] = $fieldId;
				}
			}
		}

		return $media;
	}

	public function prepareInlineModOptions(array &$media, $userPage = false)
	{
		$mediaModOptions = array();

		foreach ($media AS &$_media)
		{
			$mediaModOptions = $mediaModOptions + $this->addInlineModOptionToMedia($_media, $_media, $userPage);
		}

		return $mediaModOptions;
	}

	/**
	 * Adds the canInlineMod value to the provided media and returns the
	 * specific list of inline mod actions that are allowed on this media.
	 *
	 * @param array $media Media info
	 * @param array $container Container (category or Album) the media is in
	 * @param array|null $viewingUser
	 *
	 * @return array List of allowed inline mod actions, format: [action] => true
	 */
	public function addInlineModOptionToMedia(array &$media, array $container, $userPage = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		/** @var $watermarkModel XenGallery_Model_Watermark */
		$watermarkModel = $this->getModelFromCache('XenGallery_Model_Watermark');

		$mediaModOptions = array();

		$canInlineMod = ($viewingUser['user_id'] &&
			($this->canApproveUnapproveMedia($errorPhraseKey, $viewingUser)
				|| $this->canDeleteMedia($media, 'soft', $errorPhraseKey, $viewingUser)
				|| $this->canEditMedia($media, $errorPhraseKey, $viewingUser)
				|| $this->canMoveMedia($media, $errorPhraseKey, $viewingUser)
				|| ($watermarkModel->canAddWatermark($media) || $watermarkModel->canRemoveWatermark($media))
			)
		);

		if ($canInlineMod)
		{
			if ($this->canApproveUnapproveMedia($errorPhraseKey, $viewingUser))
			{
				$mediaModOptions['unapprove'] = true;
				$mediaModOptions['approve'] = true;
			}
			if ($this->canDeleteMedia($media, 'soft', $errorPhraseKey, $viewingUser))
			{
				$mediaModOptions['delete'] = true;
			}
			if ($this->canDeleteMedia($media, 'soft', $errorPhraseKey, $viewingUser)
				&& ($viewingUser['is_staff']
					|| $viewingUser['is_moderator']
					|| $viewingUser['is_admin']
				)
			)
			{
				$mediaModOptions['undelete'] = true;
			}
			if ($this->canEditMedia($media, $errorPhraseKey, $viewingUser))
			{
				$mediaModOptions['edit'] = true;
			}
			if ($this->canMoveMedia($media, $errorPhraseKey, $viewingUser))
			{
				$mediaModOptions['move'] = true;
			}

			if ($watermarkModel->canAddWatermark($media) || $watermarkModel->canRemoveWatermark($media))
			{
				$mediaModOptions['addWatermark'] = true;
				$mediaModOptions['removeWatermark'] = true;
			}
		}

		$media['canInlineMod'] = (count($mediaModOptions) > 0);

		return $mediaModOptions;
	}

	/**
	 * Prepares join-related fetch options.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array Containing 'selectFields' and 'joinTables' keys.
	 */
	public function prepareMediaFetchOptions(array $fetchOptions, array $conditions = array())
	{
		$db = $this->_getDb();

		$selectFields = '';
		$joinTables = '';		
		$orderBy = '';
		
		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';

			switch ($fetchOptions['order'])
			{
				case 'rand':
					$orderBy = 'RAND()';
					break;
					
				case 'media_date':
				case 'new':
				default:
					$orderBy = 'media.media_date DESC';
					$orderBySecondary = ', media.media_id DESC';
					break;
					
				case 'media_id':
					$orderBy = 'media.media_date DESC';
					break;

				case 'sitemap_order':
					$orderBy = 'media.media_id ASC';
					break;

				case 'rating_avg':
					$orderBy = 'media.rating_avg DESC';
					$orderBySecondary = ', media.media_date DESC';
					break;

				case 'rating_weighted':
					$orderBy = 'media.rating_weighted DESC';
					$orderBySecondary = ', media.media_date DESC';
					break;
					
				case 'view_count':
					$orderBy = 'media.media_view_count DESC';
					break;
					
				case 'comment_count':
					$orderBy = 'media.comment_count DESC';
					break;	
					
				case 'rating_count':
					$orderBy = 'media.rating_count DESC';
					break;											
					
				case 'likes':
					$orderBy = 'media.likes DESC';
					break;

				case 'custom':
					$orderBy = 'media.position ASC';
					break;
			}

			$orderBy .= $orderBySecondary ? $orderBySecondary : ', media.media_date DESC, media.media_id DESC';
		}

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_ALBUM)
			{
				$selectFields .= ',
					album.*, albumviewperm.*';
				$joinTables .= '
					LEFT JOIN xengallery_album AS album ON
						(album.album_id = media.album_id)
					LEFT JOIN xengallery_album_permission as albumviewperm ON
						(album.album_id = albumviewperm.album_id AND albumviewperm.permission = \'view\')
					';
			}

			if ($fetchOptions['join'] & self::FETCH_CATEGORY)
			{
				$selectFields .= ',
					category.*';
				$joinTables .= '
					LEFT JOIN xengallery_category AS category ON
						(category.category_id = media.category_id)';
			}

			$this->standardizeViewingUserReference($viewingUser);

			if ($fetchOptions['join'] & self::FETCH_PRIVACY && $viewingUser['user_id'])
			{
				if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewOverride'))
				{
					$db = $this->_getDb();

					if (isset($conditions['privacyUserId']))
					{
						$selectFields .= ',
							shared.shared_user_id, private.private_user_id';
						$joinTables .= '
							LEFT JOIN xengallery_shared_map AS shared ON
								(shared.album_id = media.album_id AND shared.shared_user_id = ' . $db->quote($conditions['privacyUserId']) . ')
							LEFT JOIN xengallery_private_map AS private ON
								(private.album_id = media.album_id AND private.private_user_id = ' .  $db->quote($conditions['privacyUserId']) .')';
					}
				}
			}

			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= ',
					user.*, user_profile.*, IF(user.username IS NULL, media.username, user.username) AS username';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = media.user_id)
					LEFT JOIN xf_user_profile AS user_profile ON
						(user_profile.user_id = media.user_id)';
			}
			
			if ($fetchOptions['join'] & self::FETCH_USER_OPTION)
			{
				$selectFields .= ',
					user_option.*';
				$joinTables .= '
					LEFT JOIN xf_user_option AS user_option ON
						(user_option.user_id = media.user_id)';
			}

            if ($fetchOptions['join'] & self::FETCH_ATTACHMENT)
            {
				$dataColumns = XenForo_Model_Attachment::$dataColumns;
				$dataColumns = explode(', ', $dataColumns);

				if (XenForo_Application::getOptions()->currentVersionId < 1050010)
				{
					if (($filePathKey = array_search('data.file_path', $dataColumns)) !== false)
					{
						unset($dataColumns[$filePathKey]);
					}
				}

				if (($userIdKey = array_search('data.user_id', $dataColumns)) !== false)
				{
					unset($dataColumns[$userIdKey]);
				}

				$dataColumns = implode(', ', $dataColumns);

                $selectFields .= ',
                    attachment.attachment_id, attachment.data_id, attachment.attach_date,' . $dataColumns;
                $joinTables .= '
                    LEFT JOIN xf_attachment AS attachment ON
                        (attachment.content_type = \'xengallery_media\' AND attachment.attachment_id = media.attachment_id)
                    LEFT JOIN xf_attachment_data AS data ON
                        (data.data_id = attachment.data_id)';
            }
			
			if ($fetchOptions['join'] & self::FETCH_DELETION_LOG)
			{
				$selectFields .= ',
					deletion_log.delete_date, deletion_log.delete_reason,
					deletion_log.delete_user_id, deletion_log.delete_username';
				$joinTables .= '
					LEFT JOIN xf_deletion_log AS deletion_log ON
						(deletion_log.content_type = \'xengallery_media\' AND deletion_log.content_id = media.media_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_LAST_VIEW && $viewingUser['user_id'])
			{
				$selectFields .= ',
					media_view.media_view_date';
				$joinTables .= '
					LEFT JOIN xengallery_media_user_view AS media_view ON
						(media.media_id = media_view.media_id
							AND media_view.user_id = ' . $db->quote($conditions['view_user_id']) .')';
			}
		}

		if (isset($fetchOptions['watchUserId']) && $viewingUser['user_id'])
		{
			if (!empty($fetchOptions['watchUserId']))
			{
				$selectFields .= ',
					IF(media_watch.user_id IS NULL, 0, 1) AS media_is_watched';
				$joinTables .= '
					LEFT JOIN xengallery_media_watch AS media_watch
						ON (media_watch.media_id = media.media_id
						AND media_watch.user_id = ' . $this->_getDb()->quote($fetchOptions['watchUserId']) . ')';
			}
			else
			{
				$selectFields .= ',
					0 AS media_is_watched';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables,
			'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}
	
	/**
	 * Prepares a set of conditions against which to select media items.
	 *
	 * @param array $conditions List of conditions.
	 * @param array $fetchOptions The fetch options that have been provided. May be edited if criteria requires.
	 *
	 * @return string Criteria as SQL for where clause
	 */
	public function prepareMediaConditions(array $conditions, array &$fetchOptions)
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_PRIVACY)
			{
				$viewingUser = $this->standardizeViewingUserReference();

				if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewOverride'))
				{
					$userId = 0;
					if (isset($conditions['privacyUserId']))
					{
						$userId = $conditions['privacyUserId'];
					}

					$categoryClause = '';
					if (!empty($conditions['viewCategoryIds']))
					{
						$categoryClause = 'OR IF(media.category_id > 0, media.category_id IN (' . $db->quote($conditions['viewCategoryIds']) . '), NULL)';
					}

					$membersClause = '';
					if ($userId > 0)
					{
						$membersClause = 'private.private_user_id IS NOT NULL OR shared.shared_user_id IS NOT NULL OR media.media_privacy = \'members\' OR';
					}

					$sqlConditions[] = '
						' . $membersClause . '
						media.media_privacy = \'public\'
						' . $categoryClause;
				}
			}
		}

		if (!empty($conditions['container']))
		{
			switch ($conditions['container'])
			{
				case 'album':
					$sqlConditions[] = 'media.album_id > 0';
					break;

				case 'site':
					$sqlConditions[] = 'media.category_id > 0';
					break;

				default:
					break;
			}
		}

		if (!empty($conditions['type']))
		{
			switch ($conditions['type'])
			{
				case 'image_upload':
				case 'video_upload':
				case 'video_embed':
					$sqlConditions[] = 'media.media_type = ' . $db->quote($conditions['type']);
					break;

				default:
					break;
			}
		}

		if (!empty($conditions['media_type']))
		{
			if (is_array($conditions['media_type']))
			{
				$sqlConditions[] = 'media.media_type IN (' . $db->quote($conditions['media_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'media.media_type = ' . $db->quote($conditions['media_type']);
			}
		}

		if (!empty($conditions['user_id']))
		{
			if (is_array($conditions['user_id']))
			{
				$sqlConditions[] = 'media.user_id IN (' . $db->quote($conditions['user_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'media.user_id = ' . $db->quote($conditions['user_id']);
			}
		}
		
		if (!empty($conditions['album_id']))
		{
			if (is_array($conditions['album_id']))
			{
				$sqlConditions[] = 'media.album_id IN (' . $db->quote($conditions['album_id']) . ')';
			}
			else
			{
				if ($conditions['album_id'] == 'noalbums')
				{
					$sqlConditions[] = 'media.album_id = 0';
				}
				else
				{
					$sqlConditions[] = 'media.album_id = ' . $db->quote($conditions['album_id']);
				}
			}
		}

		if (!empty($conditions['category_id']))
		{
			$additionalCondition = '';
			if (!empty($conditions['mediaBlock']))
			{
				$additionalCondition = ' OR media.category_id = 0';
			}

			if (is_array($conditions['category_id']))
			{
				$sqlConditions[] = 'media.category_id IN (' . $db->quote($conditions['category_id']) . ')' . $additionalCondition;
			}
			else
			{
				if ($conditions['category_id'] == 'nocategories')
				{
					$sqlConditions[] = 'media.category_id = 0';
				}
				elseif ($conditions['category_id'] == 'all')
				{
					$sqlConditions[] = 'media.category_id > 0' . $additionalCondition;
				}
				else
				{
					$sqlConditions[] = 'media.category_id = ' . $db->quote($conditions['category_id']) . $additionalCondition;
				}
			}
		}
		
		if (!empty($conditions['media_id']))
		{
			if (is_array($conditions['media_id']))
			{
				$sqlConditions[] = 'media.media_id IN (' . $db->quote($conditions['media_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'media.media_id = ' . $db->quote($conditions['media_id']);
			}
		}

		if (!empty($conditions['media_id_gt']))
		{
			$sqlConditions[] = 'media.media_id >= ' . $db->quote($conditions['media_id_gt']);
		}

		if (!empty($conditions['media_privacy']))
		{
			if (is_array($conditions['media_privacy']))
			{
				$sqlConditions[] = 'media.media_privacy IN (' . $db->quote($conditions['media_privacy']) . ')';
			}
			else
			{
				$sqlConditions[] = 'media.media_privacy = ' . $db->quote($conditions['media_privacy']);
			}
		}

		if (!empty($conditions['newerThan']))
		{
			$sqlConditions[] = 'media.media_date >= ' . $db->quote($conditions['newerThan']);
		}

		if (isset($conditions['viewAlbums']))
		{
			if (!$conditions['viewAlbums'])
			{
				$this->standardizeViewingUserReference($viewingUser);

				if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewOverride'))
				{
					$sqlConditions[] = 'media.album_id = 0';
				}
			}
		}

		if (isset($conditions['deleted']))
		{
			$sqlConditions[] = $this->prepareStateLimitFromConditions($conditions, 'media', 'media_state');
			if (isset($fetchOptions['join']))
			{
				if ($fetchOptions['join'] & self::FETCH_ALBUM)
				{
					if ($conditions['deleted'])
					{
						$sqlConditions[] = "IF(media.album_id > 0, album.album_state IN('visible','deleted'), 1=1)";
					}
					else
					{
						$sqlConditions[] = "IF(media.album_id > 0, album.album_state = 'visible', 1=1)";
					}
				}
			}
		}
		else if (empty($conditions['skipVisibility']))
		{
			$sqlConditions[] = "media.media_state = 'visible'";

			if (isset($fetchOptions['join']))
			{
				if ($fetchOptions['join'] & self::FETCH_ALBUM)
				{
					$sqlConditions[] = "IF(media.album_id > 0, album.album_state = 'visible', 1=1)";
				}
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function canViewMedia(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'view'))
		{
			return true;
		}		
		
		$errorPhraseKey = 'xengallery_no_view_permission';
		return false;		
	}
	
	public function canViewMediaItem(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
	
		// Media can be viewed from areas outside the gallery, e.g. profile tabs. This ensures they stay appropriately hidden if they have no permission.
		if (!$this->canViewMedia($errorPhraseKey, $viewingUser))
		{
			return false;
		}
	
		if ($media['media_state'] == 'deleted' && !$this->canViewDeletedMedia($errorPhraseKey, $viewingUser))
		{
			return false;
		}
	
		if ($media['media_state'] == 'moderated' && !$this->canViewUnapprovedMedia($errorPhraseKey, $viewingUser))
		{
			return false;
		}
	
		return true;
	}
	
	public function canViewDeletedMedia(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewDeleted'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_view_deleted_media_permission';
		return false;		
	}

	public function canWatchMedia(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		return ($viewingUser['user_id'] ? true : false);
	}
	
	public function canLikeMedia(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (isset($media['user_id']))
		{
			if ($media['user_id'] == $viewingUser['user_id'])
			{
				$errorPhraseKey = 'xengallery_you_cannot_like_your_own_media';
				return false;
			}
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'like'))
		{
			return true;
		}
		
		$errorPhraseKey = 'xengallery_no_like_permission';
		return false;		
	}
	
	public function canDeleteMedia(array $media, $type = 'soft', &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($type != 'soft' && !XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'hardDeleteAny'))
		{
			// fail immediately on hard delete without permission
			return false;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteAny'))
		{
			return true;
		}
		else if ($media['user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'delete'))
		{
			$editLimit = XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editOwnMediaTimeLimit');

			if ($editLimit !== 0)
			{
				if ($editLimit != -1 && (!$editLimit || $media['media_date'] < XenForo_Application::$time - 60 * $editLimit))
				{
					$errorPhraseKey = array('xengallery_media_delete_limit', 'minutes' => $editLimit);
					return false;
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Determines if the user can edit tags on the media.
	 *
	 * @param array|null $media Info about the media item (null when creating adding media)
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canEditTags(array $media = null, &$errorPhraseKey = '', array $viewingUser = null)
	{
		if (!XenForo_Application::getOptions()->enableTagging)
		{
			return false;
		}

		$this->standardizeViewingUserReference($viewingUser);

		// if no media item, assume the media will be owned by this person
		if (!$media || $media['user_id'] == $viewingUser['user_id'])
		{
			if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'tagOwnMedia'))
			{
				return true;
			}
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'tagAnyMedia'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_content_tag_permission';
		return false;
	}
	
	public function canEditMedia(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editAny'))
		{
			return true;
		}
		
		if ($media['user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'edit'))
		{
			$editLimit = XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editOwnMediaTimeLimit');

			if ($editLimit !== 0)
			{
				if ($editLimit != -1 && (!$editLimit || $media['media_date'] < XenForo_Application::$time - 60 * $editLimit))
				{
					$errorPhraseKey = array('xengallery_media_edit_limit', 'minutes' => $editLimit);
					return false;
				}
			}

			return true;
		}
		
		$errorPhraseKey = 'xengallery_no_edit_permission';
		return false;
	}

	public function canMoveMedia(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($media['user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'move'))
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'moveAny'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_move_permission';
		return false;
	}

	public function canMoveMediaToAnyAlbum(array $media = array(), &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($media)
		{
			if ($this->canMoveMedia($media, $null, $viewingUser) && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'moveToAnyAlbum'))
			{
				return true;
			}
		}
		else
		{
			if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'moveToAnyAlbum'))
			{
				return true;
			}
		}

		$errorPhraseKey = 'xengallery_no_move_permission';
		return false;
	}

	public function canEditEmbedUrl(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$errorPhraseKey = 'xengallery_no_edit_url_permission';
		if ($media['media_type'] != 'video_embed' || !$media['media_id'])
		{
			return false;
		}

		if ($media['user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editUrl'))
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editUrlAny'))
		{
			return true;
		}

		return false;
	}

	/**
	 * Checks if a user can generally approve or unapprove media.
	 * @param string $errorPhraseKey
	 * @param array $viewingUser
	 * @return bool|false|true
	 */
	public function canApproveUnapproveMedia(&$errorPhraseKey ='', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'approveUnapproveMedia');
	}

	public function canApproveMedia(array $media, &$errorPhraseKey ='', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if (!$viewingUser['user_id'] || $media['media_state'] != 'moderated')
		{
			return false;
		}
		
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'approveUnapproveMedia');
	}	
	
	public function canUnapproveMedia(array $media, &$errorPhraseKey ='', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if (!$viewingUser['user_id'] || $media['media_state'] != 'visible')
		{
			return false;
		}
		
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'approveUnapproveMedia');
	}
	
	public function canViewUnapprovedMedia(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if (!$viewingUser['user_id'])
		{
			return false;
		}		
		
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'approveUnapproveMedia');
	}

	public function canViewRatings(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewRatings'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_view_rate_permission';
		return false;
	}
	
	public function canRateMedia(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if (isset($media['user_id']))
		{
			if ($media['user_id'] == $viewingUser['user_id'])
			{
				$errorPhraseKey = 'xengallery_no_rate_media_by_self';
				return false;
			}
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'rate'))
		{
			return true;
		}
		
		$errorPhraseKey = 'xengallery_no_rate_permission';
		return false;
	}

	public function canWarnMediaItem(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		if (!empty($media['warning_id']) || empty($media['user_id']))
		{
			return false;
		}

		if (!empty($media['is_admin']) || !empty($media['is_moderator']))
		{
			return false;
		}

		$this->standardizeViewingUserReference($viewingUser);

		return ($viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'warn'));
	}

	public function canCropMedia(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($media['media_type'] != 'image_upload')
		{
			$errorPhraseKey = 'xengallery_you_cannot_crop_a_video';
			return false;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'crop')
			&& $media['user_id'] == $viewingUser['user_id']
		)
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'cropAny'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_crop_permission';
		return false;
	}
	
	public function canTagMedia(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($media['media_type'] != 'image_upload')
		{
			$errorPhraseKey = 'xengallery_you_cannot_tag_a_video';
			return false;
		}

		$tagLimit = XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'maxTags');

		if ($tagLimit !== 0 && !empty($media['tags']))
		{
			if ($tagLimit != -1)
			{
				$tagsByUser = 0;
				foreach ($media['tags'] AS $tag)
				{
					if ($tag['tag_by_user_id'] === $viewingUser['user_id'])
					{
						$tagsByUser++;
					}
				}

				if ($tagsByUser >= $tagLimit)
				{
					$errorPhraseKey = array('xengallery_media_tag_limit', 'limit' => $tagLimit);
					return false;
				}
			}
		}
	
		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'tag')
			&& $media['user_id'] == $viewingUser['user_id']
		)
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'tagAny'))
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'tagSelf'))
		{
			return 'self';
		}

		$errorPhraseKey = 'xengallery_no_tag_permission';
		return false;
	}

	public function canDeleteTag(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($media['media_type'] != 'image_upload')
		{
			$errorPhraseKey = 'xengallery_you_cannot_tag_a_video';
			return false;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteTag')
			&& $media['user_id'] == $viewingUser['user_id']
		)
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteTagAny'))
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteTagSelf'))
		{
			return 'self';
		}

		$errorPhraseKey = 'xengallery_no_delete_tag_permission';
		return false;
	}
	
	public function canViewTags(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
	
		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewTag'))
		{
			return true;
		}
	
		$errorPhraseKey = 'xengallery_no_view_tag_permission';
		return false;
	}

	public function canSetAvatar(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($media['media_type'] != 'image_upload')
		{
			$errorPhraseKey = 'xengallery_no_avatar_permission';
			return false;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'avatarAny'))
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'avatar') && $media['user_id'] == $viewingUser['user_id'])
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_avatar_permission';
		return false;
	}
	
	public function canDownloadMedia(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($media['media_type'] == 'video_embed')
		{
			$errorPhraseKey = 'xengallery_you_cannot_download_embedded_media';
			return false;
		}
	
		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'download'))
		{
			return true;
		}
	
		$errorPhraseKey = 'xengallery_no_download_permission';
		return false;
	}
	
	public function canRotateMedia(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($media['media_type'] != 'image_upload')
		{
			$errorPhraseKey = 'xengallery_you_cannot_rotate_a_video';
			return false;
		}

		if (isset($media['extension']) && $media['extension'] == 'gif')
		{
			if (!class_exists('Imagick')
				|| !XenForo_Application::getOptions()->xengalleryAnimatedRotateFlip
			)
			{
				return false;
			}
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'rotate')
			&& $media['user_id'] == $viewingUser['user_id']
		)
		{
			return true;
		}
	
		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'rotateAny'))
		{
			return true;
		}
	
		$errorPhraseKey = 'xengallery_no_rotate_permission';
		return false;
	}

	public function canFlipMedia(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($media['media_type'] != 'image_upload')
		{
			$errorPhraseKey = 'xengallery_you_cannot_flip_a_video';
			return false;
		}

		if (isset($media['extension']) && $media['extension'] == 'gif')
		{
			if (!class_exists('Imagick')
				|| !XenForo_Application::getOptions()->xengalleryAnimatedRotateFlip
			)
			{
				return false;
			}
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'flip')
			&& $media['user_id'] == $viewingUser['user_id']
		)
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'flipAny'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_flip_permission';
		return false;
	}
	
	public function canAddMedia(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		$errorPhraseKey = 'xengallery_no_add_permission';

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewCategories')
			&& !XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewAlbums'))
		{
			return false;
		}

		if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'add'))
		{
			return false;
		}

		return true;
	}
	
	public function canAddMediaToCategory(array $allowedUserGroups, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if (!$this->canAddMedia($errorPhraseKey, $viewingUser))
		{
			$errorPhraseKey = 'xengallery_you_cannot_add_media_to_this_category';
			return false;
		}
		
		if (in_array(-1, $allowedUserGroups))
		{
			return true;
		}
		
		$userModel = $this->getModelFromCache('XenForo_Model_User');
		foreach($allowedUserGroups AS $userGroupId)
		{
			if ($userModel->isMemberOfUserGroup($viewingUser, $userGroupId))
			{
				return true;
			}
		}
		
		$errorPhraseKey = 'xengallery_you_cannot_add_media_to_this_category';
		return false;
	}

	public function canChangeThumbnail(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($media['user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'thumbnail'))
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'thumbnailAny'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_change_thumbnail_permission';
		return false;
	}

	/**
	 * Gets the media state for a newly inserted media by the viewing user.
	 *
	 * @param array|null $viewingUser
	 *
	 * @return string Media state (visible, moderated, deleted)
	 */
	public function getMediaInsertState(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'approveUnapproveMedia')
			|| XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'bypassModQueueMedia')
		)
		{
			return 'visible';
		}
		else
		{
			return 'moderated';
		}
	}

	public function getViewableCategoriesForVisitor(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		/** @var $categoryModel XenGallery_Model_Category */
		$categoryModel = $this->getModelFromCache('XenGallery_Model_Category');
		if (!$categoryModel->canViewCategories($null, $viewingUser))
		{
			return array();
		}

		$userGroupIds = explode(',', $viewingUser['secondary_group_ids']);
		if (is_array($userGroupIds))
		{
			$userGroupIds = array_map('intval', $userGroupIds);
		}

		if (!$userGroupIds)
		{
			$userGroupIds = array();
		}
		$userGroupIds[] = $viewingUser['user_group_id'];

		foreach ($userGroupIds AS $key => $userGroupId)
		{
			if ($userGroupId === 0)
			{
				unset($userGroupIds[$key]);
			}
		}

		$db = $this->_getDb();
		$categoryIds = $db->fetchCol('
			SELECT category_id
			FROM xengallery_category_map
			WHERE view_user_group_id IN (' . $db->quote($userGroupIds). ')
		');

		return $categoryIds;
	}
	
	/**
	* Checks that the viewing user may managed a reported media item
	*
	* @param array $media
	* @param string $errorPhraseKey
	* @param array $viewingUser
	*
	* @return boolean
	*/
	public function canManageReportedMedia(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if (!$viewingUser['user_id'])
		{
			return false;
		}

		return (
			XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteAny')
			|| XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editAny')
		);
	}
	
	/**
	* Checks that the viewing user may manage a moderated media item
	*
	* @param array $media
	* @param string $errorPhraseKey
	* @param array $viewingUser
	*
	* @return boolean
	*/
	public function canManageModeratedMedia(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if (!$viewingUser['user_id'])
		{
			return false;
		}

		return (
			XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteAny')
			|| XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editAny')
			|| XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'approveUnapproveMedia')
		);
	}	
	
	/**
	 * Gets the set of attachment params required to allow uploading.
	 *
	 * @param array $forum
	 * @param array $contentData Information about the content, for URL building
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return array|false
	 */
	public function getAttachmentParams(array $contentData, $type = 'image_upload')
	{
		if ($this->canAddMedia())
		{
			$contentData = array_intersect_key($contentData, array_flip(array(
				'media_id',
				'album_id',
				'category_id'
			)));

			return array(
				'hash' => md5(uniqid('', true)),
				'content_type' => 'xengallery_media',
				'content_data' => $contentData,
				'upload_type' => $type
			);
		}
		else
		{
			return false;
		}
	}

	public function getUploadConstraints($type = 'image_upload', $viewingUser = array())
	{
		$viewingUser = $this->standardizeViewingUserReference($viewingUser);

		if ($type == 'image_upload')
		{
			$quotas = $this->getImageUploadConstraints($viewingUser);
		}
		else
		{
			$quotas = $this->getVideoUploadConstraints($viewingUser);
		}

		$options = XenForo_Application::getOptions();
		if ($options->xengalleryGeneralUploadConstraints)
		{
			$quotas['storage'] = $options->xengalleryGeneralUploadConstraints['storage_quota'] * 1024 * 1024;
		}
		else
		{
			$quotas['storage'] = XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'generalStorageQuota') * 1024 * 1024;
		}

		return $quotas;
	}
	
	/**
	 * Fetches uploads constraints
	 *
	 * @return array
	 */
	public function getImageUploadConstraints($viewingUser = array())
	{
		$options = XenForo_Application::getOptions();

		$allowedExtensions = preg_split('/\s+/', trim($options->xengalleryImageExtensions));

		if ($options->xengalleryImageUploadConstraints && $options->xengalleryImageUploadConstraints['global_quotas'])
		{
			$quotas = array(
				'extensions' => $allowedExtensions,
				'size' => $options->xengalleryImageUploadConstraints['file_size'] * 1024 * 1024,
				'width' => $options->xengalleryImageUploadConstraints['dimensions']['width'],
				'height' => $options->xengalleryImageUploadConstraints['dimensions']['height'],
				'count' => $options->xengalleryImageUploadConstraints['max_items']
			);
		}
		else
		{
			$quotas = array(
				'extensions' => $allowedExtensions,
				'size' => 0,
				'width' => 0,
				'height' => 0,
				'count' => 0
			);

			$fileSize = XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'imageFileSize');
			if ($fileSize > 0)
			{
				$quotas['size'] = $fileSize * 1024 * 1024;
			}

			$width = XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'imageWidth');
			if ($width > 0)
			{
				$quotas['width'] = $width;
			}

			$height = XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'imageHeight');
			if ($height > 0)
			{
				$quotas['height'] = $height;
			}

			$imageMaxItems = XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'imageMaxItems');
			if ($imageMaxItems > 0)
			{
				$quotas['count'] = $imageMaxItems;
			}
		}

		return $quotas;
	}

	/**
	 * Fetches video upload constraints
	 *
	 * @return array
	 */
	public function getVideoUploadConstraints($viewingUser = array())
	{
		$options = XenForo_Application::getOptions();

		$allowedExtensions = preg_split('/\s+/', trim($options->xengalleryVideoExtensions));

		$quotas = array(
			'extensions' => $allowedExtensions,
			'size' => 0, 'width' => 0,
			'height' => 0, 'count' => 0,
			'transcode' => $options->xengalleryTranscode
		);

		if ($options->xengalleryVideoUploadConstraints)
		{
			$quotas['size'] = $options->xengalleryVideoUploadConstraints['file_size'] * 1024 * 1024;
			$quotas['count'] = $options->xengalleryVideoUploadConstraints['max_items'];
		}
		else
		{
			$viewingUser = $this->standardizeViewingUserReference($viewingUser);

			$fileSize = XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'videoFileSize');
			if ($fileSize > 0)
			{
				$quotas['size'] = $fileSize * 1024 * 1024;
			}

			$videoMaxItems = XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'videoMaxItems');
			if ($videoMaxItems > 0)
			{
				$quotas['count'] = $videoMaxItems;
			}
		}

		return $quotas;
	}

	/**
	 * Given an array of attachment data or a file path string, ascertains whether the
	 * file will need to be transcoded. Returns true if transcoding is required. False otherwise.
	 *
	 * @param string|array $data
	 *
	 * @return bool
	 */
	public function requiresTranscoding($data)
	{
		if (!is_array($data))
		{
			$filePath = $data;
		}
		else
		{
			$filePath = $this->getAttachmentDataFilePath($data);
		}

		$videoInfo = new XenGallery_VideoInfo_Preparer($filePath);

		$result = $videoInfo->getInfo();
		if (!$result->isValid() || $result->requiresTranscoding())
		{
			return true;
		}

		return false;
	}

	public function canTranscode()
	{
		$options = XenForo_Application::getOptions();

		$ffmpegPath = $options->get('xengalleryVideoTranscoding', 'ffmpegPath');
		$transcode = $options->get('xengalleryVideoTranscoding', 'transcode');

		if (!$ffmpegPath || !$transcode)
		{
			return false;
		}

		return true;
	}

	public function updateAttachmentData($attachmentId, $mediaId)
	{
		$db = $this->_getDb();

		$attachmentData = array(
			'content_type' => 'xengallery_media',
			'temp_hash' => '',
			'unassociated' => 0
		);

		$db->update('xf_attachment', $attachmentData, "attachment_id = $attachmentId");
		$db->update('xf_attachment', array('content_id' => $mediaId), "attachment_id = $attachmentId");
	}

	public function updateExifData(array $attachment, array $media)
	{
		if (!$media['media_type'] == 'image_upload' || !function_exists('exif_read_data'))
		{
			return;
		}

		$db = $this->_getDb();

		try
		{
			$exifData = $db->fetchOne('
				SELECT media_exif_data_cache_full
				FROM xengallery_exif_cache
				WHERE data_id = ?
			', $attachment['data_id']);

			$exifData = @json_decode($exifData, true);
			if (!$exifData)
			{
				@ini_set('exif.encode_unicode', 'UTF-8');
				$exifData = exif_read_data($this->getOriginalDataFilePath($attachment, true), null, true);
				$exifData = $this->sanitizeExifData($exifData);
			}

			$options = XenForo_Application::getOptions();
			$exifOptions = $options->xengalleryExifOptions;

			$exifOutput = array();
			$this->_processExifData($exifOutput, $exifData, $exifOptions, 'FILE');
			$this->_processExifData($exifOutput, $exifData, $exifOptions, 'COMPUTED');
			$this->_processExifData($exifOutput, $exifData, $exifOptions, 'IFD0');
			$this->_processExifData($exifOutput, $exifData, $exifOptions, 'EXIF');

			foreach ($exifOutput AS $exif)
			{
				if (is_array($exif['value']))
				{
					continue;
				}

				$exifWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Exif');

				$exifWriter->bulkSet(array(
					'media_id' => $media['media_id'],
					'exif_name' => $exif['name'],
					'exif_value' => $exif['value'],
					'exif_format' => $exif['format']
				));

				$exifWriter->save();
			}

			$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');

			$mediaWriter->setExistingData($media);
			$mediaWriter->bulkSet(array(
				'media_exif_data_cache' => $exifOutput,
				'media_exif_data_cache_full' => @json_encode($exifData)
			));

			$mediaWriter->save();
		}
		catch (Exception $e) {}
	}

	public function deleteExifDataByMediaId($mediaId)
	{
		$db = $this->_getDb();

		return $db->delete('xengallery_exif', 'media_id = ' . $db->quote($mediaId));
	}

	public function deleteExifDataByMediaIds(array $mediaIds)
	{
		$db = $this->_getDb();

		return $db->delete('xengallery_exif', 'media_id IN(' . $db->quote($mediaIds) . ')');
	}

	public function sanitizeExifData(array $exif)
	{
		$allowedKeys = array(
			'FILE', 'COMPUTED', 'IFD0', 'EXIF', 'GPS'
		);
		foreach ($allowedKeys AS $key)
		{
			if (!isset($exif[$key]))
			{
				unset ($exif[$key]);
			}
		}

		foreach ($exif AS &$section)
		{
			foreach ($section AS $key => &$value)
			{
				if (is_array($value))
				{
					$value = @implode(', ', $value);
				}

				$value = XenGallery_Helper_String::toUTF8($value);
				$value = XenGallery_Helper_String::fixUTF8($value);

				if (!utf8_strlen($value))
				{
					unset ($section[$key]);
				}
			}
		}

		return $exif;
	}

	public function rebuildExifDataForMedia(array $media)
	{
		if (function_exists('exif_read_data'))
		{
			try
			{
				@ini_set('exif.encode_unicode', 'UTF-8');
				$exifData = exif_read_data($this->getOriginalDataFilePath($media, true), null, true);
				$exifData = $this->sanitizeExifData($exifData);
			}
			catch (Exception $e) { return false; }
		}
		else
		{
			return false;
		}

		$media = $this->prepareMediaExifData($media);
		if ($media['exifDataFull'])
		{
			if (isset($media['exifDataFull']['FILE']))
			{
				if (!empty($media['exifDataFull']['FILE']['SectionsFound']))
				{
					$exifData = $media['exifDataFull'];
				}
			}
		}

		$options = XenForo_Application::getOptions();
		$exifOptions = $options->xengalleryExifOptions;

		$exifOutput = array();
		$this->_processExifData($exifOutput, $exifData, $exifOptions, 'FILE');
		$this->_processExifData($exifOutput, $exifData, $exifOptions, 'COMPUTED');
		$this->_processExifData($exifOutput, $exifData, $exifOptions, 'IFD0');
		$this->_processExifData($exifOutput, $exifData, $exifOptions, 'EXIF');

		foreach ($exifOutput AS $exif)
		{
			if (is_array($exif['value']))
			{
				continue;
			}
			$exifWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Exif');

			$exifWriter->bulkSet(array(
				'media_id' => $media['media_id'],
				'exif_name' => $exif['name'],
				'exif_value' => $exif['value'],
				'exif_format' => $exif['format']
			));

			$exifWriter->save();
		}

		// Call media writer again to save EXIF data caches
		$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');

		$mediaWriter->setExistingData($media);
		$mediaWriter->bulkSet(array(
			'media_exif_data_cache' => $exifOutput,
			'media_exif_data_cache_full' => @json_encode($exifData)
		));

		return $mediaWriter->save();
	}

	protected function _processExifData(&$exifOutput, $exifData, $exifOptions, $type)
	{
		if (isset($exifOptions[$type]) && isset($exifData[$type]))
		{
			foreach ($exifOptions[$type] AS $exifOption)
			{
				if (isset($exifData[$type][$exifOption['name']]))
				{
					$name = XenGallery_Helper_String::toUTF8($exifOption['name']);
					$name = XenGallery_Helper_String::fixUTF8($name);

					$value = XenGallery_Helper_String::toUTF8($exifData[$type][$exifOption['name']]);
					$value = XenGallery_Helper_String::fixUTF8($value);

					$format = XenGallery_Helper_String::toUTF8($exifOption['format']);
					$format = XenGallery_Helper_String::fixUTF8($format);

					$exifOutput[$exifOption['name']] = array(
						'name' => $name,
						'value' => $value,
						'format' => $format
					);
				}
			}
		}
	}

	public function getAttachmentDataFilePath($data)
	{
		$attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');

		if (is_callable(array($attachmentModel, 'bdAttachmentStore_useTempFile')))
		{
			$attachmentModel->bdAttachmentStore_useTempFile(true);
		}

		$filePath = $attachmentModel->getAttachmentDataFilePath($data);

		if (is_callable(array($attachmentModel, 'bdAttachmentStore_useTempFile')))
		{
			$attachmentModel->bdAttachmentStore_useTempFile(false);
		}

		return $filePath;
	}

	public function getOriginalDataFilePath(array $data, $checkExists = false, $internalDataPath = null)
	{
		if ($internalDataPath === null)
		{
			$internalDataPath = XenForo_Helper_File::getInternalDataPath();
		}

		$filePath = sprintf('%s/xengallery/originals/%d/%d-file_hash.data',
			$internalDataPath,
			floor($data['data_id'] / 1000),
			$data['data_id']
		);

		if (XenForo_Application::getOptions()->xengalleryEnableWatermarking == 'disabled'
			&& empty($data['watermark_id'])
		)
		{
			@unlink($filePath);
			$checkExists = true;
		}

		if ($checkExists && (!file_exists($filePath) || !is_readable($filePath)))
		{
			$filePath = $this->getAttachmentDataFilePath($data);
		}

		return $filePath;
	}
	
	/**
	 * Gets the full path to this attachment's thumbnail.
	 *
	 * @param string $fileHash Data file hash
	 * @param int $mediaId Media ID
	 *
	 * @return string
	 */
	public function getMediaThumbnailFilePath($data)
	{
		if (is_array($data))
		{
			return sprintf('%s/xengallery/%d/%d-%s.jpg',
				XenForo_Helper_File::getExternalDataPath(),
				floor($data['data_id'] / 1000),
				$data['data_id'],
				$data['file_hash']
			);
		}
		else
		{
			preg_match('/\[media=(.*?)\](.*?)\[\/media\]/is', $data, $parts);

			$thumbnailPath = sprintf('%s/xengallery/%s/%s_%s',
				XenForo_Helper_File::getExternalDataPath(),
				$parts[1], $parts[1], $parts[2]
			);

			return array(
				$thumbnailPath . '_thumb.jpg',
				$thumbnailPath . '.jpg'
			);
		}

		return false;
	}

	public function getVideoFilePath($extension = 'mp4')
	{
		return '%DATA%/xengallery_videos/%FLOOR%/%DATA_ID%-%HASH%.' . $extension;
	}

	public function getVideoUrl($data)
	{
		return sprintf('%s/xengallery_videos/%d/%d-%s.%s',
			XenForo_Application::$externalDataUrl,
			floor($data['data_id'] / 1000),
			$data['data_id'],
			$data['file_hash'],
			'mp4'
		);
	}

	public function getVideoThumbnailFromParts(array $parts)
	{
		if (!isset($parts[0]) || !isset($parts[1]))
		{
			return false;
		}

		$serviceId = preg_replace('#[^a-zA-Z0-9_]#', '', $parts[0]);

		$videoId = $parts[1];
		$cleanVideoId = XenGallery_Helper_String::cleanVideoId($videoId);

		if (!strlen($serviceId) || !strlen($cleanVideoId))
		{
			return false;
		}

		$videoThumbnail = sprintf('%s/xengallery/%s/%s_%s_thumb.jpg',
			XenForo_Helper_File::getExternalDataPath(),
			$serviceId, $serviceId, $cleanVideoId
		);

		if (!file_exists($videoThumbnail) || !is_readable($videoThumbnail))
		{
			$mediaSiteOptions = XenForo_Application::getOptions()->xengalleryMediaThumbs;
			if (empty($mediaSiteOptions[$serviceId]))
			{
				return false;
			}

			$mediaSite = $mediaSiteOptions[$serviceId];

			if (strpos($mediaSite, '_'))
			{
				if (class_exists($mediaSite))
				{
					/** @var $thumbnailObj XenGallery_Thumbnail_Abstract */
					$thumbnailObj = XenGallery_Thumbnail_Abstract::create($mediaSite);
					$thumbnailPath = $thumbnailObj->getThumbnailUrl($videoId);
				}
				else
				{
					$thumbnailPath = $mediaSite;
				}
			}
			else
			{
				$thumbnailPath = $mediaSite;
			}

			if (strpos($thumbnailPath, '{$id}'))
			{
				$this->_thumbnailPath = XenForo_Application::$externalDataPath . '/xengallery/' . $serviceId;
				XenForo_Helper_File::createDirectory($this->_thumbnailPath, true);

				$thumbnailUrl = str_replace('{$id}', rawurldecode($videoId), $thumbnailPath);
				XenGallery_Thumbnail_Abstract::saveThumbnailFromUrl($serviceId, $cleanVideoId, $thumbnailUrl);
			}
		}

		return $videoThumbnail;
	}

	public function getVideoThumbnailUrlFromParts(array $parts, $force = false)
	{
		if (!isset($parts[1]) || !isset($parts[2]))
		{
			return false;
		}

		$serviceId = preg_replace('#[^a-zA-Z0-9_]#', '', $parts[1]);

		$videoId = $parts[2];
		$cleanVideoId = XenGallery_Helper_String::cleanVideoId($videoId);

		if (!strlen($serviceId) || !strlen($cleanVideoId))
		{
			return false;
		}

		$videoThumbnail = sprintf('%s/xengallery/%s/%s_%s_thumb.jpg',
			XenForo_Application::$externalDataUrl,
			$serviceId, $serviceId, $cleanVideoId
		);

		$videoThumbnailPath = sprintf('%s/xengallery/%s/%s_%s.jpg',
			XenForo_Application::$externalDataPath,
			$serviceId, $serviceId, $cleanVideoId
		);

		if (!file_exists($videoThumbnailPath) || !is_readable($videoThumbnailPath) || $force)
		{
			$mediaSiteOptions = XenForo_Application::getOptions()->xengalleryMediaThumbs;
			if (empty($mediaSiteOptions[$serviceId]))
			{
				return false;
			}

			$mediaSite = $mediaSiteOptions[$serviceId];

			if (strpos($mediaSite, '_'))
			{
				if (class_exists($mediaSite))
				{
					/** @var $thumbnailObj XenGallery_Thumbnail_Abstract */
					$thumbnailObj = XenGallery_Thumbnail_Abstract::create($mediaSite);

					if ($mediaSite == 'XenGallery_Thumbnail_NoThumb')
					{
						$videoThumbnailPath = $thumbnailObj->getNoThumbnailUrl($videoId, $serviceId);
					}
					else
					{
						$videoThumbnailPath = $thumbnailObj->getThumbnailUrl($videoId);
					}
				}
				else
				{
					$videoThumbnailPath = $mediaSite;
				}
			}
			else
			{
				$videoThumbnailPath = $mediaSite;
			}

			if (strpos($videoThumbnailPath, '{$id}'))
			{
				$this->_thumbnailPath = XenForo_Application::$externalDataPath . '/xengallery/' . $parts[0];
				XenForo_Helper_File::createDirectory($this->_thumbnailPath, true);

				$videoThumbnailPath = str_replace('{$id}', rawurldecode($cleanVideoId), $videoThumbnailPath);
				XenGallery_Thumbnail_Abstract::saveThumbnailFromUrl($serviceId, $videoId, $videoThumbnailPath);
			}
			else
			{
				$this->_thumbnailPath = XenForo_Application::$externalDataPath . '/xengallery/' . $serviceId;
				XenForo_Helper_File::createDirectory($this->_thumbnailPath, true);

				XenGallery_Thumbnail_Abstract::saveThumbnailFromPath($serviceId, $videoId, $videoThumbnailPath);
			}

			if (!$mediaSite)
			{
				/** @var $thumbnailObj XenGallery_Thumbnail_Abstract */
				$thumbnailObj = XenGallery_Thumbnail_Abstract::create('XenGallery_Thumbnail_NoThumb');
				$thumbnailObj->getNoThumbnailUrl($videoId, $serviceId);
			}
		}

		return $videoThumbnail;
	}
	
	/**
	 * Gets the default file path to the thumbnail directory
	 *
	 */
	public function getThumbnailFilePath(array $data)
	{
		return sprintf('%s/xengallery/%d/',
			XenForo_Application::$externalDataUrl,
			floor($data['data_id'] / 1000)
		);
	}
	
	/**
	 * Gets the URL to this media's thumbnail. May be absolute or
	 * relative to the application root directory.
	 *
	 * @param string $fileHash Data file hash
	 * @param int $mediaId Media ID
	 *
	 * @return string
	 */
	public function getMediaThumbnailUrl(array $data)
	{
		if (!isset($data['media_type'])
			|| ($data['media_type'] == 'image_upload' || $data['media_type'] == 'video_upload'))
		{
			return sprintf('%s/xengallery/%d/%d-%s.jpg',
				XenForo_Application::$externalDataUrl,
				floor($data['data_id'] / 1000),
				$data['data_id'],
				$data['file_hash']
			);
		}
		else
		{
			preg_match('/\[media=(.*?)\](.*?)\[\/media\]/is', $data['media_tag'], $parts);
			if (isset($parts[1], $parts[2]))
			{
				return XenForo_Link::buildPublicLink('xengallery/thumbnail', '', array('id' => $parts[1] . '.' . $parts[2]));
			}
		}
	}

	public function rebuildThumbnail(array $media, array $imageInfo, $deleteExisting = true, $skipThumbnail = 0)
	{
		$originalThumbFile = $this->getMediaThumbnailFilePath($media);

		$media['file_hash'] = $imageInfo['file_hash'];

		$options = XenForo_Application::getOptions();

		$thumbFile = $this->getMediaThumbnailFilePath($media);
		$thumbImage = false;

		if ($skipThumbnail)
		{
			XenForo_Helper_File::safeRename($originalThumbFile, $thumbFile);
		}
		else
		{
			if ($media['media_type'] == 'image_upload')
			{
				$originalFile = $this->getOriginalDataFilePath($media, true);
				$thumbImage = new XenGallery_Helper_Image($originalFile);
			}
			else if ($media['media_type'] == 'video_upload')
			{
				$originalFile = $this->getAttachmentDataFilePath($media);
				$tempThumbFile = false;

				if ($options->get('xengalleryVideoTranscoding', 'thumbnail'))
				{
					try
					{
						$video = new XenGallery_Helper_Video($originalFile);
						$tempThumbFile = $video->getKeyFrame();
					}
					catch (XenForo_Exception $e) { }
				}

				if (!$tempThumbFile)
				{
					$tempThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xfmg');
					if ($tempThumbFile)
					{
						@copy($options->xengalleryDefaultNoThumb, $tempThumbFile);
					}
				}

				$thumbImage = new XenGallery_Helper_Image($tempThumbFile);
			}

			if ($thumbImage)
			{
				$thumbImage->resize(
					$options->xengalleryThumbnailDimension['width'],
					$options->xengalleryThumbnailDimension['height'], 'crop'
				);

				$thumbnailed = $thumbImage->saveToPath($thumbFile);

				if ($thumbnailed && $deleteExisting)
				{
					@unlink($originalThumbFile);
				}
			}
		}

		$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');

		$mediaWriter->setExistingData($media);
		$mediaWriter->bulkSet(array(
			'last_edit_date' => XenForo_Application::$time,
			'thumbnail_date' => $media['thumbnail_date']
		));

		$mediaWriter->save();

		if ($media['album_id'])
		{
			$albumWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
			$albumWriter->setExistingData($media['album_id']);

			if (!$albumWriter->get('manual_media_cache') && !$albumWriter->get('album_thumbnail_date'))
			{
				$mediaItems = $this->getMediaForAlbumCache($media['album_id']);

				$albumWriter->bulkSet(array(
					'last_update_date' => XenForo_Application::$time,
					'media_cache' => serialize($mediaItems)
				));

				$albumWriter->save();
			}
		}
	}

	public function sendAuthorAlert(array $content, $contentType, $action, array $options, array $extra = array(), $userIdKey = 'user_id')
	{
		$category = array();
		$album = array();

		if (!empty($content['category_id']))
		{
			/** @var XenGallery_Model_Category $categoryModel */
			$categoryModel = $this->getModelFromCache('XenGallery_Model_Category');

			$category = $categoryModel->getCategoryById($content['category_id']);
			$category = $categoryModel->prepareCategory($category);
		}

		if (!empty($content['album_id']))
		{
			/** @var XenGallery_Model_Album $albumModel */
			$albumModel = $this->getModelFromCache('XenGallery_Model_Album');

			$album = $albumModel->getAlbumByIdSimple($content['album_id']);
			if ($album)
			{
				$album = $albumModel->prepareAlbum($album);
			}
			else
			{
				$album = $content; // Probably just deleted the album so use the $content record we already have.
			}
		}

		if ($options['authorAlert'])
		{
			$extra = array_merge(array(
				'reason' => $options['authorAlertReason'],
				'content' => $content,
				'album' => $album,
				'category' => $category
			), $extra);

			XenForo_Model_Alert::alert(
				$content[$userIdKey],
				0, '',
				'user', $content[$userIdKey],
				"{$contentType}_{$action}",
				$extra
			);
		}
	}
}
