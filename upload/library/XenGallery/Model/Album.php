<?php

class XenGallery_Model_Album extends XenForo_Model
{
	const FETCH_USER = 0x01;
	const FETCH_USER_OPTION = 0x02;
	const FETCH_PRIVACY = 0x04;
	const FETCH_VIEW_PERM = 0x08;
	const FETCH_ADD_PERM = 0x10;
	
	public static $voteThreshold = 10;
	public static $averageVote = 3;
		
	/**
	 * Gets a single album record specified by its ID
	 *
	 * @param integer $albumId
	 *
	 * @return array
	 */
	public function getAlbumById($albumId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareAlbumFetchOptions($fetchOptions);
		
		return $this->_getDb()->fetchRow('
			SELECT album.*, permission.permission,
				permission.access_type, permission.share_users
				' . $joinOptions['selectFields'] . '
			FROM xengallery_album AS album
			LEFT JOIN xengallery_album_permission AS permission ON
				(album.album_id = permission.album_id
					AND permission.permission = \'view\')
				' . $joinOptions['joinTables'] . '
			WHERE album.album_id = ?
		', $albumId);
	}

	public function getAlbumByIdSimple($albumId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xengallery_album
			WHERE album_id = ?
		', $albumId);
	}
	
	/**
	 * Gets album records specified by their IDs
	 *
	 * @param array $albumIds
	 *
	 * @return array
	 */
	public function getAlbumsByIds($albumIds, array $fetchOptions = array())
	{
		if (!$albumIds)
		{
			return array();
		}

		$db = $this->_getDb();
		$joinOptions = $this->prepareAlbumFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT album.*, permission.*
				' . $joinOptions['selectFields'] . '
			FROM xengallery_album AS album
			LEFT JOIN xengallery_album_permission AS permission ON
				(album.album_id = permission.album_id
					AND permission.permission = \'view\')
				' . $joinOptions['joinTables'] . '
			WHERE album.album_id IN (' . $db->quote($albumIds) . ')
		', 'album_id');
	}

	public function getAlbumIdsInRange($start, $limit)
	{
		$db = $this->_getDb();

		return $db->fetchCol($db->limit('
			SELECT album_id
			FROM xengallery_album
			WHERE album_id > ?
			ORDER BY album_id
		', $limit), $start);
	}

	public function getSharedAlbumIdsInRange($start, $limit)
	{
		$db = $this->_getDb();

		return $db->fetchCol($db->limit('
			SELECT album.album_id
			FROM xengallery_album AS album
			INNER JOIN xengallery_album_permission AS perm ON
				(album.album_id = perm.album_id AND perm.permission = \'view\')
			WHERE album.album_id > ?
				AND perm.access_type IN(\'shared\', \'followed\')
			ORDER BY album_id
		', $limit), $start);
	}
	
	/**
	 * Gets albums based on various criteria
	 *
	 * @param array $condtions
	 * @param array $fetchOptions 
	 *
	 * @return array
	 */
	public function getAlbums(array $conditions = array(), array $fetchOptions = array())
	{
		$bind = array();
		
		$whereClause = $this->prepareAlbumConditions($conditions, $fetchOptions);
		
		$joinOptions = $this->prepareAlbumFetchOptions($fetchOptions, $conditions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$sqlClauses = $this->prepareAlbumFetchOptions($fetchOptions, $conditions);

		$albums = $this->fetchAllKeyed($this->limitQueryResults('
			SELECT album.*, permission.*
				' . $joinOptions['selectFields'] . '
			FROM xengallery_album AS album
				' . $joinOptions['joinTables'] . '
			LEFT JOIN xengallery_album_permission AS permission ON
				(album.album_id = permission.album_id
					AND permission.permission = \'view\')
			WHERE ' . $whereClause . '
				' . $sqlClauses['orderClause'] . '			
			', $limitOptions['limit'], $limitOptions['offset']
		), 'album_id', $bind);
		
		return $albums;
	}

	/**
	 * Gets albums by add permission
	 *
	 * @param array $condtions
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function getAlbumsByAddPermission(array $conditions = array(), array $fetchOptions = array())
	{
		$whereClause = $this->prepareAlbumConditions($conditions, $fetchOptions);

		$joinOptions = $this->prepareAlbumFetchOptions($fetchOptions, $conditions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$sqlClauses = $this->prepareAlbumFetchOptions($fetchOptions, $conditions);

		$albums = $this->fetchAllKeyed($this->limitQueryResults('
			SELECT album.*, addmap.add_user_id, addperm.access_type,
				addperm.permission, addperm.share_users, viewperm.access_type AS view_access_type,
				viewperm.permission AS view_permission, viewperm.share_users AS view_share_users
				' . $joinOptions['selectFields'] . '
			FROM xengallery_album AS album
			LEFT JOIN xengallery_add_map AS addmap ON
				(addmap.album_id = album.album_id AND addmap.add_user_id = ' . $this->_getDb()->quote($conditions['add_user_id']) . ')
			LEFT JOIN xengallery_album_permission AS addperm ON
				(addperm.album_id = album.album_id AND addperm.permission = \'add\')
			LEFT JOIN xengallery_album_permission AS viewperm ON
				(viewperm.album_id = album.album_id AND viewperm.permission = \'view\')
			' . $joinOptions['joinTables'] . '
			WHERE ' . $whereClause . '
			' . $sqlClauses['orderClause'] . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'album_id');

		return $albums;
	}

	public function groupAlbumsByUser(array $albums)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$groupedAlbums = array();
		$users = array();

		foreach ($albums AS &$album)
		{
			$album['albumPermissions']['view'] = array(
				'access_type' => $album['view_access_type'],
				'permission' => $album['view_permission'],
				'shareUsers' => @unserialize($album['view_share_users'])
			);

			if (!$this->canViewAlbum($album, $null, $viewingUser, true))
			{
				continue;
			}

			if (!isset($users[$album['album_user_id']]))
			{
				$users[$album['album_user_id']] = array(
					'user_id' => $album['album_user_id'],
					'username' => $album['album_username']
				);
			}

			$groupedAlbums[$album['album_user_id']][$album['album_id']] = $album;
		}

		// Places the viewing user first in the $users array.
		if (isset($users[$viewingUser['user_id']]))
		{
			$visitor = $users[$viewingUser['user_id']];
			unset ($users[$viewingUser['user_id']]);

			$users = array_merge(array($visitor['user_id'] => $visitor), $users);
		}

		return array($users, $groupedAlbums);
	}
	
	public function getSharedAlbums(array $conditions = array(), array $fetchOptions = array())
	{
		$whereClause = $this->prepareAlbumConditions($conditions, $fetchOptions);
		
		$joinOptions = $this->prepareAlbumFetchOptions($fetchOptions, $conditions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$sqlClauses = $this->prepareAlbumFetchOptions($fetchOptions, $conditions);
		
		$albums = $this->fetchAllKeyed($this->limitQueryResults('
			SELECT sharedmap.*, album.*, permission.*
				' . $joinOptions['selectFields'] . '
			FROM xengallery_shared_map AS sharedmap
			INNER JOIN xengallery_album AS album ON
				(album.album_id = sharedmap.album_id)
			LEFT JOIN xengallery_album_permission AS permission ON
				(album.album_id = permission.album_id
					AND permission.permission = \'view\')
				' . $joinOptions['joinTables'] . '
				WHERE ' . $whereClause . '
				' . $sqlClauses['orderClause'] . '			
			', $limitOptions['limit'], $limitOptions['offset']
		), 'album_id');
		
		return $albums;
	}

	public function prepareAlbumWithPermissions(array $album)
	{
		$albumPermissions = $this->getUserAlbumPermissions($album['album_id']);

		$users = $this->getUsersByAlbumPermissionCacheValue($albumPermissions['view'], $albumPermissions['add']);
		foreach ($albumPermissions AS &$permission)
		{
			$permission = $this->prepareUserAlbumPermission($permission, $users);

			foreach ($permission['shareUsers'] AS &$shareUser)
			{
				if (!$shareUser['shared_user_id'] || !isset($users[$shareUser['shared_user_id']]))
				{
					continue;
				}

				$shareUser = $users[$shareUser['shared_user_id']];
			}
		}
		$album['albumPermissions'] = $this->prepareAlbumPermissionPhrases($albumPermissions);

		return $album;
	}

	public function getUserAlbumPermissions($albumId)
	{
		$permissions = $this->fetchAllKeyed('
			SELECT *
			FROM xengallery_album_permission
			WHERE album_id = ?
		', 'permission', $albumId);

		if (!isset($permissions['view']))
		{
			$permissions['view'] = $this->getDefaultAlbumPermission($albumId);
		}

		if (!isset($permissions['add']))
		{
			$permissions['add'] = $this->getDefaultAlbumPermission($albumId, 'add');
		}

		return $permissions;
	}

	public function getDefaultAlbumPermission($albumId, $type = null)
	{
		return array(
			'album_id' => $albumId,
			'permission' => $type ? 'add' : '',
			'access_type' => $type ? 'private' : '',
			'share_users' => ''
		);
	}

	public function getUserAlbumPermission($albumId, $permission)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xengallery_album_permission
			WHERE album_id = ?
				AND permission = ?
		', array($albumId, $permission));
	}

	/**
	 * Checks that the viewing user may managed a reported album
	 *
	 * @param array $album
	 * @param string $errorPhraseKey
	 * @param array $viewingUser
	 *
	 * @return boolean
	 */
	public function canManageReportedAlbum(array $album, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		return (
			XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteAlbumAny')
			|| XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editAlbumAny')
		);
	}

	public function isUserMappedToAlbum($albumId, $userId, $type)
	{
		$table = 'xengallery_' . $type . '_map';
		$userField = $type . '_user_id';

		return $this->_getDb()->fetchOne("SELECT album_id FROM $table WHERE $userField = $userId AND album_id = $albumId");
	}

	public function mapUserToAlbum($albumId, $userId, $type)
	{
		$table = 'xengallery_' . $type . '_map';
		$userField = $type . '_user_id';

		$this->_getDb()->query("
			INSERT IGNORE INTO $table
				(album_id, $userField)
			VALUES
				($albumId, $userId)
		");
	}

	public function prepareAlbumPermissionPhrases(array $permissions)
	{
		foreach ($permissions AS &$permission)
		{
			$add = $permission['permission'] == 'add' ? 'add_' : '';
			switch ($permission['access_type'])
			{
				case 'public':

					$permission['albumPrivacy'] = new XenForo_Phrase('xengallery_everyone');
					$permission['privacyExplain'] = new XenForo_Phrase('xengallery_public_' . $add . 'explain_mini');
					break;

				case 'members':

					$permission['albumPrivacy'] = new XenForo_Phrase('xengallery_members_only');
					$permission['privacyExplain'] = new XenForo_Phrase('xengallery_members_only_' . $add . 'explain_mini');
					break;

				case 'followed':

					$permission['albumPrivacy'] = new XenForo_Phrase('xengallery_people_you_follow');
					$permission['privacyExplain'] = new XenForo_Phrase('xengallery_people_you_follow_' . $add . 'explain_mini');
					break;

				case 'shared':

					$permission['albumPrivacy'] = new XenForo_Phrase('xengallery_custom_users');
					$permission['privacyExplain'] = new XenForo_Phrase('xengallery_shared_' . $add . 'explain_mini');
					break;

				case 'private':
				default:

					$permission['albumPrivacy'] = new XenForo_Phrase('xengallery_owner_only');
					$permission['privacyExplain'] = new XenForo_Phrase('xengallery_private_' . $add . 'explain_mini');
					break;
			}
		}

		return $permissions;
	}

	public function getUsersByAlbumPermissionCacheValue($viewPermission, $addPermission)
	{
		$userIds = array();

		if (is_array($viewPermission))
		{
			if ($viewPermission['access_type'] == 'shared'
				|| $viewPermission['access_type'] == 'followed'
			)
			{
				$shareUsers = $this->unserializeShareUsers($viewPermission['share_users']);
				if ($shareUsers)
				{
					foreach ($shareUsers AS $shareUser)
					{
						$userIds[] = $shareUser['shared_user_id'];
					}
				}
			}
		}

		if (is_array($addPermission))
		{
			if ($addPermission['access_type'] == 'shared'
				|| $addPermission['access_type'] == 'followed'
			)
			{
				$shareUsers = $this->unserializeShareUsers($addPermission['share_users']);
				if ($shareUsers)
				{
					foreach ($shareUsers AS $shareUser)
					{
						$userIds[] = $shareUser['shared_user_id'];
					}
				}
			}
		}

		$users = array();
		if ($userIds)
		{
			$users = $this->getModelFromCache('XenForo_Model_User')->getUsersByIds($userIds);
		}

		return $users;
	}

	public function unserializeShareUsers($shareUsers)
	{
		$shareUsers = @unserialize($shareUsers);
		if (!is_array($shareUsers))
		{
			$shareUsers = array();
		}

		return $shareUsers;
	}

	public function prepareUserAlbumPermission($permission, array $users)
	{
		$permission['shareUsernames'] = array();

		$permission['shareUsers'] = @unserialize($permission['share_users']);
		if ($permission['shareUsers'])
		{
			foreach ($permission['shareUsers'] AS $shareUser)
			{
				$userId = $shareUser['shared_user_id'];

				if (!empty($users[$userId]['username']))
				{
					$permission['shareUsernames'][] = $users[$userId]['username'];
				}
			}
		}
		else
		{
			$permission['shareUsers'] = array();
		}

		return $permission;
	}
	
	/**
	 * Gets a list of media Ids based on the album Id
	 * 
	 * @param int $albumId
	 * 
	 * @return array
	 */
	public function getMediaIdsFromAlbum($albumId)
	{
		return $this->_getDb()->fetchCol('
			SELECT media_id
			FROM xengallery_media
			WHERE album_id = ?
		', $albumId);
	}

	public function countAlbums(array $conditions = array(), $fetchOptions = array())
	{
		$whereClause = $this->prepareAlbumConditions($conditions, $fetchOptions);

		$joinOptions = $this->prepareAlbumFetchOptions($fetchOptions, $conditions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xengallery_album AS album
			LEFT JOIN xengallery_album_permission AS permission ON
				(album.album_id = permission.album_id AND permission.permission = \'view\')
			' . $joinOptions['joinTables'] . '
			WHERE ' . $whereClause
		);
	}

	public function rebuildUserAlbumCounts(array $userIds)
	{
		if (!is_array($userIds))
		{
			return false;
		}

		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		foreach ($userIds AS $userId)
		{
			$albumCount = $db->fetchOne('
				SELECT COUNT(*)
				FROM xengallery_album
				WHERE album_user_id = ?
					AND album_state = \'visible\'
			', $userId);

			$db->update('xf_user', array('xengallery_album_count' => $albumCount), 'user_id = ' . $db->quote($userId));
		}

		XenForo_Db::commit($db);

		return true;
	}

	public function getTopContributors($limit)
	{
		return $this->_getDb()->fetchAll('
			SELECT user.user_id, user.username,
				user.xengallery_media_count, user.avatar_date,
				user.gravatar, user.avatar_width,
				user.avatar_height, user.display_style_group_id
			FROM xf_user AS user
			WHERE xengallery_media_count > 0
			ORDER BY xengallery_media_count DESC
			LIMIT ?
		', $limit);
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
	
	/**
	 * Prepares the users to be shared with and returns the data for the cache.
	 * 
	 * @param array $users
	 * @param array $album
	 * @param string $type
	 */
	public function prepareViewShareUsers(array $users, array $album)
	{
		$db = $this->_getDb();
		
		XenForo_Db::beginTransaction($db);

		// Delete all shared users EXCEPT the owner.
		$db->delete('xengallery_shared_map', 'album_id = ' . $db->quote($album['album_id']) . ' AND shared_user_id != ' . $db->quote($album['album_user_id']));

		$shareUsers = array();
		if (isset($users['user_id']))
		{
			$data = array(
				'album_id' => $album['album_id'],
				'shared_user_id' => $users['user_id']
			);

			$shareUsers[$users['user_id']] = $data;

			$db->query('
				INSERT IGNORE INTO xengallery_shared_map
					(album_id, shared_user_id)
				VALUES
					(?, ?)
			', $data);
		}
		else
		{
			foreach ($users AS $userId => $user)
			{
				$data = array(
					'album_id' => $album['album_id'],
					'shared_user_id' => $userId
				);

				$shareUsers[$userId] = $data;

				$db->query('
					INSERT IGNORE INTO xengallery_shared_map
						(album_id, shared_user_id)
					VALUES
						(?, ?)
				', $data);
			}
		}
		
		XenForo_Db::commit($db);

		return $shareUsers;
	}

	/**
	 * Prepares the users to be shared with and returns the data for the cache.
	 *
	 * @param array $users
	 * @param array $album
	 * @param string $type
	 */
	public function prepareAddShareUsers(array $users, array $album)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$db->delete('xengallery_add_map', 'album_id = ' . $album['album_id']);
		
		$shareUsers = array();
		if (isset($users['user_id']))
		{
			$data = array(
				'album_id' => $album['album_id'],
				'shared_user_id' => $users['user_id']
			);

			$shareUsers[$users['user_id']] = $data;

			$db->query('
				INSERT IGNORE INTO xengallery_add_map
					(album_id, add_user_id)
				VALUES
					(?, ?)
			', $data);
		}
		else
		{
			foreach ($users AS $userId => $user)
			{
				$data = array(
					'album_id' => $album['album_id'],
					'shared_user_id' => $userId
				);

				$shareUsers[$userId] = $data;

				$db->query('
					INSERT IGNORE INTO xengallery_add_map
						(album_id, add_user_id)
					VALUES
						(?, ?)
				', $data);
			}
		}

		XenForo_Db::commit($db);

		return $shareUsers;
	}
	
	/**
	 * Unshares an album and rebuilds the permissions cache
	 * 
	 * @param int $userId
	 * @param array $albumId
	 */
	public function unshare($userId, array $album)
	{
		$shareUsers = @unserialize($album['share_users']);
		
		if ($shareUsers !== false)
		{
			$db = $this->_getDb();

			XenForo_Db::beginTransaction($db);

			$db->delete('xengallery_shared_map', "album_id = $album[album_id] AND shared_user_id = $userId");

			$alerts = $db->fetchAll('
				SELECT *
				FROM xf_user_alert
				WHERE alerted_user_id = ?
					AND content_type = \'xengallery_album\'
					AND action = \'share\'
					AND content_id = ?
			', array($userId, $album['album_id']));

			foreach ($alerts AS $alert)
			{
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_Alert');
				$dw->setExistingData($alert, true);
				$dw->delete();
			}

			XenForo_Db::commit($db);

			unset($shareUsers[$userId]);

			return $shareUsers;
		}
	}

	public function writeAlbumPermission(array $permissionData, array $album)
	{
		$albumPermDw = XenForo_DataWriter::create('XenGallery_DataWriter_AlbumPermission');
		$albumPermDw->setImportMode(false);

		$existing = $this->getUserAlbumPermission($permissionData['album_id'], $permissionData['permission']);
		if ($existing)
		{
			$albumPermDw->setExistingData($existing);
		}

		if (isset($album['album_user_id']))
		{
			$albumPermDw->setExtraData(XenGallery_DataWriter_AlbumPermission::DATA_ALBUM_USER_ID, $album['album_user_id']);
		}

		$albumPermDw->bulkSet($permissionData);
		$albumPermDw->save();

		$this->_logChanges($albumPermDw, $album, 'permission', $permissionData);
	}

	protected function _logChanges(XenForo_DataWriter $dw, array $content, $action, array $additionalChanges = array(), $contentType = 'xengallery_album', $userIdKey = 'album_user_id')
	{
		if (!empty($content[$userIdKey]) && XenForo_Visitor::getUserId() != $content[$userIdKey])
		{
			$basicLog = array();

			if ($dw->isUpdate())
			{
				$basicLog = $this->_getLogChanges($dw);
			}

			$changes = array_merge($basicLog, $additionalChanges);

			if ($changes)
			{
				XenForo_Model_Log::logModeratorAction($contentType, $content, $action, $changes);
			}
		}
	}

	protected function _getLogChanges(XenForo_DataWriter $dw)
	{
		$newData = $dw->getMergedNewData();
		$oldData = $dw->getMergedExistingData();
		$changes = array();

		foreach ($newData AS $key => $newValue)
		{
			if (isset($oldData[$key]))
			{
				$changes[$key] = $oldData[$key];
			}
		}

		return $changes;
	}

	/**
	 * Logs the viewing of an album.
	 *
	 * @param integer $albumId
	 */
	public function logAlbumView($albumId)
	{
		$this->_getDb()->query('
			INSERT ' . (XenForo_Application::getOptions()->enableInsertDelayed ? 'DELAYED' : '') . ' INTO xengallery_album_view
				(album_id)
			VALUES
				(?)
		', $albumId);
	}

	/**
	 * Updates album views in bulk.
	 */
	public function updateAlbumViews()
	{
		$db = $this->_getDb();

		$updates = $db->fetchPairs('
			SELECT album_id, COUNT(*)
			FROM xengallery_album_view
			GROUP BY album_id
		');

		XenForo_Db::beginTransaction($db);

		$db->query('TRUNCATE TABLE xengallery_album_view');

		foreach ($updates AS $albumId => $views)
		{
			$db->query('
				UPDATE xengallery_album SET
					album_view_count = album_view_count + ?
				WHERE album_id = ?
			', array($views, $albumId));
		}

		XenForo_Db::commit($db);
	}
	
	
	public function prepareAlbum(array $album)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$album['album_title'] = XenForo_Helper_String::censorString($album['album_title']);
		$album['album_description'] = XenForo_Helper_String::censorString($album['album_description']);

		$album['isIgnored'] = array_key_exists($album['album_user_id'], $viewingUser['ignoredUsers']);

		$album['mediaCache'] = @unserialize($album['media_cache']);
		if ($album['mediaCache'])
		{
			$i = 0;
			$thumbnails = array();
			foreach ($album['mediaCache'] AS $cache)
			{
				$i++;
				$separator = '?';
				if (strstr($cache['thumbnailUrl'], '?'))
				{
					$separator = '&';
				}

				$cacheBuster = isset($cache['last_edit_date']) ? $cache['last_edit_date'] : XenForo_Application::$time;
				$thumbnails[] = $cache['thumbnailUrl'] . $separator . $cacheBuster;
			}

			$album['mediaCache']['thumbnails'] = $thumbnails;
			$album['mediaCache']['placeholder'] = reset($thumbnails);
		}

		if ($album['album_thumbnail_date'] > 0)
		{
			$thumbnails = array($this->getAlbumThumbnailUrl($album['album_id']) . '?' . $album['album_thumbnail_date']);

			$album['mediaCache']['thumbnails'] = $thumbnails;
			$album['mediaCache']['placeholder'] = reset($thumbnails);
		}

		$album['albumFieldCache'] = array();

		$fieldCache = $this->_getFieldModel()->getGalleryFieldCache();
		foreach ($fieldCache AS $key => $field)
		{
			if (!empty($field['album_use']))
			{
				$album['albumFieldCache'][$field['display_group']][$key] = $field['field_id'];
			}
		}

		if (isset($album['access_type']))
		{
			$album['privacyPhrase'] = new XenForo_Phrase('xengallery_album_' . $album['access_type']);
		}

		$album['canUploadImage'] = $this->canUploadImage();
		$album['canUploadVideo'] = $this->canUploadVideo();
		$album['canEmbedVideo'] = $this->canEmbedVideo();

		$album['likeUsers'] = false;
		$album['liked'] = false;

		if (!empty($album['album_like_users']))
		{
			$album['likeUsers'] = @unserialize($album['album_like_users']);

			if (is_array($album['likeUsers']))
			{
				foreach ($album['likeUsers'] AS $likeUser)
				{
					if ($likeUser['user_id'] == $viewingUser['user_id'])
					{
						$album['liked'] = true;
					}
				}
			}
		}

		$album['canLikeAlbum'] = $this->canLikeAlbum($album, $null, $viewingUser);
		$album['canRateAlbum'] = $this->canRateAlbum($album, $null, $viewingUser);

		if (isset($album['album_user_id']))
		{
			$album['customUserFields'] = @unserialize($album['custom_fields']);
		}

		return $album;
	}

	public function prepareAlbums(array $albums)
	{
		foreach ($albums AS &$album)
		{
			$album = $this->prepareAlbum($album);
		}

		return $albums;
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
				WHERE content_type = \'xengallery_album\'
				AND like_user_id = ?
			) AS temp
			INNER JOIN xengallery_album AS album ON (album.album_id = temp.content_id)
			SET album_like_users = REPLACE(album_like_users, ' .
				$db->quote('i:' . $oldUserId . ';s:8:"username";s:' . strlen($oldUsername) . ':"' . $oldUsername . '";') . ', ' .
				$db->quote('i:' . $newUserId . ';s:8:"username";s:' . strlen($newUsername) . ':"' . $newUsername . '";') . ')
		', $newUserId);
	}

	public function prepareAlbumThumbs(array $media, array $album)
	{
		$mediaCache = @unserialize($album['media_cache']);
		if (!is_array($mediaCache))
		{
			return $media;
		}

		$checkedMedia = array();
		$uncheckedMedia = array();

		foreach ($mediaCache AS $mediaId => $item)
		{
			if (!isset($media[$mediaId]))
			{
				continue;
			}

			$item = $media[$mediaId];
			$item['checked'] = true;

			$checkedMedia[$mediaId] = $item;
		}

		foreach ($media AS $mediaId => $item)
		{
			if (!isset($mediaCache[$mediaId]))
			{
				$uncheckedMedia[$mediaId] = $item;
			}
		}

		return $checkedMedia + $uncheckedMedia;
	}

	public function uploadAlbumThumbnail(XenForo_Upload $upload, $albumId)
	{
		if (!$albumId)
		{
			throw new XenForo_Exception('Missing user ID.');
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

		return $this->processAlbumThumbnail($albumId, $baseTempFile, $imageType, $width, $height);
	}

	public function deleteAlbumThumbnail($albumId)
	{
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
		$dw->setExistingData($albumId);
		$dw->set('album_thumbnail_date', 0);
		$dw->save();

		$filePath = $this->getAlbumThumbnailFilePath($albumId);
		@unlink($filePath);

		$filePath = $this->getAlbumThumbnailFilePath($albumId, true);
		@unlink($filePath);
	}

	public function processAlbumThumbnail($albumId, $fileName, $imageType = false, $width = false, $height = false)
	{
		if (!$imageType || !$width || !$height)
		{
			$imageInfo = getimagesize($fileName);
			if (!$imageInfo)
			{
				throw new XenForo_Exception('Non-image passed in to albumThumbnail');
			}
			$imageType = $imageInfo[2];
		}

		if (!in_array($imageType, array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG)))
		{
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		}

		$options = XenForo_Application::getOptions();

		$thumbFile = $this->getAlbumThumbnailFilePath($albumId);
		XenForo_Helper_File::createDirectory(dirname($thumbFile));

		$thumbImage = new XenGallery_Helper_Image($fileName);
		if ($thumbImage)
		{
			$thumbImage->resize(
				$options->xengalleryThumbnailDimension['width'],
				$options->xengalleryThumbnailDimension['height'], 'crop'
			);

			$thumbnailed = $thumbImage->saveToPath($thumbFile);

			$originalFile = $this->getAlbumThumbnailFilePath($albumId, true);
			XenForo_Helper_File::createDirectory(dirname($originalFile));

			if ($thumbnailed)
			{
				$writeSuccess = XenForo_Helper_File::safeRename($fileName, $originalFile);
				if ($writeSuccess && file_exists($fileName))
				{
					@unlink($fileName);
				}
			}
			else
			{
				$writeSuccess = false;
			}

			if ($writeSuccess)
			{
				$albumDw = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
				$albumDw->setExistingData($albumId);

				$albumDw->bulkSet(array(
					'album_thumbnail_date' => XenForo_Application::$time,
					'media_cache' => array(),
					'manual_media_cache' => 0
				));

				$albumDw->save();
			}

			return $writeSuccess;
		}

		return false;
	}

	public function getAlbumThumbnailFilePath($albumId, $original = false)
	{
		if ($original)
		{
			return sprintf('%s/xengallery/originals/album/%d/%d.jpg',
				XenForo_Helper_File::getInternalDataPath(),
				floor($albumId / 1000),
				$albumId
			);
		}
		else
		{
			return sprintf('%s/xengallery/album/%d/%d.jpg',
				XenForo_Helper_File::getExternalDataPath(),
				floor($albumId / 1000),
				$albumId
			);
		}
	}

	public function getAlbumThumbnailUrl($albumId)
	{
		return sprintf('%s/xengallery/album/%d/%d.jpg',
			XenForo_Application::$externalDataUrl,
			floor($albumId / 1000),
			$albumId
		);
	}

	public function prepareInlineModOptions(array &$albums, $userPage = false)
	{
		$albumModOptions = array();

		foreach ($albums AS &$album)
		{
			$albumModOptions = $albumModOptions + $this->addInlineModOptionToAlbum($album, $album, $userPage);
		}

		return $albumModOptions;
	}

	/**
	 * Adds the canInlineMod value to the provided album and returns the
	 * specific list of inline mod actions that are allowed on this album.
	 *
	 * @param array $album Album info
	 * @param array $container Container (category or Album) the media is in
	 * @param array|null $viewingUser
	 *
	 * @return array List of allowed inline mod actions, format: [action] => true
	 */
	public function addInlineModOptionToAlbum(array &$album, array $container, $userPage = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$albumModOptions = array();

		$canInlineMod = ($viewingUser['user_id'] &&
			($this->canDeleteAlbum($album, 'soft', $errorPhraseKey, $viewingUser)
				|| $this->canEditAlbum($album, $errorPhraseKey, $viewingUser)
				|| $this->canChangeAlbumViewPerm($album, $errorPhraseKey, $viewingUser)
			)
		);

		if ($canInlineMod)
		{
			if ($this->canDeleteAlbum($album, 'soft', $errorPhraseKey, $viewingUser))
			{
				$albumModOptions['delete'] = true;
			}
			if ($this->canDeleteAlbum($album, 'soft', $errorPhraseKey, $viewingUser)
				&& ($viewingUser['is_staff']
					|| $viewingUser['is_moderator']
					|| $viewingUser['is_admin']
				)
			)
			{
				$albumModOptions['undelete'] = true;
			}
			if ($this->canEditAlbum($album, $errorPhraseKey, $viewingUser))
			{
				$albumModOptions['edit'] = true;
			}
			if ($this->canChangeAlbumViewPerm($album, $errorPhraseKey, $viewingUser))
			{
				$albumModOptions['privacy'] = true;
			}
		}

		$album['canInlineMod'] = (count($albumModOptions) > 0);

		return $albumModOptions;
	}

	/**
	 * Prepares join-related fetch options.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array Containing 'selectFields' and 'joinTables' keys.
	 */
	public function prepareAlbumFetchOptions(array $fetchOptions, array $conditions = array())
	{
				
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
					
				case 'album_create_date':
				case 'new':
				default:
					$orderBy = 'album.album_create_date';
					break;
					
				case 'album_id':
					$orderBy = 'album.album_create_date';
					$orderBySecondary = ', album.album_id DESC';
					break;
					
				case 'rating_avg':
					$orderBy = 'album.album_rating_avg';
					$orderBySecondary = ', album.album_create_date DESC';
					break;

				case 'rating_weighted':
					$orderBy = 'album.album_rating_weighted';
					$orderBySecondary = ', album.album_create_date DESC';
					break;

				case 'comment_count':
					$orderBy = 'album.album_comment_count';
					$orderBySecondary = ', album.album_create_date DESC';
					break;
					
				case 'view_count':
					$orderBy = 'album.album_view_count';
					$orderBySecondary = ', album.album_create_date DESC';
					break;
					
				case 'media_count':
					$orderBy = 'album.album_media_count';
					$orderBySecondary = ', album.album_media_count DESC';
					break;
					
				case 'rating_count':
					$orderBy = 'album.album_rating_count';
					$orderBySecondary = ', album.album_create_date DESC';
					break;											
					
				case 'likes':
					$orderBy = 'album.album_likes';
					$orderBySecondary = ', album.album_create_date DESC';
					break;

				case 'album_id_asc':
					$orderBy = 'album.album_id';
					break;
			}
			if (!isset($fetchOptions['orderDirection']) || $fetchOptions['orderDirection'] == 'desc')
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
			if (($fetchOptions['join'] & self::FETCH_PRIVACY)
				&& isset($conditions['privacyUserId'])
			)
			{
				$selectFields .= ',
					shared.shared_user_id, private.private_user_id';
				$joinTables .= '
					LEFT JOIN xengallery_shared_map AS shared ON
						(shared.album_id = album.album_id AND shared.shared_user_id = ' . $this->_getDb()->quote($conditions['privacyUserId']) . ')
					LEFT JOIN xengallery_private_map AS private ON
						(private.album_id = album.album_id AND private.private_user_id = ' .  $this->_getDb()->quote($conditions['privacyUserId']) .')';
			}
			
			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= ',
					user.*, user_profile.*, IF(user.username IS NULL, album.album_username, user.username) AS username';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = album.album_user_id)
					LEFT JOIN xf_user_profile AS user_profile ON
						(user_profile.user_id = album.album_user_id)';
			}
			
			if ($fetchOptions['join'] & self::FETCH_USER_OPTION)
			{
				$selectFields .= ',
					user_option.*';
				$joinTables .= '
					LEFT JOIN xf_user_option AS user_option ON
						(user_option.user_id = album.album_user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_VIEW_PERM)
			{
				$selectFields .= ',
					viewperm.*';
				$joinTables .= '
					LEFT JOIN xengallery_album_permission AS viewperm ON
						(album.album_id = viewperm.album_id AND viewperm.permission = \'view\')';
			}

			if ($fetchOptions['join'] & self::FETCH_ADD_PERM)
			{
				$selectFields .= ',
					addperm.*';
				$joinTables .= '
					LEFT JOIN xengallery_album_permission AS addperm ON
						(album.album_id = addperm.album_id AND addperm.permission = \'add\')';
			}
		}

		if (isset($fetchOptions['watchUserId']))
		{
			if (!empty($fetchOptions['watchUserId']))
			{
				$selectFields .= ',
					IF(album_watch.user_id IS NULL, 0, 1) AS album_is_watched';
				$joinTables .= '
					LEFT JOIN xengallery_album_watch AS album_watch
						ON (album_watch.album_id = album.album_id
						AND album_watch.user_id = ' . $this->_getDb()->quote($fetchOptions['watchUserId']) . ')';
			}
			else
			{
				$selectFields .= ',
					0 AS album_is_watched';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables,
			'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}
	
	/**
	 * Prepares a set of conditions against which to select albums.
	 *
	 * @param array $conditions List of conditions.
	 * @param array $fetchOptions The fetch options that have been provided. May be edited if criteria requires.
	 *
	 * @return string Criteria as SQL for where clause
	 */
	public function prepareAlbumConditions(array $conditions, array &$fetchOptions)
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

					$membersClause = '';
					if ($userId > 0)
					{
						$membersClause = 'OR permission.access_type = \'members\'';
					}

					$sqlConditions[] = '
						private.private_user_id IS NOT NULL
							OR shared.shared_user_id IS NOT NULL
							OR permission.access_type = \'public\'
						' . $membersClause . '
					';
				}
			}
		}

		if (!empty($conditions['add_user_id']))
		{
			$userId = 0;
			if (isset($conditions['add_user_id']))
			{
				$userId = $conditions['add_user_id'];
			}

			$membersOnlyClause = '';
			if ($userId > 0)
			{
				$membersOnlyClause = ' OR addperm.access_type = \'members\'';
			}

			$sqlConditions[] = '
				addmap.add_user_id IS NOT NULL
					OR addperm.access_type = \'public\'
					OR album.album_user_id = ' . $db->quote($userId)
					. $membersOnlyClause;
		}

		if (!empty($conditions['album_user_id']))
		{
			if (is_array($conditions['album_user_id']))
			{
				$sqlConditions[] = 'album.album_user_id IN (' . $db->quote($conditions['album_user_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'album.album_user_id = ' . $db->quote($conditions['album_user_id']);
			}
		}

		if (!empty($conditions['album_media_count']))
		{
			$sqlConditions[] = 'album.album_media_count ' . $conditions['album_media_count'];
		}

		if (isset($conditions['is_banned']))
		{
			$sqlConditions[] = 'user.is_banned = ' . ($conditions['is_banned'] ? 1 : 0);
		}
		
		if (!empty($conditions['shared_user_id']))
		{
			if (is_array($conditions['shared_user_id']))
			{
				$sqlConditions[] = 'shared.shared_user_id IN (' . $db->quote($conditions['shared_user_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'shared.shared_user_id = ' . $db->quote($conditions['shared_user_id']);
			}

			if (!is_array($conditions['shared_user_id']))
			{
				$sqlConditions[] = 'album.album_user_id != ' . $db->quote($conditions['shared_user_id']);
			}
		}

		if (!empty($conditions['album_id']))
		{
			if (is_array($conditions['album_id']))
			{
				$sqlConditions[] = 'album.album_id IN (' . $db->quote($conditions['album_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'album.album_id = ' . $db->quote($conditions['album_id']);
			}
		}

		if (!empty($conditions['access_type']))
		{
			if (is_array($conditions['access_type']))
			{
				$sqlConditions[] = 'permission.access_type IN (' . $db->quote($conditions['access_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'permission.access_type = ' . $db->quote($conditions['access_type']);
			}
		}
		
		if (!empty($conditions['public']))
		{
			$sqlConditions[] = "permission.access_type = 'public'";
		}

		if (!empty($conditions['view_type']))
		{
			$this->addFetchOptionJoin($fetchOptions, self::FETCH_VIEW_PERM);

			if (is_array($conditions['view_type']))
			{
				$sqlConditions[] = 'viewperm.access_type IN (' . $db->quote($conditions['view_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'viewperm.access_type = ' . $db->quote($conditions['view_type']);
			}
		}

		if (!empty($conditions['add_type']))
		{
			$this->addFetchOptionJoin($fetchOptions, self::FETCH_ADD_PERM);

			if (is_array($conditions['add_type']))
			{
				$sqlConditions[] = 'addperm.access_type IN (' . $db->quote($conditions['add_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'addperm.access_type = ' . $db->quote($conditions['add_type']);
			}
		}

		if (isset($conditions['deleted']))
		{
			$sqlConditions[] = $this->prepareStateLimitFromConditions($conditions, 'album', 'album_state');
		}
		else
		{
			// sanity check: only get visible albums unless we've explicitly said to get something else
			$sqlConditions[] = "album.album_state = 'visible'";
		}

		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function canViewAlbums(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewOverride'))
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewAlbums'))
		{
			return true;
		}		
		
		$errorPhraseKey = 'xengallery_no_album_view_permission';
		return false;
	}
	
	public function canViewAlbum(array $album, &$errorPhraseKey = '', array $viewingUser = null, $skipOverride = false)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$skipOverride)
		{
			if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewOverride'))
			{
				return true;
			}
		}

		if (!$this->canViewAlbums($errorPhraseKey, $viewingUser))
		{
			return false;
		}

		if ($album['album_state'] == 'deleted')
		{
			if (!$this->canViewDeletedAlbums($errorPhraseKey, $viewingUser))
			{
				return false;
			}
		}

		if (isset($album['albumPermissions']['view']))
		{
			$viewPermissions = $album['albumPermissions']['view'];
			if ($viewPermissions['access_type'] == 'public')
			{
				return true;
			}

			if ($viewPermissions['access_type'] == 'members')
			{
				if ($viewingUser['user_id'])
				{
					return true;
				}
			}

			if ($viewPermissions['access_type'] == 'private' && $viewingUser['user_id'] == $album['album_user_id'])
			{
				return true;
			}

			if ($viewPermissions['access_type'] == 'shared'
				|| $viewPermissions['access_type'] == 'followed'
			)
			{
				if ($viewingUser['user_id'] == $album['album_user_id'])
				{
					return true;
				}

				if (isset($viewPermissions['shareUsers'][$viewingUser['user_id']]))
				{
					return true;
				}
			}
		}
		
		$errorPhraseKey = 'xengallery_no_view_this_album_permission';
		return false;		
	}

	public function canWatchAlbum(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		return ($viewingUser['user_id'] ? true : false);
	}
	
	public function canCreateAlbum(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ((!$this->canUploadImage($errorPhraseKey, $viewingUser) && !$this->canUploadVideo($errorPhraseKey, $viewingUser) && !$this->canEmbedVideo($errorPhraseKey, $viewingUser)) || !$this->canViewAlbums($errorPhraseKey, $viewingUser))
		{
			$errorPhraseKey = 'xengallery_no_album_create_permission';
			return false;
		}
	
		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'createAlbum'))
		{
			return true;
		}
	
		$errorPhraseKey = 'xengallery_no_album_create_permission';
		return false;
	}

	public function canUploadImage(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'uploadImage'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_album_image_upload_permission';
		return false;
	}

	public function canUploadVideo(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'uploadVideo'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_album_video_upload_permission';
		return false;
	}

	public function canEmbedVideo(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$mediaSites = XenForo_Application::getOptions()->xengalleryMediaSites;
		if (count($mediaSites) && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'embedVideo'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_album_video_embed_permission';
		return false;
	}
	
	public function canLikeAlbum(array $album, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
	
		if ($album['album_user_id'] == $viewingUser['user_id'])
		{
			$errorPhraseKey = 'xengallery_you_cannot_like_your_own_album';
			return false;
		}
	
		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'likeAlbum'))
		{
			return true;
		}
	
		$errorPhraseKey = 'xengallery_no_like_permission';
		return false;
	}
	
	public function canViewDeletedAlbums(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewDeletedAlbums'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_view_deleted_album_permission';
		return false;		
	}	
	
	public function canLikeMedia(array $media, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if ($media['user_id'] == $viewingUser['user_id'])
		{
			$errorPhraseKey = 'xengallery_you_cannot_like_your_own_media';
			return false;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'like'))
		{
			return true;
		}
		
		$errorPhraseKey = 'xengallery_no_like_permission';
		return false;		
	}
	
	public function canDeleteAlbum(array $album, $type = 'soft', &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($type != 'soft' && !XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'hardDeleteAlbumAny'))
		{
			return false;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteAlbumAny'))
		{
			return true;
		}
		else if ($album['album_user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteAlbum'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_delete_album_permission';
		return false;
	}
	
	public function canEditAlbum(array $album, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editAlbumAny'))
		{
			return true;
		}

		if ($album['album_user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editAlbum'))
		{
			return true;
		}
		
		$errorPhraseKey = 'xengallery_no_edit_album_permission';
		return false;		
	}
	
	public function canRateAlbum(array $album, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}
		
		if ($album['album_user_id'] == $viewingUser['user_id'])
		{
			$errorPhraseKey = 'xengallery_no_rate_album_by_self';
			return false;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'rateAlbum'))
		{
			return true;
		}
		
		$errorPhraseKey = 'xengallery_no_rate_album_permission';
		return false;
	}

	public function canWarnAlbum(array $album, &$errorPhraseKey = '', array $viewingUser = null)
	{
		if (!empty($album['album_warning_id']) || empty($album['album_user_id']))
		{
			return false;
		}

		if (!empty($album['is_admin']) || !empty($album['is_moderator']))
		{
			return false;
		}

		$this->standardizeViewingUserReference($viewingUser);

		return ($viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'warnAlbum'));
	}

	public function canChangeCustomOrder(array $album, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($album['album_user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'customOrder'))
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'customOrderAny'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_change_order_permission';
		return false;
	}

	public function canChangeThumbnail(array $album, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($album['album_user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'albumThumbnail'))
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'albumThumbnailAny'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_change_thumbnail_permission';
		return false;
	}
	
	public function canShareAlbum(array $album, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if ($album['access_type'] != 'shared')
		{
			$errorPhraseKey = 'xengallery_cannot_share_an_album_that_not_shared';
			return false;
		}
	
		if ($album['album_user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'shareAlbum'))
		{
			return true;
		}
	
		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'shareAlbumAny'))
		{
			return true;
		}
	
		$errorPhraseKey = 'xengallery_no_share_album_permission';
		return false;
	}

	public function canChangeAlbumViewPerm(array $album, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (isset($album['add_user_id']))
		{
			$album['album_user_id'] = $album['add_user_id'];
		}

		if (!isset($album['album_user_id']))
		{
			$errorPhraseKey = 'xengallery_no_change_privacy_album_permission';
			return false;
		}

		if ($album['album_user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'changeViewPermission'))
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'changeViewPermissionAny'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_change_privacy_album_permission';
		return false;
	}

	public function canChangeAlbumAddPerm(array $album, &$errorPhraseKey = '', array $addingUser = null)
	{
		$this->standardizeViewingUserReference($addingUser);

		if (isset($album['add_user_id']))
		{
			$album['album_user_id'] = $album['add_user_id'];
		}

		if (!isset($album['album_user_id']))
		{
			$errorPhraseKey = 'xengallery_no_change_privacy_album_permission';
			return false;
		}

		if ($album['album_user_id'] == $addingUser['user_id'] && XenForo_Permission::hasPermission($addingUser['permissions'], 'xengallery', 'changeAddPermission'))
		{
			return true;
		}

		if (XenForo_Permission::hasPermission($addingUser['permissions'], 'xengallery', 'changeAddPermissionAny'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_change_privacy_album_permission';
		return false;
	}

	public function canAddMediaToAlbum(array $album = array(), &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$canAddMedia = true;
		if (!$this->getModelFromCache('XenGallery_Model_Media')->canAddMedia($errorPhraseKey, $viewingUser))
		{
			$errorPhraseKey = 'xengallery_you_cannot_add_media_to_this_album';
			$canAddMedia = false;
		}

		$uploadImage = false;
		if ($this->canUploadImage())
		{
			$uploadImage = true;
		}

		$uploadVideo = false;
		if ($this->canUploadVideo())
		{
			$uploadVideo = true;
		}

		$embedVideo = false;
		if ($this->canEmbedVideo())
		{
			$embedVideo = true;
		}

		if (!$uploadImage && !$uploadVideo && !$embedVideo)
		{
			$errorPhraseKey = 'xengallery_you_cannot_add_media_to_this_album';
			return false;
		}

		if (!$album && $canAddMedia)
		{
			return true;
		}

		if ($album && $canAddMedia)
		{
			$hasPermission = false;

			$addPermissions = $album['albumPermissions']['add'];
			switch($addPermissions['access_type'])
			{
				case 'public':

					$hasPermission = true;
					break;

				case 'members':

					if ($viewingUser['user_id'])
					{
						$hasPermission = true;
					}
					break;

				case 'shared':
				case 'followed':

					if (isset($addPermissions['shareUsers'][$viewingUser['user_id']])
						|| $album['album_user_id'] == $viewingUser['user_id']
					)
					{
						$hasPermission = true;
					}
					break;

				case 'private':

					if ($album['album_user_id'] == $viewingUser['user_id'])
					{
						$hasPermission = true;
					}
					break;
			}

			if (!$hasPermission)
			{
				$errorPhraseKey = 'xengallery_you_cannot_add_media_to_this_album';
				return false;
			}
		}

		if ($album && !$canAddMedia)
		{
			$errorPhraseKey = 'xengallery_you_cannot_add_media_to_this_album';
			return false;
		}

		return true;
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
	 * @return XenGallery_Model_Field
	 */
	protected function _getFieldModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Field');
	}
}
