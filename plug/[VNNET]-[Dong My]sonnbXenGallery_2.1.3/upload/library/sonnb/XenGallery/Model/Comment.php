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
class sonnb_XenGallery_Model_Comment extends sonnb_XenGallery_Model_Abstract
{
	const FETCH_USER = 0x01;
	
	public static $contentType = 'comment';
	public static $xfContentType = 'sonnb_xengallery_comment';
	
	public function getCommentById($id, array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
			
		$conditions['comment_id'] = $id;
		$fetchOptions['limit'] = 1;
		$fetchOptions['offset'] = 0;
		
		$return = $this->getComments($conditions, $fetchOptions);
		
		return (empty($return) ? array() : reset($return));
	}
	
	public function getCommentsByIds($ids, array $fetchOptions = array())
	{
		if (!$ids)
		{
			return array();
		}
		
		$conditions['comment_id'] = $ids;
		
		return $this->getComments($conditions, $fetchOptions);
	}
	
	public function getCommentsByUserId($id, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$id)
		{
			return array();
		}
		
		$conditions['user_id'] = $id;
		
		return $this->getComments($conditions, $fetchOptions);
	}

	public function getCommentsByUserIds($id, array $conditions = array(), array $fetchOptions = array())
	{
		return $this->getCommentsByUserId($id, $conditions, $fetchOptions);
	}

	public function getCommentsByContentId($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$contentType || !$contentId)
		{
			return array();
		}
		
		$conditions['content_type'] = $contentType;
		$conditions['content_id'] = $contentId;
		
		return $this->getComments($conditions, $fetchOptions);
	}

	public function getCommentsByContentIds($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		return $this->getCommentsByContentId($contentType, $contentId, $conditions, $fetchOptions);
	}
	
	public function getComments(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareCommentConditions($conditions, $fetchOptions);
		
		$sqlClauses = $this->prepareCommentFetchOptions($fetchOptions);
		
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		
		return $this->fetchAllKeyed(
				$this->limitQueryResults(
						'
		                   SELECT comment.*
		                        ' . $sqlClauses['selectFields'] . '
		                   FROM `sonnb_xengallery_comment` AS comment
		                    	' . $sqlClauses['joinTables'] . '
		                   WHERE ' . $whereConditions . '
		                    	' . $sqlClauses['orderClause'] . '
	                	', $limitOptions['limit'], $limitOptions['offset']
				), 'comment_id'
		);
	}
	
	public function countCommentsByContentId($contentType, $contentId, array $conditions = array(), array $fetchOptions = array())
	{
		if (!$contentType || !$contentId)
		{
			return array();
		}
		
		$conditions['content_type'] = $contentType;
		$conditions['content_id'] = $contentId;
		
		return $this->countComments($conditions, $fetchOptions);
	}
	
	public function countComments(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareCommentConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareCommentFetchOptions($fetchOptions);
		
		return $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `sonnb_xengallery_comment` AS comment
                	' . $sqlClauses['joinTables'] . '
                WHERE ' . $whereConditions . '
            ');
	}
	
	public function prepareComment(array $comment, array $fetchOptions = array(), $viewingUser = array())
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if (!empty($comment))
		{
			$comment['canView'] = $this->canViewComment($comment, $errorKey, $viewingUser);
			$comment['canEdit'] = $this->canEditComment($comment, $errorKey, $viewingUser);
			$comment['canDelete'] = $this->canDeleteComment($comment, 'soft', $errorKey, $viewingUser);
			$comment['canLike'] = $this->canLikeComment($comment, $null, $viewingUser);
			$comment['canReport'] = $this->canReportComment($comment, $null, $viewingUser);

			$comment['isDeleted'] = $this->isDeleted($comment);
			$comment['isModerated'] = $this->isModerated($comment);
			$comment['isIgnored'] = XenForo_Visitor::getInstance()->isIgnoring($comment['user_id']);

			$comment['title'] = XenForo_Helper_String::censorString($comment['message']);
			
			if ($comment['likes'])
			{
				$comment['likeUsers'] = @unserialize($comment['like_users']);
			}
		}
		
		return $comment;
	}
	
	public function prepareComments(array $comments, array $fetchOptions = array(), $viewingUser = array())
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if ($comments)
		{
			foreach ($comments as $commentId=>$comment)
			{
				$comments[$commentId] = $this->prepareComment($comment, $fetchOptions, $viewingUser);
			}
		}
		
		return $comments;
	}
	
	public function addCommentsToContents(array $contents, $contentKey = 'album_id', array $fetchOptions = null)
	{
		if (!isset($fetchOptions['join']))
		{
			$fetchOptions['join'] = self::FETCH_USER;
		}
		else
		{
			$fetchOptions['join'] |= self::FETCH_USER;
		}
		
		$commentIdMap = array();
	
		foreach ($contents AS &$content)
		{
			if ($content['latest_comment_ids'])
			{
				foreach (explode(',', $content['latest_comment_ids']) AS $commentId)
				{
					$commentIdMap[intval($commentId)] = $content[$contentKey];
				}
			}
	
			$content['comments'] = array();
		}
	
		if ($commentIdMap)
		{
			$comments = $this->getCommentsByIds(array_keys($commentIdMap), $fetchOptions);
			$comments = $this->prepareComments($comments, $fetchOptions);
				
			foreach ($commentIdMap AS $commentId => $contentId)
			{
				if (isset($comments[$commentId]) && $comments['canView'])
				{
					$contents[$contentId]['comments'][$commentId] = $comments[$commentId];
				}
			}
		}
	
		return $contents;
	}
	
	public function addCommentsToContent(array $content, array $fetchOptions = null)
	{
		if ($content['latest_comment_ids'])
		{
			if (!isset($fetchOptions['join']))
			{
				$fetchOptions['join'] = self::FETCH_USER;
			}
			else
			{
				$fetchOptions['join'] |= self::FETCH_USER;
			}
			
			$commentIds = explode(',', $content['latest_comment_ids']);
				
			if ($commentIds)
			{
				$comments = $this->getCommentsByIds($commentIds, $fetchOptions);
				$content['comments'] = $this->prepareComments($comments, $fetchOptions);
			}
		}
	
		return $content;
	}
	
	public function getCommentBreadCrumbs(array $comment, array $content, array $album)
	{
		switch ($comment['content_type'])
		{
			case sonnb_XenGallery_Model_Album::$contentType:
				$breadCrumbs = $this->getModelFromCache('sonnb_XenGallery_Model_Album')->getAlbumBreadCrumbs($album);
				break;
			case sonnb_XenGallery_Model_Photo::$contentType:
			case sonnb_XenGallery_Model_Video::$contentType:
				$breadCrumbs = $this->getModelFromCache('sonnb_XenGallery_Model_Photo')->getContentBreadCrumbs($content, $album);
				break;
		}

		return $breadCrumbs;
	}
	
	public function getCommentDefaultState(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'moderateComment');
	}
	
	public function canViewComment(array $comment, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
	
		if ($this->isDeleted($comment) && !$this->canDeleteAnyComment('soft', $viewingUser))
		{
			return false;
		}
	
		if ($this->isModerated($comment))
		{
			if (!$this->canViewModeratedComment($viewingUser))
			{
				return false;
			}
		}
	
		return true;
	}
	
	public function canLikeComment(array $comment, &$errorPhraseKey = '', array $viewingUser = null)
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
	
		if ($comment['user_id'] == $viewingUser['user_id'])
		{
			$errorPhraseKey = 'liking_own_content_cheating';
			return false;
		}
	
		return true;
	}
	
	public function canReportComment(array $comment, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
	
		return $this->getModelFromCache('XenForo_Model_User')->canReportContent($errorPhraseKey, $viewingUser);
	}
	
	public function canEditComment(array $comment, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
	
		if (!$viewingUser['user_id'])
		{
			return false;
		}
	
		if ($comment['user_id'] == $viewingUser['user_id']
				|| $this->canEditAnyComment($viewingUser)
		)
		{
			return true;
		}
	
		return false;
	}
	
	public function canDeleteComment(array $comment, $type, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
	
		if (!$viewingUser['user_id'])
		{
			return false;
		}
        
        if ($comment['user_id'] == $viewingUser['user_id'])
        {
        	switch ($type)
        	{
        		case 'soft':
        			return true;
        			break;
        		case 'hard':
			        if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'hardDeleteOwnContents'))
			        {
				        return true;
			        }
        		default:
        			break;
        	}
        }
	
		if ($this->canDeleteAnyComment($type, $viewingUser))
		{
			return true;
		}
	
		return false;
	}
	
	public function getPermissionBasedCommentFetchConditions(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($this->canViewDeletedComment(null, $viewingUser))
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
	
		if ($this->canViewModeratedComment($viewingUser))
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
	
	public function canViewDeletedComment(array $comment = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (isset($comment['user_id']) && $comment['user_id'] === $viewingUser['user_id'] && $this->_getContentModel()->canHardDeleteOwnContents($viewingUser))
		{
			return true;
		}
		 
		return $this->canDeleteAnyComment('soft', $viewingUser);
	}
	
	public function canViewModeratedComment(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		 
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'viewModeratedComment');
	}
	
	public function canEditAnyComment(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		 
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'editAnyComment');
	}
	
	public function canDeleteAnyComment($type, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		$hardDelete = XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'deleteAnyCommentHard');
		$softDelete = XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'deleteAnyCommentSoft');
		 
		if ($type == 'soft')
		{
			return ($hardDelete || $softDelete);
		}
		else
		{
			return $hardDelete;
		}
	}
	
	public function canViewCommentAndContainer(array $comment, &$errorPhraseKey = '', array $viewingUser = null, &$content = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		
		if ($this->isDeleted($comment) && !$this->canViewDeletedComment($comment, $viewingUser))
		{
			return false;
		}
		
		if ($this->isModerated($comment))
		{
			if ($viewingUser['user_id'] !== $comment['user_id'] && !$this->canViewModeratedComment($viewingUser))
			{
				return false;
			}
		}

		if (empty($comment['content']))
		{
			$handler = sonnb_XenGallery_ContentHandler_Abstract::create($comment['content_type']);
			$content = $handler->getContentById($comment['content_id'], $viewingUser);

			return $handler->canViewContent($content, $viewingUser);
		}

		return true;
	}
	
	public function isDeleted(array $comment)
	{
		return ($comment['comment_state'] === 'deleted');
	}
	
	public function isModerated(array $comment)
	{
		return ($comment['comment_state'] === 'moderated');
	}
	
	public function isVisible(array $comment)
	{
		return ($comment['comment_state'] === 'visible');
	}
	
	public function prepareCommentFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';
		
		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';
		
			switch ($fetchOptions['order'])
			{
				case 'comment_id':
				case 'content_type':
				case 'content_id':
				case 'user_id':
				case 'comment_state':
				case 'likes':
					$orderBy = 'comment.' . $fetchOptions['order'];
					$orderBySecondary = ', comment.comment_date DESC';
					break;
				case 'random':
					$orderBy = 'RAND()';
					$orderBySecondary = '';
					break;
				case 'recently_liked':
					$joinTables .= '
                                LEFT JOIN xf_liked_content AS liked_content_sort
                                        ON (liked_content.content_type = \''.self::$xfContentType.'\'
                                                AND liked_content.content_id = comment.comment_id)';

					$orderBy = 'liked_content_sort.like_date';
					$orderBySecondary = ', content.content_updated_date ASC';
					break;
				case 'comment_date':
				default:
					$orderBy = 'comment.comment_date';
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
                                                AND liked_content.content_id = comment.comment_id
                                                AND liked_content.like_user_id = ' . $this->_getDb()->quote($fetchOptions['likeUserId']) . ')';
			}
		}
		
		if (!empty($fetchOptions['join']))
		{		
			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= ',
					IF(user.username IS NULL, comment.username, user.username) AS username, user.avatar_date, user.gravatar';
				$joinTables .= '
					LEFT JOIN `xf_user` AS user ON
						(user.user_id = comment.user_id)';
			}
		}

		if (isset($fetchOptions['followingUserId']))
		{
			$fetchOptions['followingUserId'] = intval($fetchOptions['followingUserId']);
			if ($fetchOptions['followingUserId'])
			{
				$selectFields .= ',
					IF(user_follow.user_id IS NOT NULL, 1, 0) AS following_' . $fetchOptions['followingUserId'];
				$joinTables .= '
					LEFT JOIN xf_user_follow AS user_follow ON
						(user_follow.user_id = comment.user_id AND user_follow.follow_user_id = ' . $fetchOptions['followingUserId'] . ')';
			}
			else
			{
				$selectFields .= ',
					0 AS following_0';
			}
		}
		
		return array(
				'selectFields' => $selectFields,
				'joinTables' => $joinTables,
				'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}
	
	public function prepareCommentConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
		
		if (!empty($conditions['comment_id']))
		{
			if (is_array($conditions['comment_id']))
			{
				$sqlConditions[] = 'comment.comment_id IN (' . $db->quote($conditions['comment_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'comment.comment_id = ' . $db->quote($conditions['comment_id']);
			}
		}
		
		if (isset($conditions['user_id']))
		{
			if (is_array($conditions['user_id']))
			{
				$sqlConditions[] = 'comment.user_id IN (' . $db->quote($conditions['user_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'comment.user_id = ' . $db->quote($conditions['user_id']);
			}
		}
		
		if (!empty($conditions['content_type']))
		{
			if (is_array($conditions['content_type']))
			{
				$sqlConditions[] = 'comment.content_type IN (' . $db->quote($conditions['content_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'comment.content_type = ' . $db->quote($conditions['content_type']);
			}
		}
		
		if (!empty($conditions['content_id']))
		{
			if (is_array($conditions['content_id']))
			{
				$sqlConditions[] = 'comment.content_id IN (' . $db->quote($conditions['content_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'comment.content_id = ' . $db->quote($conditions['content_id']);
			}
		}
		
		if (!empty($conditions['comment_state']))
		{
			if (is_array($conditions['comment_state']))
			{
				$sqlConditions[] = 'comment.comment_state IN (' . $db->quote($conditions['comment_state']) . ')';
			}
			else
			{
				$sqlConditions[] = 'comment.comment_state = ' . $db->quote($conditions['comment_state']);
			}
		}

		if (isset($conditions['deleted']) || isset($conditions['moderated']))
		{
			$sqlConditions[] = $this->prepareStateLimitFromConditions($conditions, 'comment', 'comment_state');
		}
		
		if (!empty($conditions['likes']) && is_array($conditions['likes']))
		{
			list($operator, $cutOff) = $conditions['likes'];
		
			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "comment.likes $operator " . $db->quote($cutOff);
		}
		
		if (!empty($conditions['comment_date']) && is_array($conditions['comment_date']))
		{
			list($operator, $cutOff) = $conditions['comment_date'];
		
			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "comment.comment_date $operator " . $db->quote($cutOff);
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * @return sonnb_XenGallery_Model_Photo
	 */
	protected function _getPhotoModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Photo');
	}
	
	/**
	 * @return sonnb_XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Album');
	}
}
