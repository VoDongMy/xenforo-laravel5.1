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
class sonnb_XenGallery_Model_Tag extends sonnb_XenGallery_Model_Abstract
{
	const FETCH_USER = 0x01;
	const FETCH_TAGGER = 0x02;
	
	public function getTagById($id, array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
		 
		$conditions['tag_id'] = $id;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getTags($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}
	
	public function getTagsByIds($ids, array $fetchOptions = array())
	{
		if (!$ids)
        {
            return array();
        }
        
        $conditions['tag_id'] = $ids;
        
        return $this->getTags($conditions, $fetchOptions);
	}
	
	public function getTagsByUserId($id, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
		
		$conditions['user_id'] = $id;
		
		return $this->getTags($conditions, $fetchOptions);
	}

	public function getTagsByUserIds($id, array $conditions = array(), array $fetchOptions = array())
	{
		return $this->getTagsByUserId($id, $conditions, $fetchOptions);
	}

	public function getTagByContentUserId($contentType, $contentId, $userId, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$contentId || !$userId)
		{
			return array();
		}

		$conditions['content_type'] = $contentType;
		$conditions['content_id'] = $contentId;
		$conditions['user_id'] = XenForo_Visitor::getUserId();
		$conditions['limit'] = XenForo_Visitor::getUserId();

		$return = $this->getTags($conditions, $fetchOptions);

		return (empty($return) ? array() : reset($return));
	}

	public function getTagsByContentId($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$contentId)
		{
			return array();
		}

		$conditions['content_type'] = $contentType;
		$conditions['content_id'] = $contentId;
		
