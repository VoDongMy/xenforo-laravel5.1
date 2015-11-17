<?php

class XenGallery_Model_Rating extends XenForo_Model
{
	const FETCH_USER = 0x01;
	const FETCH_CONTENT = 0x02;

	public function getRatingById($ratingId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareRatingFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT rating.*
				' . $joinOptions['selectFields'] . '
			FROM xengallery_rating AS rating
			' . $joinOptions['joinTables'] . '
			WHERE rating_id = ?
		', $ratingId);
	}

	public function getRatingByContentAndUser($contentId, $contentType, $userId, array $fetchOptions = array(), $fetch = 'fetchRow')
	{
		$joinOptions = $this->prepareRatingFetchOptions($fetchOptions);

		return $this->_getDb()->$fetch('
			SELECT rating.*
				' . $joinOptions['selectFields'] . '
			FROM xengallery_rating AS rating
			' . $joinOptions['joinTables'] . '
			WHERE user_id = ?
			AND content_id = ?
			AND content_type = ? 
		', array($userId, $contentId, $contentType));
	}

	public function getRatingsByIds(array $ratingIds, array $fetchOptions = array())
	{
		if (!$ratingIds)
		{
			return array();
		}

		$joinOptions = $this->prepareRatingFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT rating.*
				' . $joinOptions['selectFields'] . '
			FROM xengallery_rating AS rating
			' . $joinOptions['joinTables'] . '
			WHERE rating_id IN (' . $this->_getDb()->quote($ratingIds) . ')
		', 'rating_id');
	}

	/**
	* Fetch media ratings based on the conditions and options specified
	*
	* @param array $conditions
	* @param array $fetchOptions
	*
	* @return array
	*/
	public function getRatings(array $conditions = array(), array $fetchOptions = array())
	{
		$whereClause = $this->prepareRatingConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareRatingOrderOptions($fetchOptions, 'rating.rating_date DESC');
		$joinOptions = $this->prepareRatingFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT rating.*
					' . $joinOptions['selectFields'] . '
				FROM xengallery_rating AS rating
				' . $joinOptions['joinTables'] . '
				WHERE ' . $whereClause . '
				' . $orderClause . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'rating_id');
	}

	public function deleteRatingsByMediaId($mediaId)
	{
		$ratings = $this->getRatings(array('media_id' => $mediaId));
		foreach ($ratings AS $rating)
		{
			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Rating', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($rating);
			$dw->delete();
		}

		return true;
	}

	/**
	* Count the number of ratings that meet the given criteria.
	*
	* @param array $conditions
	*
	* @return integer
	*/
	public function countRatings(array $conditions = array())
	{
		$fetchOptions = array();

		$whereClause = $this->prepareRatingConditions($conditions, $fetchOptions);
		$joinOptions = $this->prepareRatingFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xengallery_rating AS rating
			' . $joinOptions['joinTables'] . '
			WHERE ' . $whereClause
		);
	}

	/**
	* Prepares a set of conditions against which to select ratings.
	*
	* @param array $conditions List of conditions.
	* @param array $fetchOptions The fetch options that have been provided. May be edited if criteria requires.
	*
	* @return string Criteria as SQL for where clause
	*/
	public function prepareRatingConditions(array $conditions, array &$fetchOptions)
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['user_id']))
		{
			if (is_array($conditions['user_id']))
			{
				$sqlConditions[] = 'rating.user_id IN (' . $db->quote($conditions['user_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'rating.user_id = ' . $db->quote($conditions['user_id']);
			}
		}

		if (!empty($conditions['media_id']))
		{
			if (is_array($conditions['media_id']))
			{
				$sqlConditions[] = 'rating.content_id IN (' . $db->quote($conditions['media_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'rating.content_id = ' . $db->quote($conditions['media_id']);
			}

			$sqlConditions[] = 'rating.content_type = \'media\'';
		}

		if (!empty($conditions['album_id']))
		{
			if (is_array($conditions['album_id']))
			{
				$sqlConditions[] = 'rating.content_id IN (' . $db->quote($conditions['album_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'rating.content_id = ' . $db->quote($conditions['album_id']);
			}

			$sqlConditions[] = 'rating.content_type = \'album\'';
		}

		if (!empty($conditions['content_id']))
		{
			if (is_array($conditions['content_id']))
			{
				$sqlConditions[] = 'rating.content_id IN (' . $db->quote($conditions['content_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'rating.content_id = ' . $db->quote($conditions['content_id']);
			}
		}
		
		if (!empty($conditions['content_type']))
		{
			if (is_array($conditions['content_type']))
			{
				$sqlConditions[] = 'rating.content_type IN (' . $db->quote($conditions['content_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'rating.content_type = ' . $db->quote($conditions['content_type']);
			}
		}

		if (isset($conditions['count_rating']))
		{
			$sqlConditions[] = 'rating.count_rating = ' . ($conditions['count_rating'] ? 1 : 0);
		}
		
		if (!empty($conditions['rating_id']))
		{
			if (is_array($conditions['rating_id']))
			{
				$sqlConditions[] = 'rating.rating_id IN (' . $db->quote($conditions['rating_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'rating.rating_id = ' . $db->quote($conditions['rating_id']);
			}
		}				

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Construct 'ORDER BY' clause
	 *
	 * @param array $fetchOptions (uses 'order' key)
	 * @param string $defaultOrderSql Default order SQL
	 *
	 * @return string
	 */
	public function prepareRatingOrderOptions(array &$fetchOptions, $defaultOrderSql = '')
	{
		$choices = array(
			'rating_date' => 'media.rating_date',
		);
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}

	/**
	 * Prepares join-related fetch options.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array Containing 'selectFields' and 'joinTables' keys.
	 */
	public function prepareRatingFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= ',
						user.*, user_profile.*';
				$joinTables .= '
						INNER JOIN xf_user AS user ON
							(user.user_id = rating.user_id)
						INNER JOIN xf_user_profile AS user_profile ON
							(user_profile.user_id = rating.user_id)';
			}
		}
		
		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_CONTENT)
			{
				$selectFields .= ',
					media.*, album.*, attachment.*, ' . XenForo_Model_Attachment::$dataColumns;
					
				$joinTables .= '
					LEFT JOIN xengallery_media AS media ON
						(media.media_id = rating.content_id AND rating.content_type = \'media\')
					LEFT JOIN xengallery_album AS album ON
						(album.album_id = rating.content_id AND rating.content_type = \'album\')
					LEFT JOIN xf_attachment AS attachment ON
						(attachment.attachment_id = media.attachment_id)
					LEFT JOIN xf_attachment_data AS data ON
						(data.data_id = attachment.data_id)
				';
			}
		}

		return array(
				'selectFields' => $selectFields,
				'joinTables'   => $joinTables
		);
	}

	/**
	* @return XenGallery_Model_Media
	*/
	protected function _getMediaModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Media');
	}
}