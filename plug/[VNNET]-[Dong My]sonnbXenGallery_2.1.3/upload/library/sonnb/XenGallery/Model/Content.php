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
class sonnb_XenGallery_Model_Content extends sonnb_XenGallery_Model_Abstract
{
	const FETCH_USER = 0x01;
	const FETCH_DATA = 0x02;
	const FETCH_ALBUM = 0x04;
	const FETCH_PHOTO = 0x08;
	const FETCH_VIDEO = 0x10;
	const FETCH_COLLECTION = 0x20;
	
	public function getContentById($contentId, array $fetchOptions = array())
	{
		if (!$contentId)
		{
			return array();
		}

		$conditions['content_id'] = $contentId;

		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getContents($conditions, $fetchOptions);

		return (empty($return) ? array() : reset($return));
	}

	public function getContentByContentId($contentType, $contentId, array $fetchOptions = array())
	{
		if (!$contentId || !$contentType)
		{
			return array();
		}

		$conditions['content_id'] = $contentId;
		$conditions['content_type'] = $contentType;

		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;

		$return = $this->getContents($conditions, $fetchOptions);

		return (empty($return) ? array() : reset($return));
	}
	
	public function getContentsByIds($contentIds, array $fetchOptions = array())
	{
		if (!$contentIds)
		{
            return array();
        }

		$conditions['content_id'] = $contentIds;

        return $this->getContents($conditions, $fetchOptions);
	}

	public function getContentsByContentIds($contentType, $contentIds, array $fetchOptions = array())
	{
		if (!$contentIds || !$contentType)
		{
			return array();
		}

		$conditions['content_id'] = $contentIds;
		$conditions['content_type'] = $contentType;

		return $this->getContents($conditions, $fetchOptions);
	}

	public function getContentsByContentUserId($contentType, $userId, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$userId)
		{
			return array();
		}

		$conditions['user_id'] = $userId;
		$conditions['content_type'] = $contentType;

