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
class sonnb_XenGallery_Model_Stream extends sonnb_XenGallery_Model_Abstract
{
	const FETCH_USER = 0x01;
	
	public function getStreamById($id, array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
			
		$conditions['stream_id'] = $id;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getStreams($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}
	
	public function getStreamsByIds($ids, array $fetchOptions = array())
	{
		if (!$ids)
		{
			return array();
		}
		
		$conditions['stream_id'] = $ids;
		
		return $this->getStreams($conditions, $fetchOptions);
	}

	public function getStreamsByContentId($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$contentType || !$contentId)
		{
			return array();
		}
		
		$conditions['content_type'] = $contentType;
		$conditions['content_id'] = $contentId;
		
		return $this->getStreams($conditions, $fetchOptions);
	}

	public function getStreamsByContentIds($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		return $this->getStreamsByContentId($contentType, $contentId, $conditions, $fetchOptions);
	}
	
	public function getStreams(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareStreamConditions($conditions, $fetchOptions);
		
		$sqlClauses = $this->prepareStreamFetchOptions($fetchOptions);
		
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		
		return $this->fetchAllKeyed(
				$this->limitQueryResults(
						'
		                   SELECT stream.*
		                        ' . $sqlClauses['selectFields'] . '
		                   FROM `sonnb_xengallery_stream` AS stream
		                    	' . $sqlClauses['joinTables'] . '
		                   WHERE ' . $whereConditions . '
		                    	' . $sqlClauses['orderClause'] . '
	                	', $limitOptions['limit'], $limitOptions['offset']
				), 'stream_id'
		);
	}

	public function getUniqueStreams(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareStreamConditions($conditions, $fetchOptions);

		$sqlClauses = $this->prepareStreamFetchOptions($fetchOptions);

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed(
			$this->limitQueryResults(
				'
				   SELECT stream.*, COUNT(*) AS item_count
						' . $sqlClauses['selectFields'] . '
		                   FROM `sonnb_xengallery_stream` AS stream
		                    	' . $sqlClauses['joinTables'] . '
		                   WHERE ' . $whereConditions . '
		                        GROUP BY stream.stream_name
		                    	' . $sqlClauses['orderClause'] . '
	                	', $limitOptions['limit'], $limitOptions['offset']
			), 'stream_id'
		);
	}
	
	public function countStreamsByContentId($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$contentType || !$contentId)
		{
			return array();
		}
		
		$conditions['content_type'] = $contentType;
		$conditions['content_id'] = $contentId;
		
