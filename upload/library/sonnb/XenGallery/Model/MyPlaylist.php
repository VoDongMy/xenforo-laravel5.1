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
class sonnb_XenGallery_Model_MyPlaylist extends XenForo_Model
{
	const FETCH_USER = 0x01;
	const FETCH_PLAYLIST = 0x02;
	const FETCH_CONTENT = 0x04;

	public function getPlaylistById($id, array $fetchOptions = array())
	{
		if (empty($id))
		{
			return array();
		}

		$conditions = array('playlist_id' => $id);
		$playlist = $this->getPlaylists($conditions, $fetchOptions);

		if (!empty($playlist))
		{
			$playlist = reset($playlist);
		}

		return $playlist;
	}

	public function getPlaylistsByIds($ids, array $fetchOptions = array())
	{
		if (empty($ids))
		{
			return array();
		}

		$conditions = array('playlist_id' => $ids);

		return $this->getPlaylists($conditions, $fetchOptions);
	}

	public function getPlaylistsByUserId($userId, array $conditions = array(), array $fetchOptions = array())
	{
		if (empty($userId))
		{
			return array();
		}

		$conditions['user_id'] = $userId;

		return $this->getPlaylists($conditions, $fetchOptions);
	}

	public function getPlaylistByContentUserId($contentType, $contentId, $userId)
	{
		return $this->_getDb()->fetchRow("
			SELECT *
			FROM sonnb_xengallery_myplaylist_item AS myplaylist_item
			LEFT JOIN sonnb_xengallery_myplaylist AS myplaylist
				ON (myplaylist.playlist_id = myplaylist_item.playlist_id)
			WHERE myplaylist_item.content_type = ?
				AND myplaylist_item.content_id = ?
				AND myplaylist.user_id = ?",
			array($contentType, $contentId, $userId));
	}

	public function getPlaylistsByUserIds($userIds, array $conditions = array(), array $fetchOptions = array())
	{
		return $this->getPlaylistsByUserId($userIds, $conditions, $fetchOptions);
	}

	public function getPlaylists(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->preparePlaylistConditions($conditions, $fetchOptions);

		$sqlClauses = $this->preparePlaylistFetchOptions($fetchOptions);

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults('
			SELECT myplaylist.*
				' . $sqlClauses['selectFields'] . '
			FROM sonnb_xengallery_myplaylist AS myplaylist
				' . $sqlClauses['joinTables'] . '
			WHERE '. $whereConditions .'
				' . $sqlClauses['orderClause'] . '
		', $limitOptions['limit'], $limitOptions['offset']), 'playlist_id');
	}

	public function getPlaylistItems($conditions = array(), $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$whereConditions = $this->preparePlaylistItemConditions($conditions);

		$sqlClauses = $this->preparePlaylistItemFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults('
			SELECT myplaylist_item.*
				' . $sqlClauses['selectFields'] . '
			FROM sonnb_xengallery_myplaylist_item AS myplaylist_item
				' . $sqlClauses['joinTables'] . '
			WHERE '. $whereConditions .'
				' . $sqlClauses['orderClause'] . '
		', $limitOptions['limit'], $limitOptions['offset']), 'playlist_item_id');
	}

	public function getPlaylistItemsByPlaylistId($playlistId, array $conditions = array(), array $fetchOptions = array())
	{
		if (empty($playlistId))
		{
			return array();
		}

		$conditions['playlist_id'] = $playlistId;

		return $this->getPlaylistItems($conditions, $fetchOptions);
	}

	public function getPlaylistItemsByPlaylistIds($playlistIds, array $conditions = array(), array $fetchOptions = array())
	{
		return $this->getPlaylistItemsByPlaylistId($playlistIds, $conditions, $fetchOptions);
	}

	public function countPlaylists(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->preparePlaylistConditions($conditions, $fetchOptions);

		$db = $this->_getDb();
		return $db->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_myplaylist` AS myplaylist
                WHERE ' . $whereConditions . '
            ');
	}

	public function countPlaylistsByUserId($userId, array $conditions = array(), array $fetchOptions = array())
	{
		$conditions['user_id'] = $userId;

		return $this->countPlaylists($conditions, $fetchOptions);
	}

	public function countPlaylistItems(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->preparePlaylistItemConditions($conditions, $fetchOptions);

		$db = $this->_getDb();
		return $db->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_myplaylist_item` AS myplaylist_item
                WHERE ' . $whereConditions . '
            ');
	}

	public function preparePlaylist(array $playlist, $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!empty($playlist))
		{
			$playlist['thumbnail'] = $this->getPlaylistThumbnailUrl($playlist);

			$playlist['title'] = XenForo_Helper_String::censorString($playlist['title']);
			$playlist['description'] = XenForo_Helper_String::censorString($playlist['description']);
		}

		return $playlist;
	}

	public function getPlaylistThumbnailUrl(array $playlist)
	{
		return "styles/sonnb/XenGallery/playlist.png";
	}

	public function getPlaylistThumbnailFile(array $playlist)
	{
		return "styles/sonnb/XenGallery/playlist.png";
	}

	public function preparePlaylists(array $playlists, $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!empty($playlists))
		{
			foreach ($playlists as &$playlist)
			{
				$playlist = $this->preparePlaylist($playlist, $viewingUser);
			}
		}

		return $playlists;
	}