		return $this->getContents($conditions, $fetchOptions);
	}
	
	public function getContentsByUserId($userId, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$userId)
		{
			return array();
		}
		
		$conditions['user_id'] = $userId;
		
		return $this->getContents($conditions, $fetchOptions);
	}

	public function getContentsByUserIds($userId, array $conditions = array(), array $fetchOptions = array())
	{
		return $this->getContentsByUserId($userId, $conditions, $fetchOptions);
	}

	public function getContentByContentDataId($contentType, $contentDataId, array $fetchOptions = array())
	{
		if (!$contentDataId)
		{
			return array();
		}

		$conditions['content_data_id'] = $contentDataId;
		$conditions['content_type'] = $contentType;

		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getContents($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}

	public function getContentByDataId($contentDataId, array $fetchOptions = array())
	{
		if (!$contentDataId)
		{
			return array();
		}

		$conditions['content_data_id'] = $contentDataId;

		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;

		$return = $this->getContents($conditions, $fetchOptions);

		return (empty($return) ? array() : reset($return));
	}
	
	public function getContentsByDataIds($contentDataId, array $fetchOptions = array())
	{
		if (!$contentDataId)
		{
			return array();
		}
	
		$conditions['content_data_id'] = $contentDataId;
	
		return $this->getContents($conditions, $fetchOptions);
	}

	public function getContentsByContentDataIds($contentType, $contentDataId, array $fetchOptions = array())
	{
		if (!$contentDataId)
		{
			return array();
		}

		$conditions['content_data_id'] = $contentDataId;
		$conditions['content_type'] = $contentType;

		return $this->getContents($conditions, $fetchOptions);
	}

	public function getContentsByAlbumId($albumId, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$albumId)
		{
			return array();
		}
		
		$conditions['album_id'] = $albumId;

		return $this->getContents($conditions, $fetchOptions);
	}

	public function getContentsByAlbumIds($albumIds, array $conditions = array(), array $fetchOptions = array())
	{
		return $this->getContentsByAlbumId($albumIds, $conditions, $fetchOptions);
	}
	
	public function getContents(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareContentConditions($conditions, $fetchOptions);

		$sqlClauses = $this->prepareContentFetchOptions($fetchOptions);

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed(
					$this->limitQueryResults(
						'
		                   SELECT content.*
		                        ' . $sqlClauses['selectFields'] . '
		                   FROM `sonnb_xengallery_content` AS content
		                    	' . $sqlClauses['joinTables'] . '
		                   WHERE ' . $whereConditions . '
		                    	' . $sqlClauses['orderClause'] . '
	                	', $limitOptions['limit'], $limitOptions['offset']
					), 'content_id'
				);
	}

	public function getContentByRowInAlbum(array $conditions = array(), array $fetchOptions = array())
	{
		$contents = $this->getContentsByRowInAlbum($conditions, $fetchOptions);

		return $contents ? reset($contents) : array();
	}

	public function getContentsByRowInAlbum(array $conditions = array(), array $fetchOptions = array())
	{
		$db = $this->_getDb();
		$extra = "";
		if (!empty($conditions['content_num_row']) && is_array($conditions['content_num_row']))
		{
			list($operator, $cutOff) = $conditions['content_num_row'];

			$this->assertValidCutOffOperator($operator);
			$extra = "WHERE content.content_num_row $operator " . $db->quote($cutOff);
		}
		if (!empty($conditions['content_id']))
		{
			if (is_array($conditions['content_id']))
			{
				$extra = ($extra ? $extra : 'WHERE' ) .' content.content_id IN (' . $db->quote($conditions['content_id']) . ')';
			}
			else
			{
				$extra = ($extra ? $extra : 'WHERE' ) .' content.content_id = ' . $db->quote($conditions['content_id']);
			}
			unset($conditions['content_id']);
		}

		$whereConditions = $this->prepareContentConditions($conditions, $fetchOptions);

		$sqlClauses = $this->prepareContentFetchOptions($fetchOptions);

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		//TODO: Optimize???
		return $db->fetchAll(
			$this->limitQueryResults(
				'
					SELECT content.*
                    FROM (
                            SELECT content.*, @row := @row + 1 AS content_num_row FROM (
	                            SELECT
			                        content.content_id, content.title, content.description, content.content_privacy,
			                        content.content_state, content.user_id, content.collection_id, content.likes, content.like_users,
			                        content.album_id, content.position, content.comment_count, content.view_count
	                                ' . $sqlClauses['selectFields'] . '
					            FROM `sonnb_xengallery_content` AS content
					                ' . $sqlClauses['joinTables'] . '
					            WHERE ' . $whereConditions . '
					            ' . $sqlClauses['orderClause'] . ') AS content
					            CROSS JOIN (SELECT @row := 0) AS r
					        ) AS content
				    '. $extra .'
	                	', $limitOptions['limit'], $limitOptions['offset']
			)
		);
	}

	public function getContentIdsByAlbumId($albumId, array $conditions = array(), array $fetchOptions = array())
	{
		$conditions['album_id'] = $albumId;

		return $this->getContentIds($conditions, $fetchOptions);
	}

	public function getContentIds(array $conditions = array(), array $fetchOptions = array())
	{
		$db = $this->_getDb();

		$whereConditions = $this->prepareContentConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareContentFetchOptions($fetchOptions);

		return $db->fetchCol('
				SELECT content.content_id
				FROM `sonnb_xengallery_content` AS content
                WHERE ' . $whereConditions . '
                    ' . $sqlClauses['orderClause'] . '
	    ');
	}

	public function getContentIdsInRange($start, $limit)
	{
		$db = $this->_getDb();

		return $db->fetchCol($db->limit('
				SELECT content_id
				FROM sonnb_xengallery_content
				WHERE content_id > ?
				ORDER BY content_id
			', $limit), $start);
	}

	public function getContentTypeIdsInRange($start, $limit)
	{
		$db = $this->_getDb();

		return $db->fetchPairs($db->limit('
				SELECT content_id, content_type
				FROM sonnb_xengallery_content
				WHERE content_id > ?
				ORDER BY content_id
			', $limit), $start);
	}
	
	public function countContentsByUserId($userId, array $conditions = array(), array $fetchOptions = array())
	{
		$conditions['user_id'] = $userId;
		
		return $this->countContents($conditions, $fetchOptions);
	}

	public function countContentsByContentUserId($contentType, $userId, array $conditions = array(), array $fetchOptions = array())
	{
		$conditions['user_id'] = $userId;
		$conditions['content_type'] = $contentType;

		return $this->countContents($conditions, $fetchOptions);
	}
	
	public function countContentsByAlbumId($id, array $conditions = array(), array $fetchOptions = array())
	{
		$conditions['album_id'] = $id;
		
		return $this->countContents($conditions, $fetchOptions);
	}

	public function countContentsByContentAlbumId($contentType, $id, array $conditions = array(), array $fetchOptions = array())
	{
		$conditions['album_id'] = $id;
		$conditions['content_type'] = $contentType;

		return $this->countContents($conditions, $fetchOptions);
	}
	
	public function countContents(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareContentConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareContentFetchOptions($fetchOptions);
		
		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_content` AS content
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
            ');
	}
	
	public function prepareContent(array $content, array $fetchOptions = array(), $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!empty($content))
		{
			if (empty($content['title']))
			{
				$content['title'] = $content['content_id'];
			}
			$content['title'] = XenForo_Helper_String::censorString($content['title']);
			$content['description'] = XenForo_Helper_String::censorString($content['description']);

			if (!empty($content['content_privacy']) && !is_array($content['content_privacy']))
			{
				$content['content_privacy'] = @unserialize($content['content_privacy']);
				if (isset($content['content_privacy']['allow_view_data']) && 
						!is_array($content['content_privacy']['allow_view_data']))
				{
					$content['content_privacy']['allow_view_data'] = @unserialize($content['content_privacy']['allow_view_data']);
				}
				if (isset($content['content_privacy']['allow_comment_data']) && 
						!is_array($content['content_privacy']['allow_comment_data']))
				{
					$content['content_privacy']['allow_comment_data'] = @unserialize($content['content_privacy']['allow_comment_data']);
				}
				if (isset($content['content_privacy']['allow_download_data']) &&
					!is_array($content['content_privacy']['allow_download_data']))
				{
					$content['content_privacy']['allow_download_data'] = @unserialize($content['content_privacy']['allow_download_data']);
				}
				if (isset($content['content_privacy']['allow_add_photo_data']) &&
					!is_array($content['content_privacy']['allow_add_photo_data']))
				{
					$content['content_privacy']['allow_add_photo_data'] = @unserialize($content['content_privacy']['allow_add_photo_data']);
				}
				if (isset($content['content_privacy']['allow_add_video_data']) &&
					!is_array($content['content_privacy']['allow_add_video_data']))
				{
					$content['content_privacy']['allow_add_video_data'] = @unserialize($content['content_privacy']['allow_add_video_data']);
				}
			}

			$privacyPhrase = array(
				'view' => 'sonnb_xengallery_privacy_'.$content['content_type'].'_view_',
				'comment' => 'sonnb_xengallery_privacy_'.$content['content_type'].'_comment_'
			);
			
			$content['allow_view_html'] = new XenForo_Phrase($privacyPhrase['view'].$content['content_privacy']['allow_view']);

			$content['canInlineMod'] = $this->canDeleteAnyContent('soft', $viewingUser);
			$content['canView'] = $this->canViewContent($content, $null, $viewingUser);
			$content['canComment'] = $this->canCommentContent($content, $null, $viewingUser);
			$content['canEdit'] = $this->canEditContent($content, $null, $viewingUser);
			$content['canDelete'] = $this->canDeleteContent($content, 'soft', $null, $viewingUser);
			$content['canLike'] = $this->canLikeContent($content, $null, $viewingUser);
			$content['canWatch'] = $this->canWatchContent($content, $null, $viewingUser);
			$content['canReport'] = $this->canReportContent($content, array(), $null, $viewingUser);

			$content['canPromote'] = $this->_getCollectionModel()->canAddToCollection($content, $null, $viewingUser);
			$content['canUnPromote'] = $content['collection_id'] && $this->_getCollectionModel()->canRemoveFromCollection($content, $null, $viewingUser);

            $content['canChangeOwner'] = $this->canChangeOwner($content, $viewingUser);
			
			$content['isDeleted'] = $this->isDeleted($content);
			$content['isModerated'] = $this->isModerated($content);
			$content['isIgnored'] = XenForo_Visitor::getInstance()->isIgnoring($content['user_id']);
			
			$content['likeUsers'] = @unserialize($content['like_users']);
			$content['tagUsers'] = @unserialize($content['tag_users']);
			$content['contentStreams'] = @unserialize($content['content_streams']);
			$content['thumbnailUrl'] = '';

			$content['likes'] = $this->_formatNumberCount($content['likes']);
			$content['comment_count'] = $this->_formatNumberCount($content['comment_count']);
			$content['view_count'] = $this->_formatNumberCount($content['view_count']);

			$content['url'] = XenForo_Link::buildPublicLink('gallery/'.$content['content_type'].'s', $content);

			if (isset($fetchOptions['join']) && ($fetchOptions['join'] & self::FETCH_DATA))
			{
				if (empty($content['medium_height']) || empty($content['medium_width']))
				{
					$content['thumbnailPath'] = $this->_getContentDataModel()->getContentDataMediumThumbnailFile($content);
					if (file_exists($content['thumbnailPath']))
					{
						if ($fileInfo = @getimagesize($content['thumbnailPath']))
						{
							$content['medium_width'] = $fileInfo[0];
							$content['medium_height'] = $fileInfo[1];
						}
					}
				}

				$content['contentUrl'] = $this->_getContentDataModel()->getContentDataLargeThumbnailUrl($content);
				$content['thumbnailUrl'] = $this->_getContentDataModel()->getContentDataMediumThumbnailUrl($content);
				$content['thumbnailSmall'] = $this->_getContentDataModel()->getContentDataSmallThumbnailUrl($content);
				$content['originalUrl'] = $this->_getContentDataModel()->getContentDataUrl($content);
			}
			
			if (isset($fetchOptions['join']) && $fetchOptions['join'] & self::FETCH_ALBUM)
			{
				$content['album'] = array(
					'album_id' => $content['album_id'],
					'title' => $content['album_title'],
					'description' => $content['album_description'],
					'user_id' => $content['album_user_id'],
					'username' => $content['album_username'],
					'category_id' => $content['category_id'],
					'collection_id' => $content['album_collection_id'],
					'album_state' => $content['album_state'],
					'album_type' => $content['album_type'],
					'comment_count' => $content['album_comment_count'],
					'view_count' => $content['album_view_count'],

					'content_count' => $content['content_count'],
					'photo_count' => $content['photo_count'],
					'video_count' => $content['video_count'],
					'audio_count' => $content['audio_count'],

					'likes' => $content['album_likes'],
					'like_users' => $content['album_like_users'],
					'tags' => $content['album_likes'],
					'tag_users' => $content['album_like_users'],
					'album_privacy' => $content['album_privacy'],
					'album_location' => $content['album_location'],

					'cover_content_id' => $content['cover_content_id'],
					'cover_content_type' => $content['cover_content_type'],

					'latest_content_ids' => $content['latest_content_ids'],
					'latest_photo_ids' => $content['latest_photo_ids'],
					'latest_video_ids' => $content['latest_video_ids'],
					'latest_audio_ids' => $content['latest_audio_ids'],
					'latest_comment_ids' => $content['album_latest_comment_ids'],

					'album_date' => $content['album_date'],
					'album_updated_date' => $content['album_updated_date'],
					'category_privacy' => $content['category_privacy']
				);

				if (isset($fetchOptions['followingUserId']))
				{
					if (isset($content['album_following_' . $fetchOptions['followingUserId']]))
					{
						$content['album']['following_' . $fetchOptions['followingUserId']] = $content['album_following_' . $fetchOptions['followingUserId']];
					}
					else
					{
						$content['album']['following_0'] = 0;
					}
				}

				$content['album'] = $this->_getAlbumModel()->prepareAlbum($content['album'], array(), $viewingUser);
			}
		}

		return $content;
	}
	
	public function prepareContents(array $contents, array $fetchOptions = array(), $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if (!empty($contents))
		{
			foreach ($contents as $key => &$content)
			{
				if (empty($content['content_data_id']))
				{
					unset($contents[$key]);
					continue;
				}

				$content = $this->prepareContent($content, $fetchOptions, $viewingUser);
			}
		}
		
		return $contents;
	}

	public function getRelatedContents(array $content, array $album, $limit = 14, $order, $direction)
	{
		$conditions = $this->getPermissionBasedContentFetchConditions() + array(
			'album_id' => $album['album_id'],
		);
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Content::FETCH_DATA,
			//	sonnb_XenGallery_Model_Content::FETCH_USER,
			'limit' => $limit,
			'order' => $order,
			'orderDirection' => $direction
		);

		switch ($order)
		{
			case 'content_updated_date':
			case 'content_date':
			case 'comment_count':
			case 'view_count':
			case 'likes':
			case 'recently_liked':
			case 'position':
				$conditions['content_id'] = $content['content_id'];

				$contentNumRow = $this->getContentByRowInAlbum($conditions, $fetchOptions);

				unset($conditions['content_id']);
				$conditions['content_num_row'] = array('<=', $contentNumRow['content_num_row']);
				$contentsBefore = $this->getContentsByRowInAlbum($conditions, $fetchOptions);

				$conditions['content_num_row'] = array('>', $contentNumRow['content_num_row']);
				$contentsAfter = $this->getContentsByRowInAlbum($conditions, $fetchOptions);

				$contents = $this->getRelatedContentsByContents($content, $contentsBefore, $contentsAfter, $limit);
				break;
			default:
				return array(array(), array(), array());
				break;
		}

		$contents = $this->prepareContents($contents, $fetchOptions);
		foreach ($contents as $key => $_content)
		{
			if (!$this->canViewContent($_content))
			{
				unset($contents[$key]);
				continue;
			}
		}

		$contents = array_values($contents);
		$nextContent = $prevContent = array();
		switch ($order)
		{
			case 'content_updated_date':
			case 'content_date':
			case 'comment_count':
			case 'view_count':
			case 'likes':
			case 'recently_liked':
			case 'position':
				foreach ($contents as $key => $_content)
				{
					if (count($contents) === 1)
					{
						$prevContent = $nextContent = $_content;
						continue;
					}

					if ($_content['content_id'] === $content['content_id'])
					{
						if ($key === 0)
						{
							$nextContent = $contents[$key + 1];
							$prevContent = end($contents);
						}
						elseif ($key >= count($contents) - 1)
						{
							$nextContent = reset($contents);
							$prevContent = $contents[$key - 1];
						}
						else
						{
							$nextContent = $contents[$key + 1];
							$prevContent = $contents[$key - 1];
						}
					}
				}
				break;
			default:
				break;
		}

		return array($contents, $nextContent, $prevContent);
	}

	public function getRelatedContentsByContents(array $content, array $contentBefore, array $contentAfter, $limit = 14)
	{
		if (empty($contentBefore))
		{
			return array_merge(array($content), $contentAfter);
		}
		elseif (count($contentBefore) === 1)
		{
			return array_merge($contentBefore, $contentAfter);
		}

		if (empty($contentAfter))
		{
			return $contentBefore;
		}

		$half = round($limit/2);
		$beforeCount = count($contentBefore);
		$afterCount = count($contentAfter);
		if ($beforeCount >= $half && $afterCount >= $half)
		{
			return array_merge(
				array_slice($contentBefore, -$half, $half, true),
				array_slice($contentAfter, 0, $half, true)
			);
		}
		elseif ($beforeCount <= $half && $afterCount <= $half)
		{
			return array_merge($contentBefore, $contentAfter);
		}
		elseif ($beforeCount <= $half && $afterCount >= $half)
		{
			$left = $limit - $beforeCount;
			return array_merge(
				$contentBefore,
				array_slice($contentAfter, 0, $left, true)
			);
		}
		else
		{
			$left = $limit - $afterCount;

			return array_merge(
				array_slice($contentBefore, -$left, $left, true),
				$contentAfter
			);
		}
	}

	public function getPositionsAroundContent(array $content, array $album, $limit = 14)
	{
		$positions = array();
		if ($content['position'] == 0)
		{
			$positions = range($album['content_count'] - $this->_round($limit/2), $album['content_count'] - 1);
			$positions = array_merge($positions, range(0, $this->_round($limit/2)));
		}
		elseif ($content['position'] == $album['content_count'] - 1)
		{
			$start = $content['position'] - $limit;
			if ($start < 0)
			{
				$start = 0;
			}

			$positions = range($start, $content['position']);
		}
		elseif (($content['position'] + $this->_round($limit/2) > $album['content_count'] - 1)
			&& ($content['position'] - $this->_round($limit/2) < 0))
		{
			$positions = range(0, $album['content_count'] - 1);
		}
		elseif ($content['position'] + $this->_round($limit/2) > $album['content_count'] - 1)
		{
			for ($i = $album['content_count'] - 1;$i>=0;$i--)
			{
				if (count($positions) >= $limit)
				{
					break;
				}

				$positions[] = $i;
			}
			sort($positions);
		}
		elseif ($content['position'] - $this->_round($limit/2) < 0)
		{
			$positions = range(0, $limit);
		}
		else
		{
			for ($i = $content['position']-1;$i>=0;$i--)
			{
				if ($content['position'] - $i -1 >= $this->_round($limit/2))
				{
					break;
				}

				$positions[] = $i;
			}
			sort($positions);

			$positions = array_merge($positions, range($content['position'], $content['position'] + $this->_round($limit/2)));
		}

		return $positions;
	}

	protected function _round($int)
	{
		if (defined('PHP_ROUND_HALF_DOWN'))
		{
			return round($int, 0, PHP_ROUND_HALF_DOWN);
		}
		else
		{
			$int = $int * pow(10, 0) - 0.5;
			return ceil($int) * pow(10, 0);
		}
	}
	
	public function logContentView($contentId)
	{
		$this->_getDb()->query('
			INSERT ' . (XenForo_Application::getOptions()->enableInsertDelayed ? 'DELAYED' : '') . ' INTO sonnb_xengallery_content_view
				(content_id)
			VALUES
				(?)
		', $contentId);
	}
	
	public function updateContentViews()
	{
		$db = $this->_getDb();

		$updates = $db->fetchPairs('
			SELECT content_id, COUNT(*)
			FROM sonnb_xengallery_content_view
			GROUP BY content_id
		');

		XenForo_Db::beginTransaction($db);

		$db->query('TRUNCATE TABLE sonnb_xengallery_content_view');

		foreach ($updates AS $contentId => $views)
		{
			$db->query('
				UPDATE sonnb_xengallery_content SET
					view_count = view_count + ?
				WHERE content_id = ?
			', array($views, $contentId));
		}

		XenForo_Db::commit($db);
	}
	
	public function addContentsToAlbums(array $albums, array $fetchOptions = null)
	{
		$contentIdMap = array();

		foreach ($albums AS &$album)
		{
			if ($album['latest_content_ids'])
			{
				foreach (explode(',', $album['latest_content_ids']) AS $contentId)
				{
					$contentIdMap[intval($contentId)] = $album['album_id'];
				}
			}
		
			$album['contents'] = array();
		}
		
		if ($contentIdMap)
		{
			$contents = $this->getContentsByIds(array_keys($contentIdMap), $fetchOptions);
			$contents = $this->prepareContents($contents, $fetchOptions);
			
			foreach ($contentIdMap AS $contentId => $albumId)
			{
				if (isset($contents[$contentId]))
				{
					$albums[$albumId]['contents'][$contentId] = $contents[$contentId];
				}
			}
		}
		
		return $albums;
	}
	
	public function addContentsToAlbum(array $album, array $fetchOptions = null)
	{
		if ($album['latest_content_ids'])
		{
			$contentIds = explode(',', $album['latest_content_ids']);
			
			if ($contentIds)
			{
				$album['contents'] = $this->getContentsByIds($contentIds, $fetchOptions);
				$album['contents'] = $this->prepareContents($album['contents'], $fetchOptions);
			}
		}
		
		return $album;
	}
	
	public function arrangeContents(array $position)
	{
		if ($position)
		{
			$db = $this->_getDb();
			
			foreach ($position as $index=>$contentId)
			{
				$updateCondition = 'content_id = '.$db->quote($contentId);
				
				$db->update('sonnb_xengallery_content', array('position' => $db->quote($index)), $updateCondition);
			} 
		}
		
		return true;
	}
	
	public function modifyContentCount($albumId, $modifyValue)
	{
		//$contentType = $this->get('content_type');
		//$contentId = $this->get('content_id');

		//TODO: Check by content type
		$db = $this->_getDb();
	
		$contentCount = $this->countContentsByAlbumId($albumId);
	
		$condition = 'album_id = ' . $db->quote($albumId);
	
		if ($contentCount <= 0)
		{
			$contentCount = 0;
		}
	
		$db->update('sonnb_xengallery_album', array('content_count' => $contentCount), $condition);
	
		return $contentCount;
	}
	
	public function getContentDefaultState($contentType, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'moderate'.ucfirst($contentType));
	}
	
	public function canReportContent(array $content, array $album, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
	
		if (!$viewingUser['user_id'])
		{
			return false;
		}
		
		return $this->_getUserModel()->canReportContent($errorPhraseKey, $viewingUser);
	}
	
	public function canViewContent(array $content, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if ($this->isDeleted($content) && !$this->canViewDeletedContent($content, $viewingUser))
		{
			return false;
		}

		if ($this->isModerated($content) && !$this->canViewModeratedContent($content, $viewingUser))
		{
			return false;
		}
		
		if ($viewingUser['user_id'] == $content['user_id'])
		{
			return true;
		}
		
		if ($this->canViewAnyContent($viewingUser))
		{
			return true;
		}
		
		return $this->passesPrivacyCheck(
			$content,
			$content['content_privacy']['allow_view'],
			'view',
			isset($content['content_privacy']['allow_view_data']) ? $content['content_privacy']['allow_view_data'] : array(),
			$errorPhraseKey,
			$viewingUser
		);
	}
	
	public function canViewContentAndContainer(array $content, $album = null, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		$albumModel = $this->_getAlbumModel();
		
		if ($album === null)
		{
			$album = $albumModel->getAlbumById($content['album_id']);
			$album = $albumModel->prepareAlbum($album);
		}
		
		if ($albumModel->canViewAlbum($album, $errorPhraseKey, $viewingUser))
		{
			return $this->canViewContent($content, $errorPhraseKey, $viewingUser);
		}
		
		return false;
	}
	
	public function canCommentContent(array $content, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'canComment'))
		{
			return false;
		}
		
		if ($content['user_id'] == $viewingUser['user_id'])
		{
			return true;
		}

		if ($this->canEditAnyContent($viewingUser))
		{
			return true;
		}
		
		return $this->passesPrivacyCheck(
			$content,
			$content['content_privacy']['allow_comment'],
			'comment',
			isset($content['content_privacy']['allow_comment_data']) ? $content['content_privacy']['allow_comment_data'] : array(),
			$errorPhraseKey,
			$viewingUser
		);
	}

	public function canTagContent(array $content, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($content['user_id'] == $viewingUser['user_id'])
		{
			return true;
		}

		if ($this->canEditAnyContent($viewingUser))
		{
			return true;
		}

		return $this->passesPrivacyCheck(
			$content,
			$content['content_privacy']['allow_tag_people'],
			'tag_people',
			isset($content['content_privacy']['allow_tag_people_data']) ? $content['content_privacy']['allow_tag_people_data'] : array(),
			$errorPhraseKey,
			$viewingUser
		);
	}
	
	public function passesPrivacyCheck(array $content, $type, $action, $typeData, &$errorPhraseKey = '', array $viewingUser = null)
	{
		if (!empty($type))
		{
			switch ($type)
			{
				case 'everyone':
					return true;
					break;
				case 'members':
					$errorPhraseKey = 'sonnb_xengallery_registered_members_can_'.$action.'_this_'.$content['content_type'];
					return $viewingUser['user_id'];
					break;
				case 'following':
					$errorPhraseKey = 'sonnb_xengallery_members_who_following_this_user_can_'.$action.'_this_'.$content['content_type'];
					return $this->getModelFromCache('XenForo_Model_User')->isFollowing($content['user_id'], $viewingUser);
					break;
				case 'followed':
					$errorPhraseKey = 'sonnb_xengallery_members_followed_by_this_user_can_'.$action.'_this_'.$content['content_type'];

					if (isset($content['following_' . $viewingUser['user_id']]))
					{
						return ($content['following_' . $viewingUser['user_id']] > 0);
					}
					elseif (!empty($content['following']))
					{
						return in_array($viewingUser['user_id'], explode(',', $content['following']));
					}
					else
					{
						return false;
					}
					break;
				case 'custom':
					$errorPhraseKey = 'sonnb_xengallery_specified_members_can_'.$action.'_this_'.$content['content_type'];
			
					if (!is_array($typeData))
					{
						$typeData = @unserialize($typeData);
					}
					if (is_array($typeData) && !empty($typeData))
					{
						return in_array($viewingUser['user_id'], $typeData);
					}
					break;
				case 'none':
				default:
					$errorPhraseKey = 'sonnb_xengallery_owner_can_'.$action.'_this_'.$content['content_type'];
					break;
			}
		}
					
		return false;
	}
	
	public function canLikeContent(array $content, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'canLike'))
		{
			return false;
		}
	
		if ($content['user_id'] === $viewingUser['user_id'])
		{
			$errorPhraseKey = 'liking_own_content_cheating';
			return false;
		}
	
		return true;
	}

	public function canWatchContent(array $content, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($viewingUser['user_id'] === $content['user_id'])
		{
			return false;
		}

		return true;
	}
	
	public function canEditContent(array $content, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
	
		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($content['user_id'] === $viewingUser['user_id']
				|| $this->canEditAnyContent($viewingUser)
		)
		{
			return true;
		}
	
		return false;
	}

	public function canChangeOwner(array $content, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'canChangeOwner'))
		{
			return false;
		}

		return true;
	}
	
	public function canDeleteContent(array $content, $type, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
	
		if (!$viewingUser['user_id'])
		{
			return false;
		}
        
        if ($content['user_id'] === $viewingUser['user_id'])
        {
        	switch ($type)
        	{
        		case 'soft':
        			return true;
        			break;
        		case 'hard':
			        if ($this->canHardDeleteOwnContents($viewingUser))
			        {
				        return true;
			        }
        		default:
        			break;
        	}
        }
	
		if ($this->canDeleteAnyContent('soft', $viewingUser))
		{
			return true;
		}
	
		return false;
	}
	
	public function getContentBreadCrumbs(array $content, array $album)
	{		
		$breadCrumbs = $this->_getAlbumModel()->getAlbumBreadCrumbs($album);
			
		$breadCrumbs[$content['content_id']] = array(
			'href' => XenForo_Link::buildPublicLink('full:' . 'gallery/'. $content['content_type'] .'s', $content),
			'value' => $content['title'],
			'content_id' => $content['content_id']
		);

		return $breadCrumbs;
	}
	
	public function getPermissionBasedContentFetchConditions(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($this->canViewDeletedContent(null, $viewingUser))
		{
			$viewDeleted = true;
		}
		elseif ($viewingUser['user_id'])
		{
			$viewDeleted = $viewingUser['user_id'];
		}
		else
		{
			$viewDeleted = false;
		}
	
		if ($this->canViewModeratedContent($viewingUser))
		{
			$viewModerated = true;
		}
		elseif ($viewingUser['user_id'])
		{
			$viewModerated = $viewingUser['user_id'];
		}
		else
		{
			$viewModerated = false;
		}
	
		return array(
			'deleted' => $viewDeleted,
			'moderated' => $viewModerated
		);
	}

	public function getDefaultPrivacy($contentType, $action)
	{
		$visitor = XenForo_Visitor::getInstance();
		$xenOptions = XenForo_Application::getOptions();

		$optionKey = 'sonnbXG_'.strtolower($contentType).'Privacy'.ucfirst(strtolower($action));
		$privacy = $xenOptions->$optionKey;

		if (!empty($visitor['xengallery']) && isset($visitor['xengallery'][strtolower($contentType).'_allow_'.strtolower($action)]))
		{
			$privacy = $visitor['xengallery'][strtolower($contentType).'_allow_'.strtolower($action)];
		}

		return $privacy;
	}

	public function getInlineModeration(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$inlineModOptions = array();

		if ($this->canDeleteAnyContent('soft', $viewingUser))
		{
			$inlineModOptions = array(
				'delete' => true,
				'undelete' => true,
				'approve' => true,
				'unapprove' => true,
				'move' => true
			);
		}

		return $inlineModOptions;
	}
	
	public function canViewModeratedContent(array $content, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($content['user_id'] === $viewingUser['user_id'])
		{
			return true;
		}
		 
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'viewModeratedContent');
	}
	
	public function canViewDeletedContent(array $content = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (isset($content['user_id']) && $content['user_id'] === $viewingUser['user_id'] && $this->canHardDeleteOwnContents($viewingUser))
		{
			return true;
		}
		 
		return $this->canDeleteAnyContent('soft', $viewingUser);
	}

	public function canHardDeleteOwnContents(array $viewingUser = null)
	{
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'hardDeleteOwnContents');
	}
	
	public function canViewAnyContent(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		 
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'viewAnyContent');
	}
	
	public function canEditAnyContent(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		 
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'editAnyContent');
	}
	
	public function canDeleteAnyContent($type, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$hardDelete = XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'deleteAnyContentHard');
		$softDelete = XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'deleteAnyContentSoft');
		 
		if ($type === 'soft')
		{
			return ($hardDelete || $softDelete);
		}
		else
		{
			return $hardDelete;
		}
	}

	public function isPhoto(array $content)
	{
		return ($content['content_type'] === sonnb_XenGallery_Model_Photo::$contentType);
	}

	public function isVideo(array $content)
	{
		return ($content['content_type'] === sonnb_XenGallery_Model_Video::$contentType);
	}
	
	public function isDeleted(array $content)
	{
		return ($content['content_state'] === 'deleted');
	}
	
	public function isModerated(array $content)
	{
		return ($content['content_state'] === 'moderated');
	}
	
	public function isVisible(array $content)
	{
		return ($content['content_state'] === 'visible');
	}

	public static function sortContentByPosition($a, $b)
	{
		return $a['position'] - $b['position'];
	}
	
	public function prepareContentFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';
		
		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';
		
			switch ($fetchOptions['order'])
			{
				case 'content_id':
				case 'album_id':
				case 'user_id':
				case 'content_state':
				case 'comment_count':
				case 'view_count':
				case 'likes':
				case 'content_date':
				case 'content_updated_date':
					$orderBy = 'content.' . $fetchOptions['order'];
					$orderBySecondary = ', content.position ASC';
					break;
				case 'position_date':
					$orderBy = 'content.position';
					$orderBySecondary = ', content.content_updated_date ASC';
					break;
				case 'collection_date':
					$orderBy = 'collection.collection_date';
					$orderBySecondary = ', content.content_updated_date ASC';
					break;
				case 'random':
					$orderBy = 'RAND()';
					$orderBySecondary = '';
					break;
				case 'recently_liked':
					$selectFields .= ',
                                liked_content_sort.like_date AS like_date_sort';
					$joinTables .= '
                                LEFT JOIN xf_liked_content AS liked_content_sort
                                        ON (liked_content_sort.content_type = CONCAT("sonnb_xengallery_", CAST(content.content_type AS CHAR))
                                                AND liked_content_sort.content_id = content.content_id)';

					$orderBy = 'liked_content_sort.like_date';
					$orderBySecondary = ', content.content_updated_date ASC';
					break;
				case 'position':
				default:
					$orderBy = 'content.position';
			}

			if (!isset($fetchOptions['orderDirection']) || $fetchOptions['orderDirection'] === 'asc')
			{
				$orderBy .= ' ASC';
			}
			else
			{
				$orderBy .= ' DESC';
			}
		
			$orderBy .= $orderBySecondary;
		}
		
		if (isset($fetchOptions['likeUserId']))
		{
			if (empty($fetchOptions['likeUserId']))
			{
				$selectFields .= ',
                                0 AS like_date';
			}
			else
			{
				$selectFields .= ',
                                liked_content.like_date';
				$joinTables .= '
                                LEFT JOIN xf_liked_content AS liked_content
                                        ON (liked_content.content_type = CONCAT("sonnb_xengallery_", CAST(content.content_type AS CHAR))
                                                AND liked_content.content_id = content.content_id
                                                AND liked_content.like_user_id = ' . $this->_getDb()->quote($fetchOptions['likeUserId']) . ')';
			}
		}

		if (isset($fetchOptions['watchUserId']))
		{
			$fetchOptions['watchUserId'] = intval($fetchOptions['watchUserId']);
			if (empty($fetchOptions['watchUserId']))
			{
				$selectFields .= ',
                                0 AS watch_date';
			}
			else
			{
				$selectFields .= ',
                                watch.watch_date as watch_date';
				$joinTables .= '
                                LEFT JOIN sonnb_xengallery_watch AS watch
                                        ON (watch.content_type = content.content_type
        										AND watch.content_id = content.content_id
                                                AND watch.user_id = ' . $fetchOptions['watchUserId'] . ')';
			}
		}
		
		if (!empty($fetchOptions['join']))
		{			
			if ($fetchOptions['join'] & self::FETCH_ALBUM)
			{
				$selectFields .= ',
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

					category.category_privacy
					';

				$joinTables .= '
					INNER JOIN `sonnb_xengallery_album` AS album ON
						(album.album_id = content.album_id)

					LEFT JOIN `sonnb_xengallery_category` AS category ON
						(album.category_id = category.category_id)';

				if (isset($fetchOptions['followingUserId']))
				{
					$fetchOptions['followingUserId'] = intval($fetchOptions['followingUserId']);
					if ($fetchOptions['followingUserId'])
					{
						$selectFields .= ',
							IF(user_follow.follow_user_id IS NOT NULL, 1, 0) AS album_following_' . $fetchOptions['followingUserId'];
								$joinTables .= '
							LEFT JOIN xf_user_follow AS user_follow_album ON
								(user_follow_album.user_id = album.user_id AND user_follow_album.follow_user_id = ' . $fetchOptions['followingUserId'] . ')';
					}
					else
					{
						$selectFields .= ',
							0 AS following_0';
					}
				}
			}
			 
			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= ',
					IF(user.username IS NULL, content.username, user.username) AS username, user.avatar_date, user.gravatar';
				$joinTables .= '
					LEFT JOIN `xf_user` AS user ON
						(user.user_id = content.user_id)';
			}
		
			if ($fetchOptions['join'] & self::FETCH_DATA)
			{
				$selectFields .= ',
					content_data.*';
				$joinTables .= "
					INNER JOIN `sonnb_xengallery_content_data` AS content_data ON
						(content.content_data_id = content_data.content_data_id)";
			}

			if (isset($fetchOptions['fetchCollectionDate']))
			{
				$selectFields .= ',
					IF(collection.collection_date IS NOT NULL, collection.collection_date, 0) AS collection_date';
				$joinTables .= "
					LEFT JOIN `sonnb_xengallery_collection_item` AS collection ON
						(collection.collection_id = content.collection_id
							AND collection.content_type = content.content_type
							AND collection.content_id = content.content_id)";
			}

			if ($fetchOptions['join'] & self::FETCH_PHOTO)
			{
				$selectFields .= ',
					photo.photo_exif';
				$joinTables .= "
					LEFT JOIN `sonnb_xengallery_photo` AS photo ON
						(photo.content_id = content.content_id)";
			}

			if ($fetchOptions['join'] & self::FETCH_VIDEO)
			{
				$selectFields .= ',
					video.video_key, video.video_type';
				$joinTables .= "
					LEFT JOIN `sonnb_xengallery_video` AS video ON
						(video.content_id = content.content_id)";
			}

			if (isset($fetchOptions['followingUserId']))
			{
				$fetchOptions['followingUserId'] = intval($fetchOptions['followingUserId']);
				if ($fetchOptions['followingUserId'])
				{
					$selectFields .= ',
					IF(user_follow.follow_user_id IS NOT NULL, 1, 0) AS following_' . $fetchOptions['followingUserId'];
					$joinTables .= '
					LEFT JOIN xf_user_follow AS user_follow ON
						(user_follow.user_id = content.user_id AND user_follow.follow_user_id = ' . $fetchOptions['followingUserId'] . ')';
				}
				else
				{
					$selectFields .= ',
					0 AS following_0';
				}
			}
		}

		return array(
				'selectFields' => $selectFields,
				'joinTables' => $joinTables,
				'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}
	
	public function prepareContentConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
		
		if (!empty($conditions['album_id']))
		{
			if (is_array($conditions['album_id']))
			{
				$sqlConditions[] = 'content.album_id IN (' . $db->quote($conditions['album_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'content.album_id = ' . $db->quote($conditions['album_id']);
			}
		}
		
		if (isset($conditions['user_id']))
		{
			if (is_array($conditions['user_id']))
			{
				$sqlConditions[] = 'content.user_id IN (' . $db->quote($conditions['user_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'content.user_id = ' . $db->quote($conditions['user_id']);
			}
		}
		
		if (!empty($conditions['content_id']))
		{
			if (is_array($conditions['content_id']))
			{
				$sqlConditions[] = 'content.content_id IN (' . $db->quote($conditions['content_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'content.content_id = ' . $db->quote($conditions['content_id']);
			}
		}

		if (!empty($conditions['around_content_id']) && is_array($conditions['around_content_id']))
		{
			list($operator, $cutOff) = $conditions['around_content_id'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "content.content_id $operator " . $db->quote($cutOff);
		}

		if (!empty($conditions['content_type']))
		{
			if (is_array($conditions['content_type']))
			{
				$sqlConditions[] = 'content.content_type IN (' . $db->quote($conditions['content_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'content.content_type = ' . $db->quote($conditions['content_type']);
			}
		}

		if (!empty($conditions['collection_id']))
		{
			if (is_array($conditions['collection_id']))
			{
				$sqlConditions[] = 'content.collection_id IN (' . $db->quote($conditions['collection_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'content.collection_id = ' . $db->quote($conditions['collection_id']);
			}
		}

		if (!empty($conditions['category_id']) &&
				isset($fetchOptions['join']) && $fetchOptions['join'] & self::FETCH_ALBUM)
		{
			if (is_array($conditions['category_id']))
			{
				$sqlConditions[] = 'album.category_id IN (' . $db->quote($conditions['category_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'album.category_id = ' . $db->quote($conditions['category_id']);
			}
		}
		
		if (!empty($conditions['content_data_id']))
		{
			if (is_array($conditions['content_data_id']))
			{
				$sqlConditions[] = 'content.content_data_id IN (' . $db->quote($conditions['content_data_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'content.content_data_id = ' . $db->quote($conditions['content_data_id']);
			}
		}
		
		if (!empty($conditions['content_state']))
		{
			if (is_array($conditions['content_state']))
			{
				$sqlConditions[] = 'content.content_state IN (' . $db->quote($conditions['content_state']) . ')';
			}
			else
			{
				$sqlConditions[] = 'content.content_state = ' . $db->quote($conditions['content_state']);
			}
		}
		
		if (!empty($conditions['content_location']))
		{
			if (is_array($conditions['content_location']))
			{
				$sqlConditions[] = 'content.content_location IN (' . $db->quote($conditions['content_location']) . ')';
			}
			else
			{
				$sqlConditions[] = 'content.content_location = ' . $db->quote($conditions['content_location']);
			}
		}

		if (!empty($conditions['position_range']))
		{
			if (is_array($conditions['position_range']))
			{
				$sqlConditions[] = 'content.position IN (' . $db->quote($conditions['position_range']) . ')';
			}
			else
			{
				$sqlConditions[] = 'content.position = ' . $db->quote($conditions['position_range']);
			}
		}

		if (!empty($conditions['position']) && is_array($conditions['position']))
		{
			list($operator, $cutOff) = $conditions['position'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "content.position $operator " . $db->quote($cutOff);
		}

		if (!empty($conditions['like_date']) && is_array($conditions['like_date']) && $fetchOptions['order'] === 'recently_liked')
		{
			list($operator, $cutOff) = $conditions['like_date'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "liked_content_sort.like_date $operator " . $db->quote($cutOff);
		}

		if (isset($conditions['deleted']) || isset($conditions['moderated']))
		{
			$sqlConditions[] = $this->prepareStateLimitFromConditions($conditions, 'content', 'content_state');
		}
		
		if (!empty($conditions['likes']) && is_array($conditions['likes']))
		{
			list($operator, $cutOff) = $conditions['likes'];
		
			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "content.likes $operator " . $db->quote($cutOff);
		}

		if (!empty($conditions['view_count']) && is_array($conditions['view_count']))
		{
			list($operator, $cutOff) = $conditions['view_count'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "content.view_count $operator " . $db->quote($cutOff);
		}

		if (!empty($conditions['comment_count']) && is_array($conditions['comment_count']))
		{
			list($operator, $cutOff) = $conditions['comment_count'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "content.comment_count $operator " . $db->quote($cutOff);
		}

		if (!empty($conditions['content_date']) && is_array($conditions['content_date']))
		{
			list($operator, $cutOff) = $conditions['content_date'];
		
			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "content.content_date $operator " . $db->quote($cutOff);
		}
		
		if (!empty($conditions['content_updated_date']) && is_array($conditions['content_updated_date']))
		{
			list($operator, $cutOff) = $conditions['content_updated_date'];
		
			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "content.content_updated_date $operator " . $db->quote($cutOff);
		}

		return $this->getConditionsForClause($sqlConditions);
	}
}