		return $this->countStreams($conditions, $fetchOptions);
	}
	
	public function countStreams(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareStreamConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareStreamFetchOptions($fetchOptions);
		
		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_stream` AS stream
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
            ');
	}

	public function countUniqueStreams(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareStreamConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareStreamFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_stream` AS stream
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
                    GROUP BY stream.stream_name
            ');
	}

	public function prepareStreamsForDisplay(array $streams, $totalStreams)
	{
		if ($streams)
		{
			foreach ($streams as &$_stream)
			{
				$_stream['streamClass'] = $this->_getClassForTagSize(($_stream['item_count']/$totalStreams)*100);
			}
		}

		shuffle($streams);

		return $streams;
	}

	protected function _getClassForTagSize($percent)
	{
		$percent = floor($percent);
		switch (true)
		{
			case $percent >= 99:
				$class = 9;
				break;
			case $percent >= 80:
				$class = 8;
				break;
			case $percent >= 70:
				$class = 7;
				break;
			case $percent >= 60:
				$class = 6;
				break;
			case $percent >= 50:
				$class = 5;
				break;
			case $percent >= 40:
				$class = 4;
				break;
			case $percent >= 30:
				$class = 3;
				break;
			case $percent >= 20:
				$class = 2;
				break;
			case $percent >= 10:
				$class = 1;
				break;
			default:
				$class = 0;
				break;
		}

		return $class;
	}

	public function getStreamBreadCrumb($stream = null)
	{
		$breadCrumbs = array();
		$breadCrumbs['stream'] = array(
			'href' => XenForo_Link::buildPublicLink('full:gallery/streams'),
			'value' => new XenForo_Phrase('sonnb_xengallery_streams_cloud')
		);

		if ($stream !== null)
		{
			$breadCrumbs['stream_'.$stream['stream_id']] = array(
				'href' => XenForo_Link::buildPublicLink('full:gallery/streams', $stream),
				'value' => $stream['stream_name'],
			);
		}

		return $breadCrumbs;
	}

	public function publishStream($contentType, $contentId, $streams, $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!is_array($streams))
		{
			$streams = explode(',', $streams);
			$streams = array_filter($streams, 'utf8_trim');
			$streams = array_filter($streams);
			$streams = array_unique($streams);
		}

		if (empty($streams))
		{
			return false;
		}

		$maximumStream = $this->_getGalleryModel()->getMaximumStreamCount($viewingUser);
		$existingStreams = $this->getStreamsByContentId($contentType, $contentId);
		$totalStreams = count($existingStreams);

		if ($maximumStream > 0 && $totalStreams >  $maximumStream)
		{
			return -1;
		}

		foreach ($streams as $_index => &$_stream)
		{
			$_stream = utf8_trim($_stream);

			if ($this->checkExistingStreams($_stream, $existingStreams)
					|| ($maximumStream > 0 && $totalStreams >=  $maximumStream))
			{
				unset($streams[$_index]);
				continue;
			}

			$totalStreams++;
		}

		if (empty($streams))
		{
			return false;
		}

		$db = $this->_getDb();
		foreach ($streams as $__stream)
		{
			$db->insert('sonnb_xengallery_stream', array(
				'stream_name' => $__stream,
				'content_id' => $contentId,
				'content_type' => $contentType,
				'user_id' => $viewingUser['user_id'],
				'username' => $viewingUser['username'],
				'stream_date' => XenForo_Application::$time
			));
		}

		return $streams;
	}

	public function removeStream($contentType, $contentId, $stream, $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$db = $this->_getDb();
		$db->delete('sonnb_xengallery_stream', array(
			'stream_name = '. $db->quote($stream),
			'content_type = '.$db->quote($contentType),
			'content_id = '.$db->quote($contentId)
		));

		return $stream;
	}

	public function removeStreams($contentType, $contentId, array $streams, $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$db = $this->_getDb();
		$db->delete('sonnb_xengallery_stream', array(
			'stream_name IN ('. $db->quote($streams) .')',
			'content_type = '.$db->quote($contentType),
			'content_id = '.$db->quote($contentId)
		));

		return $streams;
	}

	public function checkExistingStreams($needed, array $existingStreams)
	{
		if (empty($existingStreams))
		{
			return false;
		}

		foreach ($existingStreams as $stream)
		{
			if ($stream['stream_name'] === $needed)
			{
				return true;
				break;
			}
		}

		return false;
	}

	public function prepareStreamFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';
		
		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';
		
			switch ($fetchOptions['order'])
			{
				case 'stream_id':
				case 'stream_name':
				case 'content_type':
				case 'content_id':
				case 'user_id':
					$orderBy = 'stream.' . $fetchOptions['order'];
					$orderBySecondary = ', stream.stream_date DESC';
					break;
				case 'item_count':
					$orderBy = $fetchOptions['order'];
					break;
				case 'stream_date':
				default:
					$orderBy = 'stream.stream_date';
			}
			if (!isset($fetchOptions['orderDirection']) || $fetchOptions['orderDirection'] === 'desc')
			{
				$orderBy .= ' DESC';
			}
			else
			{
				$orderBy .= ' ASC';
			}
		
			$orderBy .= $orderBySecondary;
		}
		
		if (!empty($fetchOptions['join']))
		{		
			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= ',
					IF(user.username IS NULL, stream.username, user.username) AS username, user.avatar_date, user.gravatar';
				$joinTables .= '
					LEFT JOIN `xf_user` AS user ON
						(user.user_id = stream.user_id)';
			}
		}
		
		return array(
				'selectFields' => $selectFields,
				'joinTables' => $joinTables,
				'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}
	
	public function prepareStreamConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
		
		if (!empty($conditions['stream_id']))
		{
			if (is_array($conditions['stream_id']))
			{
				$sqlConditions[] = 'stream.stream_id IN (' . $db->quote($conditions['stream_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'stream.stream_id = ' . $db->quote($conditions['stream_id']);
			}
		}

		if (!empty($conditions['stream_name']))
		{
			if (is_array($conditions['stream_name']))
			{
				$sqlConditions[] = 'stream.stream_name IN (' . $db->quote($conditions['stream_name']) . ')';
			}
			else
			{
				$sqlConditions[] = 'stream.stream_name = ' . $db->quote($conditions['stream_name']);
			}
		}

		if (!empty($conditions['stream_name_search']))
		{
			if (is_array($conditions['stream_name_search']))
			{
				$sqlConditions[] = 'stream.stream_name LIKE ' . XenForo_Db::quoteLike($conditions['stream_name_search'][0], $conditions['stream_name_search'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'stream.stream_name LIKE ' . XenForo_Db::quoteLike($conditions['stream_name_search'], 'lr', $db);
			}
		}
		
		if (isset($conditions['user_id']))
		{
			if (is_array($conditions['user_id']))
			{
				$sqlConditions[] = 'stream.user_id IN (' . $db->quote($conditions['user_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'stream.user_id = ' . $db->quote($conditions['user_id']);
			}
		}
		
		if (!empty($conditions['content_type']))
		{
			if (is_array($conditions['content_type']))
			{
				$sqlConditions[] = 'stream.content_type IN (' . $db->quote($conditions['content_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'stream.content_type = ' . $db->quote($conditions['content_type']);
			}
		}
		
		if (!empty($conditions['content_id']))
		{
			if (is_array($conditions['content_id']))
			{
				$sqlConditions[] = 'stream.content_id IN (' . $db->quote($conditions['content_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'stream.content_id = ' . $db->quote($conditions['content_id']);
			}
		}
		
		if (!empty($conditions['stream_date']) && is_array($conditions['stream_date']))
		{
			list($operator, $cutOff) = $conditions['stream_date'];
		
			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "stream.stream_date $operator " . $db->quote($cutOff);
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * @return sonnb_XenGallery_Model_Gallery
	 */
	protected function _getGalleryModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Gallery');
	}
}