	public function getPlaylistBreadCrumb($playlist = null)
	{
		if ($playlist === null)
		{
			return $breadCrumbs['myplaylist'] = array(
				'href' => XenForo_Link::buildPublicLink('full:gallery/my-playlists'),
				'value' => new XenForo_Phrase('sonnb_xengallery_my_playlists')
			);
		}

		if (!is_array($playlist))
		{
			return array();
		}

		$breadCrumbs['myplaylist'] = array(
			'href' => XenForo_Link::buildPublicLink('full:gallery/my-playlists'),
			'value' => new XenForo_Phrase('sonnb_xengallery_my_playlists')
		);
		$breadCrumbs['myplaylist_'.$playlist['playlist_id']] = array(
			'href' => XenForo_Link::buildPublicLink('full:gallery/my-playlists', $playlist),
			'value' => $playlist['title'],
			'playlist_id' => $playlist['playlist_id']
		);

		return $breadCrumbs;
	}

	public function insertPlaylistItem($playlistId, $contentType, $contentId, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		/* @var sonnb_XenGallery_DataWriter_MyPlaylist $dw */
		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_MyPlaylist');
		$dw->setExistingData($playlistId);
		$success = $dw->insertPlaylistItem($contentType, $contentId, $viewingUser);

		if ($success === true)
		{
			return $dw->save();
		}
		else
		{
			return $success;
		}
	}

	public function deletePlaylistItem($playlistId, $contentType, $contentId, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		/* @var sonnb_XenGallery_DataWriter_MyPlaylist $dw */
		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_MyPlaylist');
		$dw->setExistingData($playlistId);
		$dw->deletePlaylistItem($contentType, $contentId, $viewingUser);

		return $dw->save();
	}

