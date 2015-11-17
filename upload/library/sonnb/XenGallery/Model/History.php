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
class sonnb_XenGallery_Model_History extends sonnb_XenGallery_Model_Abstract
{
	const FETCH_USER = 0x01;
	
	public function getHistoryById($id, array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
			
		$conditions['history_id'] = $id;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getHistories($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}
	
	public function getHistoriesByIds($ids, array $fetchOptions = array())
	{
		if (!$ids)
		{
			return array();
		}
		
		$conditions['history_id'] = $ids;
		
		return $this->getHistories($conditions, $fetchOptions);
	}

	public function getHistoriesByContentId($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$contentType || !$contentId)
		{
			return array();
		}
		
		$conditions['content_type'] = $contentType;
		$conditions['content_id'] = $contentId;
		
		return $this->getHistories($conditions, $fetchOptions);
	}

	public function getHistoriesByContentIds($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		return $this->getHistoriesByContentId($contentType, $contentId, $conditions, $fetchOptions);
	}
	
	public function getHistories(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareHistoryConditions($conditions, $fetchOptions);
		
		$sqlClauses = $this->prepareHistoryFetchOptions($fetchOptions);
		
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		
		return $this->fetchAllKeyed(
				$this->limitQueryResults(
						'
		                   SELECT history.*
		                        ' . $sqlClauses['selectFields'] . '
		                   FROM `sonnb_xengallery_history` AS history
		                    	' . $sqlClauses['joinTables'] . '
		                   WHERE ' . $whereConditions . '
		                    	' . $sqlClauses['orderClause'] . '
	                	', $limitOptions['limit'], $limitOptions['offset']
				), 'history_id'
		);
	}
	
	public function countHistoriesByContentId($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$contentType || !$contentId)
		{
			return array();
		}
		
		$conditions['content_type'] = $contentType;
		$conditions['content_id'] = $contentId;
		
		return $this->countHistories($conditions, $fetchOptions);
	}
	
	public function countHistories(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareHistoryConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareHistoryFetchOptions($fetchOptions);
		
		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_history` AS history
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
            ');
	}
	
	public function prepareHistoryFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';
		
		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';
		
			switch ($fetchOptions['order'])
			{
				case 'history_id':
				case 'content_type':
				case 'content_id':
				case 'user_id':
				case 'history_type':
				case 'history_sub_type':
					$orderBy = 'history.' . $fetchOptions['order'];
					$orderBySecondary = ', history.history_date DESC';
					break;
				case 'history_date':
				default:
					$orderBy = 'history.history_date';
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
					user.username, user.avatar_date, user.gravatar';
				$joinTables .= '
					LEFT JOIN `xf_user` AS user ON
						(user.user_id = history.user_id)';
			}
		}
		
		return array(
				'selectFields' => $selectFields,
				'joinTables' => $joinTables,
				'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}
	
	public function prepareHistoryConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
		
		if (!empty($conditions['history_id']))
		{
			if (is_array($conditions['history_id']))
			{
				$sqlConditions[] = 'history.history_id IN (' . $db->quote($conditions['history_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'history.history_id = ' . $db->quote($conditions['history_id']);
			}
		}
		
		if (isset($conditions['user_id']))
		{
			if (is_array($conditions['user_id']))
			{
				$sqlConditions[] = 'history.user_id IN (' . $db->quote($conditions['user_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'history.user_id = ' . $db->quote($conditions['user_id']);
			}
		}
		
		if (!empty($conditions['content_type']))
		{
			if (is_array($conditions['content_type']))
			{
				$sqlConditions[] = 'history.content_type IN (' . $db->quote($conditions['content_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'history.content_type = ' . $db->quote($conditions['content_type']);
			}
		}
		
		if (!empty($conditions['content_id']))
		{
			if (is_array($conditions['content_id']))
			{
				$sqlConditions[] = 'history.content_id IN (' . $db->quote($conditions['content_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'history.content_id = ' . $db->quote($conditions['content_id']);
			}
		}
		
		if (!empty($conditions['history_type']))
		{
			if (is_array($conditions['history_type']))
			{
				$sqlConditions[] = 'history.history_type IN (' . $db->quote($conditions['history_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'history.history_type = ' . $db->quote($conditions['history_type']);
			}
		}
		
		if (!empty($conditions['history_sub_type']))
		{
			if (is_array($conditions['history_sub_type']))
			{
				$sqlConditions[] = 'history.history_sub_type IN (' . $db->quote($conditions['history_sub_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'history.history_sub_type = ' . $db->quote($conditions['history_sub_type']);
			}
		}
		
		if (!empty($conditions['history_date']) && is_array($conditions['history_date']))
		{
			list($operator, $cutOff) = $conditions['history_date'];
		
			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "history.history_date $operator " . $db->quote($cutOff);
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
}
