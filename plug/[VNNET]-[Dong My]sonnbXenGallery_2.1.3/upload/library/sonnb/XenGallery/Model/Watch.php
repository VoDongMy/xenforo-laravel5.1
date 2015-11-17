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
class sonnb_XenGallery_Model_Watch extends sonnb_XenGallery_Model_Abstract
{
	const FETCH_USER = 0x01;
	const FETCH_USER_FULL = 0x02;
	
	public function getWatchById($id, array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
			
		$conditions['watch_id'] = $id;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getWatches($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}
	
	public function getWatchesByIds($ids, array $fetchOptions = array())
	{
		if (!$ids)
		{
			return array();
		}
		
		$conditions['watch_id'] = $ids;
		
		return $this->getWatches($conditions, $fetchOptions);
	}

	public function getWatchesByContentId($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$contentType || !$contentId)
		{
			return array();
		}
		
		$conditions['content_type'] = $contentType;
		$conditions['content_id'] = $contentId;
		
		return $this->getWatches($conditions, $fetchOptions);
	}

	public function getWatchesByContentIds($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		return $this->getWatchesByContentId($contentType, $contentId, $conditions, $fetchOptions);
	}

	public function getWatchByUserIdContentId($userId, $contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		$conditions['user_id'] = $userId;
		
		$return = $this->getWatchesByContentId($contentType, $contentId, $conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}
	
	public function getWatches(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareWatchConditions($conditions, $fetchOptions);
		
		$sqlClauses = $this->prepareWatchFetchOptions($fetchOptions);
		
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		
		return $this->fetchAllKeyed(
				$this->limitQueryResults(
						'
		                   SELECT watch.*
		                        ' . $sqlClauses['selectFields'] . '
		                   FROM `sonnb_xengallery_watch` AS watch
		                    	' . $sqlClauses['joinTables'] . '
		                   WHERE ' . $whereConditions . '
		                    	' . $sqlClauses['orderClause'] . '
	                	', $limitOptions['limit'], $limitOptions['offset']
				), 'watch_id'
		);
	}
	
	public function countWatchesByContentId($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$contentType || !$contentId)
		{
			return array();
		}
		
		$conditions['content_type'] = $contentType;
		$conditions['content_id'] = $contentId;
		
		return $this->countWatches($conditions, $fetchOptions);
	}
	