		return $this->getTags($conditions, $fetchOptions);
	}

	public function getTagsByContentIds($contentType, $contentIds, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$contentIds)
		{
			return array();
		}

		$conditions['content_type'] = $contentType;
		$conditions['content_id'] = $contentIds;

		return $this->getTags($conditions, $fetchOptions);
	}
	
	public function getTags(array $conditions = array(), array $fetchOptions = array())
	{		 
		$whereConditions = $this->prepareTagConditions($conditions, $fetchOptions);
		
		$sqlClauses = $this->prepareTagFetchOptions($fetchOptions);
		
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		
		return $this->fetchAllKeyed(
					$this->limitQueryResults(
						'
		                   SELECT gallery_tag.*
		                        ' . $sqlClauses['selectFields'] . '
		                   FROM `sonnb_xengallery_tag` AS gallery_tag
		                    	' . $sqlClauses['joinTables'] . '
		                   WHERE ' . $whereConditions . '
		                    	' . $sqlClauses['orderClause'] . '
	                	', $limitOptions['limit'], $limitOptions['offset']
					), 'tag_id'
				);
	}
	
	public function countTagByContentId($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		$conditions['content_type'] = $contentType;
		$conditions['content_id'] = $contentId;
		
		return $this->countTags($conditions, $fetchOptions);
	}
	
	public function countTags(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareTagConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareTagFetchOptions($fetchOptions);
		
		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_tag` AS gallery_tag
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
            ');
	}
	
	public function prepareTag(array $tag, array $fetchOptions = array())
	{
		if (!empty($tag))
		{
			
		}
		
		return $tag;
	}
	
	public function prepareTags(array $tags, array $fetchOptions = array())
	{
		if (!empty($tags))
		{
			foreach ($tags as $tagId=>$tag)
			{
				$tags[$tagId] = $this->prepareTag($tag);
			}
		}
		
		return $tags;
	}
	
	public function addTagUsers($usernames, $contentType, $contentId, $overwrite = false, array $positions = null, $viewingUser = null)
	{
		if (!$contentId)
		{
			return false;
		}

		list($tagUsers, $alertUsers, $directUsers) = $this->_getTaggableUsers($usernames, $contentType, $contentId, $overwrite, $viewingUser);

		if (!$tagUsers)
		{
			return false;
		}
		
		$this->standardizeViewingUserReference($viewingUser);

		$returnTags = array();
		foreach ($tagUsers as $key => $user)
		{
			/** @var sonnb_XenGallery_DataWriter_Tag $tagDw */
			$tagDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Tag');
				
			$tagDw->bulkSet($user);
			$tagDw->bulkSet(array(
				'content_type' => $contentType,
				'content_id' => $contentId,
				'tagger_user_id' => $viewingUser['user_id'],
				'tagger_username' => $viewingUser['username']
			));

			if (isset($directUsers[$key]))
			{
				$tagDw->set('tag_state', 'accepted');
			}
				
			if ($this->isValidTagPosition($positions, $user['username']))
			{
				$tagDw->bulkSet(array(
					'tag_x' => $positions[$user['username']]['tag_x'],
					'tag_y' => $positions[$user['username']]['tag_y'],
				));
			}
				
			$tagDw->save();
			$_tagSave = $tagDw->getMergedData();
			$returnTags[$tagDw->get('tag_id')] = $_tagSave;
		}

		if (!empty($alertUsers))
		{
			$xfContentType = $this->_getXfContentType($contentType);

			$this->massAlert($viewingUser, $alertUsers, $directUsers, $xfContentType, $contentId, 'tag');
		}

		if ($overwrite !== false)
		{
			$table = 'sonnb_xengallery_content';
			$field = 'content_id';
			if ($contentType === sonnb_XenGallery_Model_Album::$contentType)
			{
				$table = 'sonnb_xengallery_album';
				$field = 'album_id';
			}
			$this->_getDb()->update(
				$table,
				array(
					'tag_users' => serialize($returnTags),
					'tags' => count($returnTags)
				),
				array(
					"$field = ?" => $contentId
				)
			);
		}

		return $returnTags;
	}

	public function addTagUser($username, $contentType, $contentId, array $position = null, $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$contentId)
		{
			return false;
		}

		list($tagUsers, $alertUsers, $directUsers) = $this->_getTaggableUsers($username, $contentType, $contentId, false, $viewingUser);

		if (!$tagUsers)
		{
			return;
		}

		$tagUser = reset($tagUsers);

		/** @var sonnb_XenGallery_DataWriter_Tag $tagDw */
		$tagDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Tag');

		$tagDw->bulkSet($tagUser);
		$tagDw->bulkSet(array(
			'content_type' => $contentType,
			'content_id' => $contentId,
			'tagger_user_id' => $viewingUser['user_id'],
			'tagger_username' => $viewingUser['username']
		));

		if (isset($directUsers[$tagUser['user_id']]))
		{
			$tagDw->set('tag_state', 'accepted');
		}

		if ($this->isValidTagPosition($position))
		{
			$tagDw->bulkSet(array(
				'tag_x' => $position['tag_x'],
				'tag_y' => $position['tag_y'],
			));
		}

		$tagDw->save();
		$returnTag = $tagDw->getMergedData();

		if (!empty($alertUsers))
		{
			$xfContentType = $this->_getXfContentType($contentType);

			$this->massAlert($viewingUser, $alertUsers, $directUsers, $xfContentType, $contentId, 'tag');
		}

		return $returnTag;
	}

	public function massAlert($viewingUser, $alertUsers, $directUsers, $contentType, $contentId, $action)
	{
		if (empty($alertUsers))
		{
			return;
		}

		$db = $this->_getDb();
		$values = array();

		foreach ($alertUsers as $key => $alertUser)
		{
			$extra = array();
			if (isset($directUsers[$key]))
			{
				$extra = array('direct' => true);
			}

			$dataArray = array(
				$alertUser['user_id'],
				$db->quote($viewingUser['user_id']),
				$db->quote($viewingUser['username']),
				$db->quote($contentType),
				$db->quote($contentId),
				$db->quote($action),
				XenForo_Application::$time,
				"'".serialize($extra)."'"
			);

			$values[] = '('. implode(',', $dataArray).')';
		}

		$queryInsert = '
					INSERT INTO `xf_user_alert`
						(alerted_user_id, user_id, username, content_type, content_id, action, event_date, extra_data)
					VALUES
							' . implode(', ', $values) . '
					';

		$db->query($queryInsert);

		$queryUpdate = 'UPDATE `xf_user` SET
				alerts_unread = alerts_unread + 1
				WHERE user_id IN ('. $db->quote(array_keys($alertUsers)) .');';

		$db->query($queryUpdate);
	}
	
	protected function _getTaggableUsers($usernames, $contentType, $contentId, $overwrite = false, $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!is_array($usernames))
		{
			$usernames = explode(',', $usernames);
			$usernames = array_filter($usernames);
		}

		$tagUsers = $alertUsers = $directUsers = array();
	
		if (!empty($usernames))
		{
			$userModel = $this->_getUserModel();
			$users = $userModel->getUsersByNames(
				$usernames,
				array(
					'join' => XenForo_Model_User::FETCH_USER_PRIVACY | XenForo_Model_User::FETCH_USER_OPTION,
					'followingUserId' => $viewingUser['user_id']
				)
			);
	
			$userKeys = array('user_id', 'username');
			$taggedUsers = array();
			if ($overwrite === false)
			{
				$taggedUsers = $this->getTagsByContentId($contentType, $contentId);
			}

			$excludeArray = array();
			if ($taggedUsers)
			{
				foreach ($taggedUsers as $tagUser)
				{
					$excludeArray[] = $tagUser['user_id'];
				}
			}

			$xfContentType = '';
			switch ($contentType)
			{
				case sonnb_XenGallery_Model_Album::$contentType:
					$xfContentType = sonnb_XenGallery_Model_Album::$xfContentType;
					break;
				case sonnb_XenGallery_Model_Photo::$contentType:
					$xfContentType = sonnb_XenGallery_Model_Photo::$xfContentType;
					break;
			}
	
			foreach ($users AS $key => $user)
			{
				if (in_array($key, $excludeArray))
				{
					continue;
				}

				$user = $userModel->prepareUser($user);

				if ($this->canTagUser($user, $errorPhraseKey, $viewingUser))
				{
					$tagUsers[$key] = XenForo_Application::arrayFilterKeys($user, $userKeys);

					if ($this->canDirectTagging($user, $viewingUser))
					{
						$directUsers[$key] = $user;
					}
				}

				if ($viewingUser['user_id'] != $user['user_id'] &&
					!$userModel->isUserIgnored($user, $viewingUser['user_id']) &&
					XenForo_Model_Alert::userReceivesAlert($user, $xfContentType, 'tag')
				)
				{
					$alertUsers[$key] = $user;
				}
			}
		}
	
		return array($tagUsers, $alertUsers, $directUsers);
	}
	
	public function canTagUser(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
	
		if ($user['user_id'] == $viewingUser['user_id'])
		{
			return true;
		}

		if (empty($user['xengallery']['allow_tagging']))
		{
			return true;
		}

		switch ($user['xengallery']['allow_tagging'])
		{
			case 'everyone':
				return true;
				break;
			case 'members':
				return $viewingUser['user_id'];
				break;
			case 'followed':
				if (isset($photo['following_' . $viewingUser['user_id']]))
				{
					return ($photo['following_' . $viewingUser['user_id']] > 0);
				}
				elseif (!empty($photo['following']))
				{
					return in_array($viewingUser['user_id'], explode(',', $photo['following']));
				}
				else
				{
					return false;
				}
				break;
			case 'following':
				return $this->_getUserModel()->isFollowing($user['user_id'], $user);
				break;
			case 'none':
			default:
				return false;
				break;
		}

		return false;
	}

	public function canDirectTagging(array $user, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($user['user_id'] == $viewingUser['user_id'])
		{
			return true;
		}

		if (!isset($user['xengallery']['direct_tagging']))
		{
			return true;
		}

		return $user['xengallery']['direct_tagging'];
	}

	public function isValidTagPosition(array $positions = null, $username = false)
	{
		if ($username !== false)
		{
			if (!empty($positions[$username]) &&
					!empty($positions[$username]['tag_x']) &&
					!empty($positions[$username]['tag_y']))
			{
				return true;
			}

			return false;
		}

		if (!empty($positions['tag_x']) && !empty($positions['tag_y']))
		{
			return true;
		}

		return false;
	}
	
	public function prepareTagFetchOptions(array $fetchOptions)
	{
        $selectFields = '';
        $joinTables = '';
        $orderBy = '';

        if (!empty($fetchOptions['order']))
        {
            $orderBySecondary = '';

            switch ($fetchOptions['order'])
            {
                case 'tag_id':
                case 'user_id':
                case 'content_type':
	            case 'content_id':
                case 'tag_state':
                case 'tagger_user_id':
                case 'tagger_username':
                    $orderBy = 'gallery_tag.' . $fetchOptions['order'];
                    $orderBySecondary = ', gallery_tag.tag_date DESC';
                    break;
                case 'tag_date':
                default:
                    $orderBy = 'gallery_tag.tag_date';
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
					IF(user.username IS NULL, gallery_tag.username, user.username) AS username, user.avatar_date, user.gravatar';
        		$joinTables .= '
					LEFT JOIN `xf_user` AS user ON
						(user.user_id = gallery_tag.user_id)';
        	}   
        	    	
        	if ($fetchOptions['join'] & self::FETCH_TAGGER)
        	{
        		$selectFields .= ',
					IF(user.username IS NULL, tagger_user.username, user.username) AS tagger_username, tagger_user.tagger_avatar_date, tagger_user.tagger_gravatar';
        		$joinTables .= '
					LEFT JOIN `xf_user` AS tagger_user ON
						(tagger_user.user_id = gallery_tag.tagger_user_id)';
        	}
        }
        
        return array(
        		'selectFields' => $selectFields,
        		'joinTables' => $joinTables,
        		'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
        );
	}
	
	public function prepareTagConditions(array $conditions, array &$fetchOptions)
	{
        $sqlConditions = array();
        $db = $this->_getDb();
        
        if (!empty($conditions['tag_id']))
        {
        	if (is_array($conditions['tag_id']))
        	{
        		$sqlConditions[] = 'gallery_tag.tag_id IN (' . $db->quote($conditions['tag_id']) . ')';
        	}
        	else
        	{
        		$sqlConditions[] = 'gallery_tag.tag_id = ' . $db->quote($conditions['tag_id']);
        	}
        }

        if (isset($conditions['user_id']))
        {
            if (is_array($conditions['user_id']))
            {
                $sqlConditions[] = 'gallery_tag.user_id IN (' . $db->quote($conditions['user_id']) . ')';
            }
            else
            {
                $sqlConditions[] = 'gallery_tag.user_id = ' . $db->quote($conditions['user_id']);
            }
        }
		
		if (!empty($conditions['content_type']))
		{
			if (is_array($conditions['content_type']))
			{
				$sqlConditions[] = 'gallery_tag.content_type IN (' . $db->quote($conditions['content_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'gallery_tag.content_type = ' . $db->quote($conditions['content_type']);
			}
		}
		
		if (!empty($conditions['content_id']))
		{
			if (is_array($conditions['content_id']))
			{
				$sqlConditions[] = 'gallery_tag.content_id IN (' . $db->quote($conditions['content_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'gallery_tag.content_id = ' . $db->quote($conditions['content_id']);
			}
		}
        
        if (!empty($conditions['tag_state']))
        {
            if (is_array($conditions['tag_state']))
            {
                $sqlConditions[] = 'gallery_tag.tag_state IN (' . $db->quote($conditions['tag_state']) . ')';
            }
            else
            {
                $sqlConditions[] = 'gallery_tag.tag_state = ' . $db->quote($conditions['tag_state']);
            }
        }
        
        if (!empty($conditions['tagger_user_id']))
        {
            if (is_array($conditions['tagger_user_id']))
            {
                $sqlConditions[] = 'gallery_tag.tagger_user_id IN (' . $db->quote($conditions['tagger_user_id']) . ')';
            }
            else
            {
                $sqlConditions[] = 'gallery_tag.tagger_user_id = ' . $db->quote($conditions['tagger_user_id']);
            }
        }

        if (!empty($conditions['tag_date']) && is_array($conditions['tag_date']))
        {
            list($operator, $cutOff) = $conditions['tag_date'];

            $this->assertValidCutOffOperator($operator);
            $sqlConditions[] = "gallery_tag.tag_date $operator " . $db->quote($cutOff);
        }
        
        return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}