	public function preparePlaylistItemConditions($conditions = array(), $fetchOptions = array())
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['content_type']))
		{
			if (is_array($conditions['content_type']))
			{
				$sqlConditions[] = "myplaylist_item.content_type IN (" . $db->quote($conditions['content_type']) . ")";
			}
			else
			{
				$sqlConditions[] = "myplaylist_item.content_type = " . $db->quote($conditions['content_type']);
			}
		}

		if (!empty($conditions['content_id']))
		{
			if (is_array($conditions['content_id']))
			{
				$sqlConditions[] = "myplaylist_item.content_id IN (" . $db->quote($conditions['content_id']) . ")";
			}
			else
			{
				$sqlConditions[] = "myplaylist_item.content_id = " . $db->quote($conditions['content_id']);
			}
		}

		if (!empty($conditions['playlist_id']))
		{
			if (is_array($conditions['playlist_id']))
			{
				$sqlConditions[] = "myplaylist_item.playlist_id IN (" . $db->quote($conditions['playlist_id']) . ")";
			}
			else
			{
				$sqlConditions[] = "myplaylist_item.playlist_id = " . $db->quote($conditions['playlist_id']);
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function preparePlaylistItemFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';

		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';

			switch ($fetchOptions['order'])
			{
				case 'playlist_id':
				case 'playlist_item_id':
				case 'content_id':
				case 'content_type':
				case 'added_date':
					$orderBy = 'myplaylist_item.' . $fetchOptions['order'];
					$orderBySecondary = ', myplaylist_item.added_date DESC';
					break;
				case 'random':
					$orderBy = 'RAND()';
					$orderBySecondary = '';
					break;
				case 'added_date':
				default:
					$orderBy = 'myplaylist_item.added_date';
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
			if ($fetchOptions['join'] & self::FETCH_PLAYLIST)
			{
				$selectFields .= ',
						myplaylist.*';
				$joinTables .= '
						LEFT JOIN `sonnb_xengallery_myplaylist` AS myplaylist ON
							(myplaylist_item.playlist_id = myplaylist.playlist_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_CONTENT)
			{
				$selectFields .= ',
						content.*,
						content_data.*,

						album.category_id, album.album_state, album.album_type,
						album.content_count, album.photo_count, album.video_count, album.audio_count,
						album.album_privacy, album.album_location,
						album.cover_content_id, album.cover_content_type,
						album.latest_content_ids, album.latest_photo_ids, album.latest_video_ids, album.latest_audio_ids,
						album.album_date, album.album_updated_date,

						album.title AS album_title,
	                    album.description AS album_description,
						album.user_id AS album_user_id, album.username AS album_username,
						album.comment_count AS album_comment_count,
						album.view_count AS album_view_count,
						album.likes AS album_likes, album.like_users AS album_like_users,
						album.latest_comment_ids as album_latest_comment_ids,
						album.collection_id as album_collection_id,

						category.category_privacy';
				$joinTables .= '
						LEFT JOIN `sonnb_xengallery_content` AS content ON
							(content.content_id = myplaylist_item.content_id AND content.content_type = myplaylist_item.content_type)

						LEFT JOIN `sonnb_xengallery_content_data` AS content_data ON
							(content.content_data_id = content_data.content_data_id)

						LEFT JOIN `sonnb_xengallery_album` AS album ON
							(album.album_id = content.album_id)

						LEFT JOIN `sonnb_xengallery_category` AS category ON
							(album.category_id = category.category_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables' => $joinTables,
			'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}

	public function preparePlaylistConditions($conditions = array(), $fetchOptions = array())
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (!empty($conditions['playlist_id']))
		{
			if (is_array($conditions['playlist_id']))
			{
				$sqlConditions[] = 'myplaylist.playlist_id IN (' . $db->quote($conditions['playlist_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'myplaylist.playlist_id = ' . $db->quote($conditions['playlist_id']);
			}
		}

		if (isset($conditions['title']))
		{
			if (is_array($conditions['title']))
			{
				$sqlConditions[] = 'myplaylist.title IN (' . $db->quote($conditions['title']) . ')';
			}
			else
			{
				$sqlConditions[] = 'myplaylist.title = ' . $db->quote($conditions['title']);
			}
		}

		if (isset($conditions['user_id']))
		{
			if (is_array($conditions['user_id']))
			{
				$sqlConditions[] = 'myplaylist.user_id IN (' . $db->quote($conditions['user_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'myplaylist.user_id = ' . $db->quote($conditions['user_id']);
			}
		}

		if (isset($conditions['username']))
		{
			if (is_array($conditions['username']))
			{
				$sqlConditions[] = 'myplaylist.username IN (' . $db->quote($conditions['username']) . ')';
			}
			else
			{
				$sqlConditions[] = 'myplaylist.username = ' . $db->quote($conditions['username']);
			}
		}

		if (!empty($conditions['added_date']) && is_array($conditions['added_date']))
		{
			list($operator, $cutOff) = $conditions['added_date'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "myplaylist.added_date $operator " . $db->quote($cutOff);
		}

		if (!empty($conditions['updated_date']) && is_array($conditions['updated_date']))
		{
			list($operator, $cutOff) = $conditions['updated_date'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "myplaylist.updated_date $operator " . $db->quote($cutOff);
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function preparePlaylistFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';

		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';

			switch ($fetchOptions['order'])
			{
				case 'playlist_id':
				case 'title':
				case 'user_id':
				case 'content_count':
				case 'added_date':
					$orderBy = 'myplaylist.' . $fetchOptions['order'];
					$orderBySecondary = ', myplaylist.updated_date DESC';
					break;
				case 'updated_date':
				default:
					$orderBy = 'myplaylist.updated_date';
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
						IF(user.username IS NULL, myplaylist.username, user.username) AS username, user.avatar_date, user.gravatar';
				$joinTables .= '
						LEFT JOIN `xf_user` AS user ON
							(user.user_id = myplaylist.user_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables' => $joinTables,
			'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}
}