	public function countWatches(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareWatchConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareWatchFetchOptions($fetchOptions);
		
		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_watch` AS watch
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
            ');
	}
	
	public function insertUpdateWatcherByContentId($user, $contentType, $contentId)
	{
		$existingWatch = $this->getWatchByUserIdContentId($user['user_id'], $contentType, $contentId);
		
		if (!$existingWatch)
		{
			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Watch');
			$dw->bulkSet(array(
				'user_id' => $user['user_id'],
				'username' => $user['username'],
				'content_type' => $contentType,
				'content_id' => $contentId	
			));
			
			$dw->save();
			
			$existingWatch = $dw->getMergedData();
		}
		
		return $existingWatch;
	}

	public function watchContent($contentType, $contentId, $watchUserId = null, $watchDate = null)
	{
		$visitor = XenForo_Visitor::getInstance();

		if ($watchUserId === null)
		{
			$watchUserId = $visitor['user_id'];
		}
		if (!$watchUserId)
		{
			return false;
		}

		if ($watchUserId != $visitor['user_id'])
		{
			$user = $this->getModelFromCache('XenForo_Model_User')->getUserById($watchUserId);
			if (!$user)
			{
				return false;
			}

			$watchUsername = $user['username'];
		}
		else
		{
			$watchUsername = $visitor['username'];
		}

		if ($watchDate === null)
		{
			$watchDate = XenForo_Application::$time;
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$result = $db->query('
			INSERT IGNORE INTO sonnb_xengallery_watch
				(content_type, content_id, user_id, username, watch_date)
			VALUES
				(?, ?, ?, ?, ?)
		', array($contentType, $contentId, $watchUserId, $watchUsername, $watchDate));

		if (!$result->rowCount())
		{
			XenForo_Db::commit($db);
			return false;
		}

		XenForo_Db::commit($db);

		return true;
	}

	public function unwatchContent(array $watch)
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$result = $db->query('
			DELETE FROM sonnb_xengallery_watch
			WHERE watch_id = ?
		', $watch['watch_id']);

		if (!$result->rowCount())
		{
			XenForo_Db::commit($db);
			return false;
		}

		XenForo_Db::commit($db);

		return true;
	}
	
	public function sendAlertToWatchedUsersByContentId($contentType, $contentId, $alertUser, $action, array $extra = array(), array $ignoredUsers = array())
	{
		$conditions = array(
			'content_id' => $contentId,
			'content_type' => $contentType	
		);
		$fetchOptions = array(
			'join' => self::FETCH_USER_FULL	
		);
		
		$watchers = $this->getWatches($conditions, $fetchOptions);
		
		if (!$watchers)
		{
			return false;
		}
		
		$xfContentType = $this->_getXfContentType($contentType);
		
		$db = $this->_getDb();
		
		$values = array();
		$userAlertUpdates = array();	
		
		foreach ($watchers as $user)
		{
			if (!empty($ignoredUsers) && in_array($user['user_id'], $ignoredUsers))
			{
				continue;
			}

			$userModel = $this->getModelFromCache('XenForo_Model_User');
			if ($user['user_id'] != $alertUser['user_id'] &&
					!$userModel->isUserIgnored($user, $alertUser['user_id']) &&
					XenForo_Model_Alert::userReceivesAlert($user, $xfContentType, $action)
			)
			{
				//TODO: Send email to user if he/she accepts emails
				// comment/add_photo/add_video.
				/*
				 $mail = XenForo_Mail::create(
					$emailTemplate,
					array(
						'reply' => $reply,
						'thread' => $thread,
						'forum' => $thread,
						'receiver' => $user
					), $user['language_id']);

				$mail->enableAllLanguagePreCache();
				$mail->queue($user['email'], $user['username']);
				 */

				$dataArray = array(
					$user['user_id'],
					$db->quote($alertUser['user_id']),
					$db->quote($alertUser['username']),
					$db->quote($xfContentType),
					$db->quote($contentId),
					$db->quote($action),
					XenForo_Application::$time,
					"'".serialize($extra)."'"
				);
				
				$values[] = '('. implode(',', $dataArray).')';
				$userAlertUpdates[] = $user['user_id'];
			}
		}
		
		if ($values)
		{
			$query = '
					INSERT INTO `xf_user_alert`
						(alerted_user_id, user_id, username, content_type, content_id, action, event_date, extra_data)
					VALUES
							' . implode(', ', $values) . '
					';
			
			$db->query($query);
		}
		
		if ($userAlertUpdates)
		{
			$query = 'UPDATE `xf_user` SET
				alerts_unread = alerts_unread + 1
				WHERE user_id IN ('. $db->quote($userAlertUpdates) .');';
						
			$db->query($query);
		}
		
		return true;
	}
	
	public function prepareWatchFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';
		
		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';
		
			switch ($fetchOptions['order'])
			{
				case 'watch_id':
				case 'content_type':
				case 'content_id':
				case 'user_id':
					$orderBy = 'watch.' . $fetchOptions['order'];
					$orderBySecondary = ', watch.watch_date DESC';
					break;
				case 'watch_date':
				default:
					$orderBy = 'watch.watch_date';
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
					IF(user.username IS NULL, watch.username, user.username) AS username, user.avatar_date, user.gravatar';
				$joinTables .= '
					LEFT JOIN `xf_user` AS user ON
						(user.user_id = watch.user_id)';
			}
			
			if ($fetchOptions['join'] & self::FETCH_USER_FULL)
			{
				$selectFields .= ',
					user_profile.*, user_option.*, user_privacy.*';
				$joinTables .= '
					INNER JOIN xf_user_profile AS user_profile ON
						(user_profile.user_id = watch.user_id)
					INNER JOIN xf_user_option AS user_option ON
						(user_option.user_id = watch.user_id)
					INNER JOIN xf_user_privacy AS user_privacy ON
						(user_privacy.user_id = watch.user_id)';
			}
		}
		
		return array(
				'selectFields' => $selectFields,
				'joinTables' => $joinTables,
				'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}
	
	public function prepareWatchConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
		
		if (!empty($conditions['watch_id']))
		{
			if (is_array($conditions['watch_id']))
			{
				$sqlConditions[] = 'watch.watch_id IN (' . $db->quote($conditions['watch_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'watch.watch_id = ' . $db->quote($conditions['watch_id']);
			}
		}
		
		if (isset($conditions['user_id']))
		{
			if (is_array($conditions['user_id']))
			{
				$sqlConditions[] = 'watch.user_id IN (' . $db->quote($conditions['user_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'watch.user_id = ' . $db->quote($conditions['user_id']);
			}
		}
		
		if (!empty($conditions['content_type']))
		{
			if (is_array($conditions['content_type']))
			{
				$sqlConditions[] = 'watch.content_type IN (' . $db->quote($conditions['content_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'watch.content_type = ' . $db->quote($conditions['content_type']);
			}
		}
		
		if (!empty($conditions['content_id']))
		{
			if (is_array($conditions['content_id']))
			{
				$sqlConditions[] = 'watch.content_id IN (' . $db->quote($conditions['content_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'watch.content_id = ' . $db->quote($conditions['content_id']);
			}
		}
		
		if (!empty($conditions['watch_date']) && is_array($conditions['watch_date']))
		{
			list($operator, $cutOff) = $conditions['watch_date'];
		
			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "watch.watch_date $operator " . $db->quote($cutOff);
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
}
