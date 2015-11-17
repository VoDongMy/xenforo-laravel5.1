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
class sonnb_XenGallery_Model_Album extends sonnb_XenGallery_Model_Abstract
{
	const FETCH_USER = 0x01;
	const FETCH_COVER_PHOTO = 0x02;
	const FETCH_COLLECTION = 0x04;
	
	const ALBUM_TYPE_NORMAL = 0;
	const ALBUM_TYPE_PROFILE = 1;
	const ALBUM_TYPE_MOBILE = 2;
	
	public static $contentType = 'album';
	public static $xfContentType = 'sonnb_xengallery_album';
	
	public static $privacyPhrase = array(
		'view' => 'sonnb_xengallery_privacy_album_view_',
		'comment' => 'sonnb_xengallery_privacy_album_comment_',
		'addPhoto' => 'sonnb_xengallery_privacy_album_addphoto_',
		'addVideo' => 'sonnb_xengallery_privacy_album_addvideo_',
		'download' => 'sonnb_xengallery_privacy_album_download_'
	);
	
	public function getAlbumById($id, array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
		 
		$conditions['album_id'] = $id;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getAlbums($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}
	
	public function getAlbumsByIds($ids, array $fetchOptions = array())
	{
		if (!$ids)
        {
            return array();
        }
        
        $conditions['album_id'] = $ids;
        
        return $this->getAlbums($conditions, $fetchOptions);
	}
	
	public function getMobileAlbumByUserId($userId)
	{
		if (!$userId)
		{
			return array();
		}
		
		$conditions['album_type'] = self::ALBUM_TYPE_MOBILE;
		$conditions['user_id'] = $userId;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getAlbums($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}
	
	public function getProfileAlbumByUserId($userId)
	{
		if (!$userId)
		{
			return array();
		}
		
		$conditions['album_type'] = self::ALBUM_TYPE_PROFILE;
		$conditions['user_id'] = $userId;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getAlbums($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}
	
	public function getAlbumsByUserId($id, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
		
		$conditions['user_id'] = $id;
		
		return $this->getAlbums($conditions, $fetchOptions);
	}

	public function getAlbumsByUserIds($id, array $conditions = array(), array $fetchOptions = array())
	{
		return $this->getAlbumsByUserId($id, $conditions, $fetchOptions);
	}

	public function getAlbumsByCategoryId($id, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
		
		$conditions['category_id'] = $id;
		
		return $this->getAlbums($conditions, $fetchOptions);
	}

	public function getAlbumsByCategoryIds($id, array $conditions = array(), array $fetchOptions = array())
	{
		return $this->getAlbumsByCategoryId($id, $conditions, $fetchOptions);
	}
	
	public function getMostViewedAlbums($limit = 5, array $conditions = array(), array $fetchOptions = array())
	{
		$fetchOptions['order'] = 'view_count';
		$fetchOptions['orderDirection'] = 'desc';
		$fetchOptions['limit'] = $limit;
		$fetchOptions['offset'] = 0;
		
		return $this->getAlbums($conditions, $fetchOptions);
	} 
	
	public function getMostCommentedAlbums($limit = 5, array $conditions = array(), array $fetchOptions = array())
	{
		$fetchOptions['order'] = 'comment_count';
		$fetchOptions['orderDirection'] = 'desc';
		$fetchOptions['limit'] = $limit;
		$fetchOptions['offset'] = 0;
		
		return $this->getAlbums($conditions, $fetchOptions);
	}
	
	public function getAlbums(array $conditions = array(), array $fetchOptions = array())
	{		 
		$whereConditions = $this->prepareAlbumConditions($conditions, $fetchOptions);
		
		$sqlClauses = $this->prepareAlbumFetchOptions($fetchOptions);
		
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed(
					$this->limitQueryResults(
						'
		                   SELECT album.*
		                        ' . $sqlClauses['selectFields'] . '
		                   FROM `sonnb_xengallery_album` AS album
		                    	' . $sqlClauses['joinTables'] . '
		                   WHERE ' . $whereConditions . '
		                    	' . $sqlClauses['orderClause'] . '
	                	', $limitOptions['limit'], $limitOptions['offset']
					), 'album_id'
				);
	}

	public function getAlbumIdsInRange($start, $limit)
	{
		$db = $this->_getDb();

		return $db->fetchCol($db->limit('
				SELECT album_id
				FROM sonnb_xengallery_album
				WHERE album_id > ?
				ORDER BY album_id
			', $limit), $start);
	}
	
	public function countAlbumsByUserId($id, array $conditions = array(), array $fetchOptions = array())
	{
		$conditions['user_id'] = $id;
		
		return $this->countAlbums($conditions, $fetchOptions);
	}
	
	public function countAlbumsByCategoryId($id, array $conditions = array(), array $fetchOptions = array())
	{
		$conditions['category_id'] = $id;
		
		return $this->countAlbums($conditions, $fetchOptions);
	}
	
	public function countAlbums(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareAlbumConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareAlbumFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_album` AS album
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
            ');
	}
	
	public function prepareAlbum(array $album, array $fetchOptions = array(), $viewingUser = array())
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if (!empty($album))
		{
			if (!empty($album['album_privacy']) && !is_array($album['album_privacy']))
			{
				$album['album_privacy'] = @unserialize($album['album_privacy']);

				if (!isset($album['album_privacy']['allow_add_video']))
				{
					$album['album_privacy']['allow_add_video'] = $album['album_privacy']['allow_add_photo'];
				}
				if (!isset($album['album_privacy']['allow_download']))
				{
					$album['album_privacy']['allow_download'] = 'none';
				}

				if (isset($album['album_privacy']['allow_view_data']) && 
						!is_array($album['album_privacy']['allow_view_data']))
				{
					$album['album_privacy']['allow_view_data'] = @unserialize($album['album_privacy']['allow_view_data']);
				}
				if (isset($album['album_privacy']['allow_comment_data']) && 
						!is_array($album['album_privacy']['allow_comment_data']))
				{
					$album['album_privacy']['allow_comment_data'] = @unserialize($album['album_privacy']['allow_comment_data']);
				}
				if (isset($album['album_privacy']['allow_add_photo_data']) && 
						!is_array($album['album_privacy']['allow_add_photo_data']))
				{
					$album['album_privacy']['allow_add_photo_data'] = @unserialize($album['album_privacy']['allow_add_photo_data']);
				}
				if (isset($album['album_privacy']['allow_add_video_data']) &&
					!is_array($album['album_privacy']['allow_add_video_data']))
				{
					$album['album_privacy']['allow_add_video_data'] = @unserialize($album['album_privacy']['allow_add_video_data']);
				}
				if (isset($album['album_privacy']['allow_download_data']) &&
					!is_array($album['album_privacy']['allow_download_data']))
				{
					$album['album_privacy']['allow_download_data'] = @unserialize($album['album_privacy']['allow_download_data']);
				}
			}

			if (isset($album['category_privacy']))
			{
				$album['category_privacy'] = @unserialize($album['category_privacy']);
			}
			
			$album['allow_view_html'] = new XenForo_Phrase(self::$privacyPhrase['view'].$album['album_privacy']['allow_view']);
			/*
			$album['allow_comment_html'] = new XenForo_Phrase(self::$privacyPhrase['comment'].$album['album_privacy']['allow_comment']);
			$album['allow_add_photo_html'] = new XenForo_Phrase(self::$privacyPhrase['addPhoto'].$album['album_privacy']['allow_add_photo']);
			$album['allow_add_video_html'] = new XenForo_Phrase(self::$privacyPhrase['addVideo'].$album['album_privacy']['allow_add_video']);
			$album['allow_download_html'] = new XenForo_Phrase(self::$privacyPhrase['download'].$album['album_privacy']['allow_download']);
			*/

			$album['likes'] = $this->_formatNumberCount($album['likes']);
			$album['comment_count'] = $this->_formatNumberCount($album['comment_count']);
			$album['view_count'] = $this->_formatNumberCount($album['view_count']);

			$album['canView'] = $this->canViewAlbum($album, $errorKey, $viewingUser);
			$album['canComment'] = $this->canCommentAlbum($album, $errorKey, $viewingUser);
			$album['canEdit'] = $this->canEditAlbum($album, $errorKey, $viewingUser);
			$album['canLike'] = $this->canLikeAlbum($album, $errorKey, $viewingUser);
			$album['canWatch'] = $this->canWatchAlbum($album, $errorKey, $viewingUser);
			$album['canAddPhoto'] = $this->canAddPhoto($album, $errorKey, $viewingUser);
			$album['canAddVideo'] = $this->canAddVideo($album, $errorKey, $viewingUser);
			$album['canReport'] = $this->canReportAlbum($album, $errorKey, $viewingUser);
			$album['canDelete'] = $this->canDeleteAlbum($album, 'soft', $errorKey, $viewingUser);

			$album['canPromote'] = $this->_getCollectionModel()->canAddToCollection($album, $errorKey, $viewingUser);
			$album['canUnPromote'] = $album['collection_id'] && $this->_getCollectionModel()->canRemoveFromCollection($album, $errorKey, $viewingUser);

            $album['canChangeOwner'] = $this->canChangeOwner($album, $viewingUser);
			
			$album['isDeleted'] = $this->isDeleted($album);
			$album['isModerated'] = $this->isModerated($album);
			$album['isIgnored'] = XenForo_Visitor::getInstance()->isIgnoring($album['user_id']);
			
			if ($album['likes'])
			{
				$album['likeUsers'] = @unserialize($album['like_users']);
			}

			if ($album['tags'])
			{
				$album['tagUsers'] = @unserialize($album['tag_users']);
			}

			$album['albumStreams'] = @unserialize($album['album_streams']);
			$album['description'] = XenForo_Helper_String::censorString($album['description']);
			$album['title'] = XenForo_Helper_String::censorString($album['title']);
		}
		
		return $album;
	}
	
	public function prepareAlbums(array $albums, array $fetchOptions = array(), $viewingUser = array())
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if (!empty($albums))
		{		
			foreach ($albums as $albumId=>$album)
			{
				$albums[$albumId] = $this->prepareAlbum($album, $fetchOptions, $viewingUser);
			}
		}
		
		return $albums;
	}
	
	public function attachCoverToAlbum(array $album, array $fetchOptions = array())
	{
		if (!empty($fetchOptions['join']) && $fetchOptions['join'] & self::FETCH_COVER_PHOTO)
		{
			$coverFetchOptions = array(
				'join' => sonnb_XenGallery_Model_Content::FETCH_DATA
			);

			$cover = $this->_getContentModel()->getContentByContentId($album['cover_content_type'], $album['cover_content_id'], $coverFetchOptions);
			$cover = $this->_getContentModel()->prepareContent($cover, $coverFetchOptions);
			
			$album['cover'] = $cover;
		}
		
		return $album;
	}
	
	public function attachCoversToAlbums(array $albums, array $fetchOptions = array())
	{
		if ($albums)
		{
			$coverContentIds = array();
			foreach ($albums as $albumId=>$album)
			{
				if (!empty($fetchOptions['join']) && $fetchOptions['join'] & self::FETCH_COVER_PHOTO)
				{
					$coverContentIds[$albumId] = $album['cover_content_id'];
				}
			}
			
			if ($coverContentIds)
			{
				$coverFetchOptions = array(
					'join' => sonnb_XenGallery_Model_Content::FETCH_DATA
				);

				$covers = $this->_getContentModel()->getContentsByIds(array_values($coverContentIds), $coverFetchOptions);
				$covers = $this->_getContentModel()->prepareContents($covers, $coverFetchOptions);

				foreach ($coverContentIds as $albumId => $contentId)
				{
					if (isset($covers[$contentId]))
					{
						$albums[$albumId]['cover'] = $covers[$contentId];
					}
				}
			}
		}
		
		return $albums;
	}

	public function attachContentsToAlbum(array $album, array $fetchOptions = array())
	{
		if ($album)
		{
			$contentIds = array();

			if ($album['content_count'] && $album['latest_content_ids'])
			{
				$contentIds = explode(',', $album['latest_content_ids']);
			}

			if ($contentIds)
			{
				if (!isset($fetchOptions['join']))
				{
					$fetchOptions['join'] = 0;
				}

				$fetchOptions['join'] |= sonnb_XenGallery_Model_Content::FETCH_DATA;

				$contents = $this->_getContentModel()->getContentsByIds($contentIds, $fetchOptions);
				$contents = $this->_getContentModel()->prepareContents($contents, $fetchOptions);

				$contentModel = $this->_getContentModel();
				foreach ($contents as $_contentId => $_content)
				{
					if (!$contentModel->canViewContent($_content))
					{
						unset($contents[$_contentId]);
					}
				}

				$album['contents'] = $contents;
			}
		}

		return $album;
	}

	public function attachContentsToAlbums(array $albums, array $fetchOptions = array())
	{
		if ($albums)
		{
			$contentIds = array();

			foreach ($albums as $albumId => $album)
			{
				if ($album['content_count'] && $album['latest_content_ids'])
				{
					$lastContentIds = explode(',', $album['latest_content_ids']);

					if ($lastContentIds)
					{
						foreach ($lastContentIds as $contentId)
						{
							$contentIds[$contentId] = $albumId;
						}
					}
				}
			}

			if ($contentIds)
			{
				$contentModel = $this->_getContentModel();
				if (!isset($fetchOptions['join']))
				{
					$fetchOptions['join'] = 0;
				}

				$fetchOptions['join'] |= sonnb_XenGallery_Model_Content::FETCH_DATA;

				$contents = $contentModel->getContentsByIds(array_keys($contentIds), $fetchOptions);
				$contents = $contentModel->prepareContents($contents, $fetchOptions);

				foreach ($contents as $_contentId => $_content)
				{
					if (!$contentModel->canViewContent($_content))
					{
						unset($contents[$_contentId]);
					}
				}

				foreach ($contentIds as $contentId => $albumId)
				{
					if (isset($contents[$contentId]))
					{
						$albums[$albumId]['contents'][] = $contents[$contentId];
					}
				}
			}
		}

		return $albums;
	}

	public function attachContentsToAlbumByType(array $album, $contentType, array $fetchOptions = array())
	{
		if ($album)
		{
			$contentIds = array();

			if ($album[$contentType.'_count'] && $album['latest_'.$contentType.'_ids'])
			{
				$contentIds = explode(',', $album['latest_'.$contentType.'_ids']);
			}

			if ($contentIds)
			{
				if (!isset($fetchOptions['join']))
				{
					$fetchOptions['join'] = 0;
				}

				$fetchOptions['join'] |= sonnb_XenGallery_Model_Content::FETCH_DATA;

				$contents = $this->_getContentModel()->getContentsByIds($contentIds, $fetchOptions);
				$contents = $this->_getContentModel()->prepareContents($contents, $fetchOptions);

				$contentModel = $this->_getContentModel();
				foreach ($contents as $_contentId => $_content)
				{
					if (!$contentModel->canViewContent($_content))
					{
						unset($contents[$_contentId]);
					}
				}

				$album[$contentType.'s'] = $contents;
			}
		}

		return $album;
	}

	public function attachContentsToAlbumsByType(array $albums, $contentType, array $fetchOptions = array())
	{
		if ($albums)
		{
			$contentIds = array();

			foreach ($albums as $albumId => $album)
			{
				if ($album[$contentType.'_count'] && $album['latest_'.$contentType.'_ids'])
				{
					$lastContentIds = explode(',', $album['latest_'.$contentType.'_ids']);

					if ($lastContentIds)
					{
						foreach ($lastContentIds as $contentId)
						{
							$contentIds[$contentId] = $albumId;
						}
					}
				}
			}

			if ($contentIds)
			{
				$contentModel = $this->_getContentModel();
				if (!isset($fetchOptions['join']))
				{
					$fetchOptions['join'] = 0;
				}

				$fetchOptions['join'] |= sonnb_XenGallery_Model_Content::FETCH_DATA;

				$contents = $contentModel->getContentsByIds(array_keys($contentIds), $fetchOptions);
				$contents = $contentModel->prepareContents($contents, $fetchOptions);

				foreach ($contents as $_contentId => $_content)
				{
					if (!$contentModel->canViewContent($_content))
					{
						unset($contents[$_contentId]);
					}
				}

				foreach ($contentIds as $contentId => $albumId)
				{
					if (isset($contents[$contentId]))
					{
						$albums[$albumId]['contents'][] = $contents[$contentId];
					}
				}
			}
		}

		return $albums;
	}
	
	public function createDefaultAlbumForUser(array $user, $type, &$errorPhraseKey = null)
	{
		if (empty($user) || empty($type))
		{
			$errorPhraseKey = 'sonnb_xengallery_please_check_user_data_and_default_type';
			return false;
		}
		
		$albumType = $title = '';
		switch ($type)
		{
			case 'mobile':
				$albumType = self::ALBUM_TYPE_MOBILE;
				$title = new XenForo_Phrase('sonnb_xengallery_mobile_uploads');
				break;
			case 'profile':
				$albumType = self::ALBUM_TYPE_PROFILE;
				$title = new XenForo_Phrase('sonnb_xengallery_profile_pictures');
				break;
			default:
				$errorPhraseKey = 'sonnb_xengallery_invalid_default_album_type';
				return false;
				break;
		}
		
		$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album');
		$albumDw->bulkSet(array(
			'user_id' => $user['user_id'],
			'username' => $user['username'],
			'album_type' => $albumType,
			'title' => $title	
		));
		$albumDw->save();
		
		return $albumDw->getMergedData();
	}
	
	public function logAlbumView($albumId)
	{
		$this->_getDb()->query('
			INSERT ' . (XenForo_Application::getOptions()->enableInsertDelayed ? 'DELAYED' : '') . ' INTO sonnb_xengallery_album_view
				(album_id)
			VALUES
				(?)
		', $albumId);
	}
	
	public function updateAlbumViews()
	{
		$db = $this->_getDb();

		$updates = $db->fetchPairs('
			SELECT album_id, COUNT(*)
			FROM sonnb_xengallery_album_view
			GROUP BY album_id
		');

		XenForo_Db::beginTransaction($db);

		$db->query('TRUNCATE TABLE sonnb_xengallery_album_view');

		foreach ($updates AS $albumId => $views)
		{
			$db->query('
				UPDATE sonnb_xengallery_album SET
					view_count = view_count + ?
				WHERE album_id = ?
			', array($views, $albumId));
		}

		XenForo_Db::commit($db);
	}
	
	public function getUserMaximumAllowedAlbumCount(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'maximumAlbum'); 
	}
	
	public function getAlbumDefaultState(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'moderateAlbum');
	}
	
	public function canViewAlbum(array $album, &$errorPhraseKey = '', array $viewingUser = null, $hash = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if ($this->isDeleted($album) && !$this->canViewDeletedAlbum($album, $viewingUser))
		{
			return false;
		}

		if ($this->isModerated($album) && !$this->canViewModeratedAlbum($album, $viewingUser))
		{
			return false;
		}

        if (!empty($album['album_hash']) && $hash !== null && $album['album_hash'] === $hash)
        {
            return true;
        }
		
		if ($viewingUser['user_id'] == $album['user_id'])
		{
			return true;
		}
		
		if ($this->canViewAnyAlbum($viewingUser))
		{
			return true;
		}

		if (!$this->_getCategoryModel()->canViewCategory($album))
		{
			return false;
		}
		
		return $this->passesPrivacyCheck(
			$album,
			$album['album_privacy']['allow_view'],
			'view',
			isset($album['album_privacy']['allow_view_data']) ? $album['album_privacy']['allow_view_data'] : array(),
			$errorPhraseKey,
			$viewingUser
		);
	}
	
	public function canCommentAlbum(array $album, &$errorPhraseKey = '', array $viewingUser = null)
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
		
		if ($album['user_id'] == $viewingUser['user_id'])
		{
			return true;
		}

		if ($this->canEditAnyAlbum($viewingUser))
		{
			return true;
		}
		
		return $this->passesPrivacyCheck(
			$album,
			$album['album_privacy']['allow_comment'],
			'comment',
			isset($album['album_privacy']['allow_comment_data']) ? $album['album_privacy']['allow_comment_data'] : array(),
			$errorPhraseKey,
			$viewingUser
		);
	}
	
	public function canAddPhoto(array $album, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
	
		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($album['category_id'])
		{
			$category = array();
			$categories = $this->_getCategoryModel()->getAllCachedCategories();
			if (isset($categories[$album['category_id']]))
			{
				$category = $categories[$album['category_id']];
			}

			if ($category)
			{
				if ($this->_getCategoryModel()->canUploadToCategory($category, $errorPhraseKey, $viewingUser) === false)
				{
					return false;
				}
			}
		}
		
		if ($album['user_id'] == $viewingUser['user_id'])
		{
			return true;
		}

		if ($this->canEditAnyAlbum($viewingUser))
		{
			return true;
		}
		
		return $this->passesPrivacyCheck(
			$album,
			$album['album_privacy']['allow_add_photo'],
			'add_photo',
			isset($album['album_privacy']['allow_add_photo_data']) ? $album['album_privacy']['allow_add_photo_data'] : array(),
			$errorPhraseKey,
			$viewingUser
		);
	}

	public function canAddVideo(array $album, &$errorPhraseKey = '', array $viewingUser = null)
	{
		if (!$this->_getGalleryModel()->canEmbedVideo())
		{
			return false;
		}

		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($album['category_id'])
		{
			$category = array();
			$categories = $this->_getCategoryModel()->getAllCachedCategories();
			if (isset($categories[$album['category_id']]))
			{
				$category = $categories[$album['category_id']];
			}

			if ($category)
			{
				if ($this->_getCategoryModel()->canUploadToCategory($category, $errorPhraseKey, $viewingUser) === false)
				{
					return false;
				}
			}
		}

		if ($album['user_id'] == $viewingUser['user_id'])
		{
			return true;
		}

		if ($this->canEditAnyAlbum($viewingUser))
		{
			return true;
		}

		return $this->passesPrivacyCheck(
			$album,
			$album['album_privacy']['allow_add_video'],
			'add_video',
			isset($album['album_privacy']['allow_add_video_data']) ? $album['album_privacy']['allow_add_video_data'] : array(),
			$errorPhraseKey,
			$viewingUser
		);
	}

	public function canDownloadOriginalContent(array $content, array $album, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

        if ($content['content_type'] === sonnb_XenGallery_Model_Video::$contentType && !empty($content['video_key']))
        {
            return false;
        }

		if (XenForo_Application::getOptions()->sonnbXG_disableOriginal)
		{
			return false;
		}

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($album['user_id'] === $viewingUser['user_id'])
		{
			return true;
		}

		if ($this->canEditAnyAlbum($viewingUser) || $this->_getContentModel()->canEditAnyContent($viewingUser))
		{
			return true;
		}

		if (!isset($album['album_privacy']['allow_download']))
		{
			return true;
		}

		return $this->passesPrivacyCheck(
			$album,
			$album['album_privacy']['allow_download'],
			'download',
			isset($album['album_privacy']['allow_download_data']) ? $album['album_privacy']['allow_download_data'] : array(),
			$errorPhraseKey,
			$viewingUser
		);
	}
	
	public function passesPrivacyCheck(array $album, $type, $action, $typeData, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!empty($type))
		{
			switch ($type)
			{
				case 'everyone':
					return true;
					break;
				case 'members':
					$errorPhraseKey = 'sonnb_xengallery_registered_members_can_'.$action.'_this_album';
					return $viewingUser['user_id'];
					break;
				case 'following':
					$errorPhraseKey = 'sonnb_xengallery_members_who_following_this_user_can_'.$action.'_this_album';
					return $this->_getUserModel()->isFollowing($album['user_id'], $viewingUser);
					break;
				case 'followed':
					$errorPhraseKey = 'sonnb_xengallery_members_followed_by_this_user_can_'.$action.'_this_album';

					if (isset($album['following_' . $viewingUser['user_id']]))
					{
						return ($album['following_' . $viewingUser['user_id']] > 0);
					}
					elseif (!empty($album['following']))
					{
						return in_array($viewingUser['user_id'], explode(',', $album['following']));
					}
					else
					{
						return false;
					}
					break;
				case 'custom':
					$errorPhraseKey = 'sonnb_xengallery_specified_members_can_'.$action.'_this_album';
					
					if (!is_array($typeData))
					{
						$typeData = @unserialize($typeData);
					}
					
					if (is_array($typeData) && !empty($typeData))
					{
						return in_array($viewingUser['user_id'], array_keys($typeData));
					}
					break;
				case 'none':
				default:
					$errorPhraseKey = 'sonnb_xengallery_owner_can_'.$action.'_this_album';
					break;
			}
		}
					
		return false;
	}
	
	public function canLikeAlbum(array $album, &$errorPhraseKey = '', array $viewingUser = null)
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
	
		if ($album['user_id'] == $viewingUser['user_id'])
		{
			$errorPhraseKey = 'liking_own_content_cheating';
			return false;
		}
	
		return true;
	}

	public function canWatchAlbum(array $album, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($viewingUser['user_id'] == $album['user_id'])
		{
			return false;
		}

		return true;
	}
    
    public function canReportAlbum(array $album, &$errorPhraseKey = '', array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);
	
		if (!$viewingUser['user_id'])
		{
			return false;
		}
        
        return $this->_getUserModel()->canReportContent($errorPhraseKey, $viewingUser);
    }
    
    public function canEditAlbum(array $album, &$errorPhraseKey = '', array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);
        
        if (!$viewingUser['user_id'])
        {
            return false;
        }
        
        if ($album['user_id'] == $viewingUser['user_id']
                || $this->canEditAnyAlbum($viewingUser)
            )
        {
            return true;
        }
        
        return false;
    }

	public function canChangeOwner(array $album, array $viewingUser = null)
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
    
    public function canDeleteAlbum(array $album, $type, &$errorPhraseKey = '', array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);
        
        if (!$viewingUser['user_id'])
        {
            return false;
        }
        
        if ($album['user_id'] == $viewingUser['user_id'])
        {
        	switch ($type)
        	{
        		case 'soft':
        			return true;
        			break;
        		case 'hard':
			        if ($this->canHardDeleteOwnAlbum($viewingUser))
			        {
				        return true;
			        }
        		default:
        			break;
        	}
        }
        
        if ($this->canDeleteAnyAlbum($type, $viewingUser))
        {
            return true;
        }
        
        return false;
    }
    
    public function getPermissionBasedAlbumFetchConditions(array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

	    if ($this->canViewDeletedAlbum(null, $viewingUser))
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

        if ($this->canViewModeratedAlbum($viewingUser))
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
    
    public function canViewDeletedAlbum(array $album = null, array $viewingUser = null)
    {
    	$this->standardizeViewingUserReference($viewingUser);

	    if (isset($album['user_id']) && $album['user_id'] === $viewingUser['user_id'] && $this->canHardDeleteOwnAlbum($viewingUser))
	    {
		    return true;
	    }
    	
    	return $this->canDeleteAnyAlbum('soft', $viewingUser);
    }

	public function canHardDeleteOwnAlbum(array $viewingUser = null)
	{
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'hardDeleteOwnAlbum');
	}
	
	public function canViewAnyAlbum(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		 
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'viewAnyAlbum');
	}
	
	public function canViewModeratedAlbum(array $album, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($album['user_id'] === $viewingUser['user_id'])
		{
			return true;
		}
		 
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'viewModeratedAlbum');
	}
    
    public function canEditAnyAlbum(array $viewingUser = null)
    {
    	$this->standardizeViewingUserReference($viewingUser);
    	
    	return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'editAnyAlbum');
    }
    
    public function canDeleteAnyAlbum($type, array $viewingUser = null)
    {
    	$this->standardizeViewingUserReference($viewingUser);
    	
    	$hardDelete = XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'deleteAnyAlbumHard');
    	$softDelete = XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'deleteAnyAlbumSoft');
    	
    	if ($type === 'soft')
    	{
    		return ($softDelete || $hardDelete);
    	}
    	else
    	{
    		return $hardDelete;
    	}
    }
	
	public function getAlbumBreadCrumbs(array $album, $includeSelf = true)
	{		
		$breadCrumbs = array();

		if ($album['collection_id'])
		{
			$parentBreadCrumbs = $this->_getCollectionModel()->getCollectionBreadCrumb($album['collection_id']);

			if ($parentBreadCrumbs)
			{
				$breadCrumbs = $breadCrumbs + $parentBreadCrumbs;
			}
		}
		elseif ($album['category_id'])
		{
			$parentBreadCrumbs = $this->_getCategoryModel()->getCategoryBreadCrumbs($album['category_id']);
			
			if ($parentBreadCrumbs)
			{
				foreach ($parentBreadCrumbs as $breadId => $bread)
				{
					$breadCrumbs[$breadId] = $bread;
				}
			}
		}

		if (XenForo_Application::getOptions()->sonnb_XG_showOwnerNav)
		{
			$breadCrumbs[$album['user_id']] = array(
				'href' => XenForo_Link::buildPublicLink('full:gallery/authors', $album),
				'value' => $album['username']
			);
		}

		$breadCrumbs[$album['album_id']] = array(
			'href' => XenForo_Link::buildPublicLink('full:gallery/albums', $album),
			'value' => $album['title'],
			'album_id' => $album['album_id']
		);

		return $breadCrumbs;
	}

	public function modifyAlbumCount($categoryId, $modifyValue)
	{
		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Category', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($categoryId);
		$dw->set('album_count', $dw->get('album_count') + $modifyValue > 0 ? $dw->get('album_count') + $modifyValue : 0);
		$dw->save();

		return $dw->get('album_count');
	}

	public function modifyCollectionCount($collectionId, $modifyValue, array $album, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Collection', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($collectionId);

		$itemCount = $dw->get('item_count') + $modifyValue;

		$dw->set('item_count', $itemCount > 0 ? $itemCount : 0);
		$dw->save();

		if ($modifyValue > 0)
		{
			$this->_getCollectionModel()->insertCollectedItem(
				$collectionId,
				sonnb_XenGallery_Model_Album::$contentType,
				$album['album_id']
			);
		}
		else
		{
			$this->_getCollectionModel()->removeCollectedItemByCollectContent(
				$collectionId,
				sonnb_XenGallery_Model_Album::$contentType,
				$album['album_id']
			);
		}

		return $dw->get('item_count');
	}
	
	public function isDeleted(array $album)
	{
		return ($album['album_state'] === 'deleted');
	}
	
	public function isModerated(array $album)
	{
		return ($album['album_state'] === 'moderated');
	}
	
	public function isVisible(array $album)
	{
		return ($album['album_state'] === 'visible');
	}
	
	public function prepareAlbumFetchOptions(array $fetchOptions)
	{
        $selectFields = '';
        $joinTables = '';
        $orderBy = '';

        if (!empty($fetchOptions['order']))
        {
            $orderBySecondary = '';

            switch ($fetchOptions['order'])
            {
                case 'album_id':
                case 'user_id':
                case 'title':
                case 'album_type':
                case 'album_state':
                case 'category_id':
                case 'comment_count':
                case 'view_count':
                case 'photo_count':
	            case 'video_count':
	            case 'content_count':
                case 'likes':
                case 'album_date':
                    $orderBy = 'album.' . $fetchOptions['order'];
                    $orderBySecondary = ', album.album_updated_date DESC';
                    break;
	            case 'collection_date':
		            $orderBy = 'collection.collection_date';
		            $orderBySecondary = ', album.album_updated_date ASC';
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
                                        ON (liked_content_sort.content_type = \''.self::$xfContentType.'\'
                                                AND liked_content_sort.content_id = album.album_id)';

		            $orderBy = 'liked_content_sort.like_date';
		            $orderBySecondary = ', album.album_updated_date ASC';
		            break;
                case 'album_updated_date':
                default:
                    $orderBy = 'album.album_updated_date';
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
                                        ON (liked_content.content_type = \''.self::$xfContentType.'\'
                                                AND liked_content.content_id = album.album_id
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
                                        ON (watch.content_type = \''.self::$contentType.'\'
        										AND watch.content_id = album.album_id
                                                AND watch.user_id = ' . $this->_getDb()->quote($fetchOptions['watchUserId']) . ')';
        	}
        }

		if (isset($fetchOptions['fetchCollectionDate']))
		{
			$selectFields .= ',
					IF(collection.collection_date IS NOT NULL, collection.collection_date, 0) AS collection_date';
			$joinTables .= "
					LEFT JOIN `sonnb_xengallery_collection_item` AS collection ON
						(collection.collection_id = album.collection_id
							AND collection.content_type = '".self::$contentType."'
							AND collection.content_id = album.album_id)";
		}
        
        if (!empty($fetchOptions['join']))
        {        	
        	if ($fetchOptions['join'] & self::FETCH_USER)
        	{
        		$selectFields .= ',
					IF(user.username IS NULL, album.username, user.username) AS username, user.avatar_date, user.gravatar';
        		$joinTables .= '
					LEFT JOIN `xf_user` AS user ON
						(user.user_id = album.user_id)';
        	}
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
						(user_follow.user_id = album.user_id AND user_follow.follow_user_id = ' . $fetchOptions['followingUserId'] . ')';
			}
			else
			{
				$selectFields .= ',
					0 AS following_0';
			}
		}

		$selectFields .= ',
					category.category_privacy';
		$joinTables .= '
					LEFT JOIN `sonnb_xengallery_category` AS category ON
						(album.category_id = category.category_id)';
        
        return array(
        		'selectFields' => $selectFields,
        		'joinTables' => $joinTables,
        		'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
        );
	}
	
	public function prepareAlbumConditions(array $conditions, array &$fetchOptions)
	{
        $sqlConditions = array();
        $db = $this->_getDb();
        
        if (isset($conditions['album_id']))
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

        if (isset($conditions['user_id']))
        {
            if (is_array($conditions['user_id']))
            {
                $sqlConditions[] = 'album.user_id IN (' . $db->quote($conditions['user_id']) . ')';
            }
            else
            {
                $sqlConditions[] = 'album.user_id = ' . $db->quote($conditions['user_id']);
            }
        }
        
        if (isset($conditions['collection_id']))
        {
            if (is_array($conditions['collection_id']))
            {
                $sqlConditions[] = 'album.collection_id IN (' . $db->quote($conditions['collection_id']) . ')';
            }
            else
            {
                $sqlConditions[] = 'album.collection_id = ' . $db->quote($conditions['collection_id']);
            }
        }

        if (isset($conditions['category_id']))
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

		if (!empty($conditions['title']))
		{
			if (is_array($conditions['title']))
			{
				$sqlConditions[] = 'album.title IN (' . $db->quote($conditions['title']) . ')';
			}
			else
			{
				$sqlConditions[] = 'album.title = ' . $db->quote($conditions['title']);
			}
		}
        
        if (isset($conditions['album_type']))
        {
            if (is_array($conditions['album_type']))
            {
                $sqlConditions[] = 'album.album_type IN (' . $db->quote($conditions['album_type']) . ')';
            }
            else
            {
                $sqlConditions[] = 'album.album_type = ' . $db->quote($conditions['album_type']);
            }
        }
        
        if (!empty($conditions['album_state']))
        {
            if (is_array($conditions['album_state']))
            {
                $sqlConditions[] = 'album.album_state IN (' . $db->quote($conditions['album_state']) . ')';
            }
            else
            {
                $sqlConditions[] = 'album.album_state = ' . $db->quote($conditions['album_state']);
            }
        }
        
        if (isset($conditions['album_location']))
        {
            if (is_array($conditions['album_location']))
            {
                $sqlConditions[] = 'album.album_location IN (' . $db->quote($conditions['album_location']) . ')';
            }
            else
            {
                $sqlConditions[] = 'album.album_location = ' . $db->quote($conditions['album_location']);
            }
        }

		if (!empty($conditions['titleLike']))
		{
			if (is_array($conditions['titleLike']))
			{
				$sqlConditions[] = 'album.title LIKE ' . XenForo_Db::quoteLike($conditions['titleLike'][0], $conditions['titleLike'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'album.title LIKE ' . XenForo_Db::quoteLike($conditions['titleLike'], 'lr', $db);
			}
		}

		if (!empty($conditions['cover_content_id']) && is_array($conditions['cover_content_id']))
		{
			list($operator, $cutOff) = $conditions['cover_content_id'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "album.cover_content_id $operator " . $db->quote($cutOff);
		}

		if (!empty($conditions['content_count']) && is_array($conditions['content_count']))
		{
			list($operator, $cutOff) = $conditions['content_count'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "album.content_count $operator " . $db->quote($cutOff);
		}

		if (!empty($conditions['photo_count']) && is_array($conditions['photo_count']))
		{
			list($operator, $cutOff) = $conditions['photo_count'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "album.photo_count $operator " . $db->quote($cutOff);
		}

		if (isset($conditions['deleted']) || isset($conditions['moderated']))
		{
			$sqlConditions[] = $this->prepareStateLimitFromConditions($conditions, 'album', 'album_state');
		}

        if (!empty($conditions['likes']) && is_array($conditions['likes']))
        {
            list($operator, $cutOff) = $conditions['likes'];

            $this->assertValidCutOffOperator($operator);
            $sqlConditions[] = "album.likes $operator " . $db->quote($cutOff);
        }

		if (!empty($conditions['view_count']) && is_array($conditions['view_count']))
		{
			list($operator, $cutOff) = $conditions['view_count'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "album.view_count $operator " . $db->quote($cutOff);
		}

		if (!empty($conditions['comment_count']) && is_array($conditions['comment_count']))
		{
			list($operator, $cutOff) = $conditions['comment_count'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "album.comment_count $operator " . $db->quote($cutOff);
		}

        if (!empty($conditions['album_date']) && is_array($conditions['album_date']))
        {
            list($operator, $cutOff) = $conditions['album_date'];

            $this->assertValidCutOffOperator($operator);
            $sqlConditions[] = "album.album_date $operator " . $db->quote($cutOff);
        }

        if (!empty($conditions['album_updated_date']) && is_array($conditions['album_updated_date']))
        {
            list($operator, $cutOff) = $conditions['album_updated_date'];

            $this->assertValidCutOffOperator($operator);
            $sqlConditions[] = "album.album_updated_date $operator " . $db->quote($cutOff);
        }
        
        return $this->getConditionsForClause($sqlConditions);
	}
	
	/**
	 * 
	 * @return sonnb_XenGallery_Model_Photo
	 */
	protected function _getPhotoModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Photo');
	}
	
	/**
	 * 
	 * @return sonnb_XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Comment');
	}

	/**
	 *
	 * @return sonnb_XenGallery_Model_Category
	 */
	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Category');
	}

	/**
	 *
	 * @return sonnb_XenGallery_Model_Collection
	 */
	protected function _getCollectionModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Collection');
	}

	/**
	 *
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}
