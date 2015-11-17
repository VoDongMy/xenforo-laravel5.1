<?php

class XenGallery_Model_Comment extends XenForo_Model
{
	const FETCH_USER = 0x01;
	const FETCH_MEDIA = 0x02;
	const FETCH_ALBUM_CONTENT = 0x04;
	const FETCH_ATTACHMENT = 0x08;
	const FETCH_DELETION_LOG = 0x10;
	const FETCH_CATEGORY = 0x20;
	const FETCH_ALBUM = 0x40;
	const FETCH_PRIVACY = 0x80;
	const FETCH_RATING = 0x100;

	/**
	 * Gets a single comment record specified by its ID and content type
	 *
	 * @param integer $mediaId
	 *
	 * @return array
	 */
	public function getCommentById($commentId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareCommentFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT comment.*
				' . $joinOptions['selectFields'] . '
			FROM xengallery_comment AS comment
				' . $joinOptions['joinTables'] . '
			WHERE comment.comment_id = ?
		', $commentId);
	}

	/**
	 * Gets comment records specified by their IDs
	 *
	 * @param array $commentIds
	 *
	 * @return array
	 */
	public function getCommentsByIds($commentIds, array $fetchOptions = array())
	{
		$db = $this->_getDb();
		$joinOptions = $this->prepareCommentFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT comment.*
				' . $joinOptions['selectFields'] . '
			FROM xengallery_comment AS comment
				' . $joinOptions['joinTables'] . '
			WHERE comment.comment_id IN (' . $db->quote($commentIds) . ')
		', 'comment_id');
	}

	public function getCommentByRatingId($ratingId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xengallery_comment
			WHERE rating_id = ?
		', $ratingId);
	}

	/**
	 * Gets comments based on options and criteria
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function getComments(array $conditions = array(), array $fetchOptions = array())
	{
		$whereClause = $this->prepareCommentConditions($conditions, $fetchOptions);

		$joinOptions = $this->prepareCommentFetchOptions($fetchOptions, $conditions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$sqlClauses = $this->prepareCommentFetchOptions($fetchOptions);

		$comments = $this->fetchAllKeyed($this->limitQueryResults('
			SELECT comment.*
				' . $joinOptions['selectFields'] . '
			FROM xengallery_comment AS comment
				' . $joinOptions['joinTables'] . '
				WHERE ' . $whereClause . '
				' . $sqlClauses['orderClause'] . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'comment_id');

		return $comments;
	}

	public function deleteCommentsByMediaId($mediaId)
	{
		$comments = $this->getComments(array('media_id' => $mediaId));

		foreach ($comments AS $comment)
		{
			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Comment', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($comment);
			$dw->delete();
		}

		return true;
	}

	public function getCommentsForBlockOrFeed($limit, array $fetchOptions)
	{
		$db = $this->_getDb();

		$viewingUser = $this->standardizeViewingUserReference();

		$privacyClause = '1=1';
		$noAlbums = '';
		if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewOverride'))
		{
			$userId = 0;
			if (isset($fetchOptions['privacyUserId']))
			{
				$userId = $fetchOptions['privacyUserId'];
			}

			if (empty($fetchOptions['viewAlbums']))
			{
				$noAlbums = 'AND media.album_id = 0 AND album.album_id = 0';
			}

			$categoryClause = '';
			if (!empty($fetchOptions['viewCategoryIds']))
			{
				$categoryClause = 'OR IF(media.category_id > 0, media.category_id IN (' . $db->quote($fetchOptions['viewCategoryIds']) . '), NULL)';
			}

			$membersClause = '';
			if ($userId > 0)
			{
				$membersClause = 'OR media.media_privacy = \'members\' OR albumviewperm.access_type = \'members\'';
			}

			$privacyClause = '
				private.private_user_id IS NOT NULL
					OR shared.shared_user_id IS NOT NULL
					OR media.media_privacy = \'public\'
					OR albumviewperm.access_type = \'public\'
				' . $membersClause . '
				' . $categoryClause;
		}

		$whereClause = '';
		if (!empty($fetchOptions['comment_id']))
		{
			if (is_array($fetchOptions['comment_id']))
			{
				$whereClause = ' AND comment.comment_id IN(' . $db->quote($fetchOptions['comment_id']) . ')';
			}
			else
			{
				$whereClause = ' AND comment.comment_id = ' . $db->quote($fetchOptions['comment_id']);
			}
		}

		$comments = $this->fetchAllKeyed($this->limitQueryResults('
			SELECT comment.*, media.media_title, media.media_type, media.media_id, media.media_state, media.attachment_id, media.media_tag, media.category_id,
				album.album_title, album.album_description, albumviewperm.access_type, albumviewperm.share_users, album.album_id, album.album_state, album.album_user_id, album.album_thumbnail_date, user.*, container.album_state AS albumstate,
				attachment.data_id, ' . XenForo_Model_Attachment::$dataColumns . '
			FROM xengallery_comment AS comment
			LEFT JOIN xengallery_media AS media ON
				(comment.content_id = media.media_id AND comment.content_type = \'media\')
			LEFT JOIN xf_attachment AS attachment ON
				(attachment.attachment_id = media.attachment_id)
			LEFT JOIN xf_attachment_data AS data ON
				(data.data_id = attachment.data_id)
			LEFT JOIN xengallery_album AS album ON
				(comment.content_id = album.album_id AND comment.content_type = \'album\')
			LEFT JOIN xengallery_album_permission AS albumviewperm ON
				(album.album_id = albumviewperm.album_id AND albumviewperm.permission = \'view\')
			LEFT JOIN xengallery_album AS container ON
				(container.album_id = media.album_id)
			LEFT JOIN xf_user AS user ON
				(comment.user_id = user.user_id)
			LEFT JOIN xengallery_shared_map AS shared ON
				(shared.album_id = COALESCE(album.album_id, media.album_id) AND shared.shared_user_id = ' . $db->quote($fetchOptions['privacyUserId']) . ')
			LEFT JOIN xengallery_private_map AS private ON
				(private.album_id = COALESCE(album.album_id, media.album_id) AND private.private_user_id = ' .  $db->quote($fetchOptions['privacyUserId']) .')
			WHERE (container.album_state IS NULL OR container.album_state = \'visible\' OR album.album_state = \'visible\')
				AND user.is_banned = 0
				AND (media.media_state IS NULL OR media.media_state = \'visible\')
				AND (album.album_state IS NULL OR album.album_state = \'visible\')
				AND ('. $privacyClause .')
				AND comment.comment_state = \'visible\' '
					. $noAlbums . $whereClause . '
			ORDER BY comment.comment_date DESC
			', $limit
		), 'comment_id');

		return $comments;
	}

	public function countComments(array $conditions = array())
	{
		$fetchOptions = array();
		$whereClause = $this->prepareCommentConditions($conditions, $fetchOptions);

		$joinOptions = $this->prepareCommentFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xengallery_comment AS comment
			' . $joinOptions['joinTables'] . '
			WHERE ' . $whereClause
		);
	}

	/**
	 * Fetches all comments newer than the date specified.
	 *
	 * @param integer Unix timestamp
	 *
	 * @return array
	 */
	public function getCommentsNewerThan($date, $contentId, $contentType)
	{
		return $this->fetchAllKeyed('
			SELECT
				comment.*,
				user.*
			FROM xengallery_comment AS comment
			LEFT JOIN xf_user AS user ON
				(user.user_id = comment.user_id)
			WHERE comment.comment_date > ?
				AND comment.content_id = ?
				AND comment.content_type = ?
			ORDER BY comment_date
		', 'comment_id', array($date, $contentId, $contentType));
	}

	/**
	 * Gets the most recent date
	 */
	public function getLatestDate(array $content, $visibleOnly = false)
	{
		$visibleOnlyClause = '';
		if ($visibleOnly)
		{
			$visibleOnlyClause = 'AND comment_state = \'visible\'';
		}

		return $this->_db->fetchOne('
			SELECT
				comment_date
			FROM xengallery_comment
			WHERE content_id = ?
				AND content_type = ?
				' . $visibleOnlyClause . '
			ORDER BY comment_date DESC
			LIMIT 1
		', array($content['content_id'], $content['content_type']));
	}

	public function rebuildCommentPositions($contentId, $contentType)
	{
		$db = $this->_getDb();

		$comments = $this->_getDb()->query('
			SELECT comment_id, comment_date, comment_state, position
			FROM xengallery_comment
			WHERE content_id = ?
				AND content_type = ?
			ORDER BY comment_date, comment_id
		', array($contentId, $contentType));

		$position = 0;
		$updatePositions = array();

		while ($comment = $comments->fetch())
		{
			if ($comment['position'] != $position)
			{
				$updatePositions[$comment['comment_id']] = $position;
			}

			if ($comment['comment_state'] == 'visible')
			{
				$position++;
			}
		}

		if ($updatePositions)
		{
			XenForo_Db::beginTransaction($db);

			foreach ($updatePositions AS $commentId => $updatePosition)
			{
				$db->update('xengallery_comment', array('position' => $updatePosition), 'comment_id = ' . $db->quote($commentId));
			}

			XenForo_Db::commit($db);
		}
	}

	/**
	 * Checks that the viewing user may managed a reported media comment item
	 *
	 * @param array $comment
	 * @param string $errorPhraseKey
	 * @param array $viewingUser
	 *
	 * @return boolean
	 */
	public function canManageReportedComment(array $comment, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		return (
			XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteCommentAny')
			|| XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editCommentAny')
		);
	}

	/**
	 * Checks that the viewing user may manage a moderated comment item
	 *
	 * @param array $comment
	 * @param string $errorPhraseKey
	 * @param array $viewingUser
	 *
	 * @return boolean
	 */
	public function canManageModeratedComment(array $comment, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		return (
			XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteCommentAny')
			|| XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editCommentAny')
			|| XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'approveUnapproveComment')
		);
	}

	public function canLikeComment(array $comment, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($comment['user_id'] == $viewingUser['user_id'])
		{
			$errorPhraseKey = 'xengallery_you_cannot_like_your_own_comment';
			return false;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'likeComment'))
		{
			return true;
		}

		$errorPhraseKey = 'xengallery_no_like_permission';
		return false;
	}

	public function canEditComment(array $comment, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editCommentAny'))
		{
			return true;
		}

		if ($comment['user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editComment'))
		{
			$editLimit = XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editOwnCommentTimeLimit');

			if ($editLimit !== 0)
			{
				if ($editLimit != -1 && (!$editLimit || $comment['comment_date'] < XenForo_Application::$time - 60 * $editLimit))
				{
					$errorPhraseKey = array('xengallery_comment_edit_limit', 'minutes' => $editLimit);
					return false;
				}
			}

			return true;
		}

		$errorPhraseKey = 'xengallery_no_edit_comment_permission';
		return false;
	}

	public function canDeleteComment(array $comment, $type = 'soft', &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($type != 'soft' && !XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'hardDeleteCommentAny'))
		{
			return false;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteCommentAny'))
		{
			return true;
		}
		else if ($comment['user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteComment'))
		{
			$editLimit = XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editOwnCommentTimeLimit');

			if ($editLimit !== 0)
			{
				if ($editLimit != -1 && (!$editLimit || $comment['comment_date'] < XenForo_Application::$time - 60 * $editLimit))
				{
					$errorPhraseKey = array('xengallery_comment_delete_limit', 'minutes' => $editLimit);
					return false;
				}
			}

			return true;
		}

		$errorPhraseKey = 'xengallery_no_delete_comment_permission';
		return false;
	}

	public function canViewComments(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewComments'))
		{
			return true;
		}

		return false;
	}

	public function canAddComment(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'addComment'))
		{
			return true;
		}

		return false;
	}

	/**
	 * Gets the comment state for a newly inserted comment by the viewing user.
	 *
	 * @param array|null $viewingUser
	 *
	 * @return string Comment state (visible, moderated, deleted)
	 */
	public function getCommentInsertState(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'approveUnapproveComment')
			|| XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'bypassModQueueComment')
		)
		{
			return 'visible';
		}
		else
		{
			return 'moderated';
		}
	}

	/**
	 * Checks if a user can generally approve or unapprove media.
	 * @param string $errorPhraseKey
	 * @param array $viewingUser
	 * @return bool|false|true
	 */
	public function canApproveUnapproveComment(&$errorPhraseKey ='', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'approveUnapproveMedia');
	}

	public function canApproveComment(array $comment, &$errorPhraseKey ='', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'] || $comment['comment_state'] != 'moderated')
		{
			return false;
		}

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'approveUnapproveComment');
	}

	public function canUnapproveComment(array $comment, &$errorPhraseKey ='', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'] || $comment['comment_state'] != 'visible')
		{
			return false;
		}

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'approveUnapproveComment');
	}

	public function canViewUnapprovedComment(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'approveUnapproveComment');
	}

	public function canViewDeletedComment(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewDeletedComments'))
		{
			return true;
		}

		return false;
	}

	public function canWarnComment(array $comment, &$errorPhraseKey = '', array $viewingUser = null)
	{
		if (!empty($comment['warning_id']) || empty($comment['user_id']))
		{
			return false;
		}

		if (!empty($comment['is_admin']) || !empty($comment['is_moderator']))
		{
			return false;
		}

		$this->standardizeViewingUserReference($viewingUser);

		return ($viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'warnComment'));
	}

	public function prepareCommentsForBlock(array $comments)
	{
		foreach ($comments AS $commentId => &$comment)
		{
			if ($comment['content_type'] == 'media')
			{
				$comment['content_title'] = XenForo_Helper_String::censorString($comment['media_title']);
				$comment['content_link'] = XenForo_Link::buildPublicLink('xengallery', $comment);
				if ($comment['media_type'] == 'image_upload')
				{
					$comment['content_icon'] = 'picture-o';
				}
				else
				{
					$comment['content_icon'] = 'film';
				}
			}
			else
			{
				$comment['content_title'] = XenForo_Helper_String::censorString($comment['album_title']);
				$comment['content_link'] = XenForo_Link::buildPublicLink('xengallery/albums', $comment);

				$comment['content_icon'] = 'folder-open';
			}
		}

		return $comments;
	}

	public function prepareComments($comments)
	{
		if (!empty($comments['comment_id']))
		{
			$comments = $this->prepareComment($comments);

			return $comments;
		}

		foreach ($comments AS &$comment)
		{
			$comment = $this->prepareComment($comment);
		}

		return $comments;
	}

	public function prepareComment($comment)
	{
		$visitor = XenForo_Visitor::getInstance();

		$comment['canWarn'] = $this->canWarnComment($comment);
		$comment['canEdit'] = $this->canEditComment($comment);
		$comment['canDelete'] = $this->canDeleteComment($comment);
		$comment['canLike'] = $this->canLikeComment($comment);
		$comment['canCleanSpam'] = (XenForo_Permission::hasPermission($visitor->permissions, 'general', 'cleanSpam') && $this->getModelFromCache('XenForo_Model_User')->couldBeSpammer($comment));

		$comment['likeUsers'] = isset($comment['like_users']) ? unserialize($comment['like_users']) : false;

		$userIds = array();
		if (!empty($comment['likeUsers']))
		{
			foreach ($comment['likeUsers'] AS $user)
			{
				$userIds[$user['user_id']] = $user['user_id'];
			}
		}

		$comment['existingLike'] = array_key_exists($visitor->user_id, $userIds);

		$comment['isIgnored'] = array_key_exists($comment['user_id'], $visitor->ignoredUsers);

		$comment['liked'] = ($comment['existingLike'] ? true : false);
		$comment['like_date'] = ($comment['liked'] ? XenForo_Application::$time : 0);

		return $comment;
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
				WHERE content_type = \'xengallery_comment\'
				AND like_user_id = ?
			) AS temp
			INNER JOIN xengallery_comment AS comment ON (comment.comment_id = temp.content_id)
			SET like_users = REPLACE(like_users, ' .
				$db->quote('i:' . $oldUserId . ';s:8:"username";s:' . strlen($oldUsername) . ':"' . $oldUsername . '";') . ', ' .
				$db->quote('i:' . $newUserId . ';s:8:"username";s:' . strlen($newUsername) . ':"' . $newUsername . '";') . ')
		', $newUserId);
	}

	public function prepareInlineModOptions(array &$comments)
	{
		$commentModOptions = array();

		foreach ($comments AS &$comment)
		{
			$commentModOptions = $this->addInlineModOptionToComment($comment, $comment);
		}

		return $commentModOptions;
	}

	/**
	 * Adds the canInlineMod value to the provided comment and returns the
	 * specific list of inline mod actions that are allowed on this comment.
	 *
	 * @param array $comment Comment info
	 * @param array $container Container (category or Album) the comment is in
	 * @param array|null $viewingUser
	 *
	 * @return array List of allowed inline mod actions, format: [action] => true
	 */
	public function addInlineModOptionToComment(array &$comment, array $container, $userPage = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$commentModOptions = array();

		$canInlineMod = ($viewingUser['user_id'] && (
			$this->canApproveUnapproveComment($errorPhraseKey, $viewingUser)
		));

		if ($canInlineMod)
		{
			if ($this->canApproveUnapproveComment($errorPhraseKey, $viewingUser))
			{
				$commentModOptions['unapprove'] = true;
				$commentModOptions['approve'] = true;
			}
			if ($this->canDeleteComment($comment, 'soft', $errorPhraseKey, $viewingUser))
			{
				$commentModOptions['delete'] = true;
			}
			if ($this->canDeleteComment($comment, 'soft', $errorPhraseKey, $viewingUser)
				&& ($viewingUser['is_staff']
					|| $viewingUser['is_moderator']
					|| $viewingUser['is_admin']
				)
			)
			{
				$commentModOptions['undelete'] = true;
			}
			if ($this->canEditComment($comment, $errorPhraseKey, $viewingUser))
			{
				$commentModOptions['edit'] = true;
			}
		}

		$comment['canInlineMod'] = (count($commentModOptions) > 0);

		return $commentModOptions;
	}

	/**
	 * Prepares a set of conditions against which to select media comments.
	 *
	 * @param array $conditions List of conditions.
	 * @param array $fetchOptions The fetch options that have been provided. May be edited if criteria requires.
	 *
	 * @return string Criteria as SQL for where clause
	 */
	public function prepareCommentConditions(array $conditions, array &$fetchOptions)
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
						$membersClause = 'OR media.media_privacy = \'members\'';
					}

					$sqlConditions[] = '
						private.private_user_id IS NOT NULL
							OR shared.shared_user_id IS NOT NULL
							OR media.media_privacy = \'public\'
							' . $membersClause . '
					';
				}
			}
		}

		if (!empty($conditions['user_id']))
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

		if (!empty($conditions['media_id']))
		{
			if (is_array($conditions['media_id']))
			{
				$sqlConditions[] = 'comment.content_id IN (' . $db->quote($conditions['media_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'comment.content_id = ' . $db->quote($conditions['media_id']);
			}

			$sqlConditions[] = 'comment.content_type = ' . $db->quote('media');
		}

		if (!empty($conditions['album_id']))
		{
			if (is_array($conditions['album_id']))
			{
				$sqlConditions[] = 'comment.content_id IN (' . $db->quote($conditions['album_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'comment.content_id = ' . $db->quote($conditions['album_id']);
			}

			$sqlConditions[] = 'comment.content_type = ' . $db->quote('album');
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

		if (!empty($conditions['comment_id_lt']))
		{
			$sqlConditions[] = 'comment.comment_id < ' . $db->quote($conditions['comment_id_lt']);
		}

		if (isset($conditions['deleted']) || isset($conditions['moderated']))
		{
			$sqlConditions[] = $this->prepareStateLimitFromConditions($conditions, 'comment', 'comment_state');
		}
		else
		{
			// sanity check: only get visible comments unless we've explicitly said to get something else
			$sqlConditions[] = "comment.comment_state = 'visible'";
		}

		if (isset($conditions['visible_media']))
		{
			$sqlConditions[] = "media.media_state = 'visible' OR album.album_state = 'visible'";
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Prepares join-related fetch options.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array Containing 'selectFields' and 'joinTables' keys.
	 */
	public function prepareCommentFetchOptions(array $fetchOptions, array $conditions = array())
	{

		$selectFields = '';
		$joinTables = '';
		$orderBy = '';

		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';

			switch ($fetchOptions['order'])
			{
				case 'comment_date':
				default:
					$orderBy = 'comment.comment_date';
			}
			if (!isset($fetchOptions['orderDirection']) || $fetchOptions['orderDirection'] == 'ASC')
			{
				$orderBy .= ' ASC';
			}
			else
			{
				$orderBy .= ' DESC';
			}
		}

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_MEDIA)
			{
				$selectFields .= ',
					media.media_id, media.media_title, media.media_description, media.media_type, media.media_state,
					media.comment_count, media.user_id AS media_user_id, media.username AS media_username, media.media_date, media.album_id, media.media_tag';
				$joinTables .= '
					LEFT JOIN xengallery_media AS media ON
						(media.media_id = comment.content_id AND comment.content_type = \'media\')';
			}

			if ($fetchOptions['join'] & self::FETCH_ALBUM_CONTENT)
			{
				$selectFields .= ',
					albumcontent.album_title';

				$joinTables .= '
					LEFT JOIN xengallery_album AS albumcontent ON
						(albumcontent.album_id = comment.content_id AND comment.content_type = \'album\')';
			}

			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= ',
					user.*, user_profile.*, IF(user.username IS NULL, comment.username, user.username) AS username';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = comment.user_id)
					LEFT JOIN xf_user_profile AS user_profile ON
						(user_profile.user_id = comment.user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_ATTACHMENT)
			{
				$selectFields .= ',
					attachment.attachment_id, attachment.data_id,
					attachment.view_count, attachment.attach_date,
					attachment_data.filename, attachment_data.file_size,
					attachment_data.thumbnail_width, attachment_data.file_hash';
				$joinTables .= '
					LEFT JOIN xf_attachment AS attachment ON
						(attachment.content_type = \'xengallery_media\' AND attachment.attachment_id = media.attachment_id)
					LEFT JOIN xf_attachment_data AS attachment_data ON
						(attachment_data.data_id = attachment.data_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_DELETION_LOG)
			{
				$selectFields .= ',
					deletion_log.delete_date, deletion_log.delete_reason,
					deletion_log.delete_user_id, deletion_log.delete_username';
				$joinTables .= '
					LEFT JOIN xf_deletion_log AS deletion_log ON
						(deletion_log.content_type = \'xengallery_comment\' AND deletion_log.content_id = comment.comment_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_CATEGORY)
			{
				$selectFields .= ',
					category.*';
				$joinTables .= '
					LEFT JOIN xengallery_category AS category ON
						(category.category_id = media.category_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_ALBUM)
			{
				$selectFields .= ',
					album.album_id, album.album_title, album.album_state,
					album.album_user_id, album.album_username, albumviewperm.*';
				$joinTables .= '
					LEFT JOIN xengallery_album AS album ON
						(album.album_id = media.album_id)
					LEFT JOIN xengallery_album_permission AS albumviewperm ON
						(album.album_id = albumviewperm.album_id AND albumviewperm.permission = \'view\')
					';
			}

			if (($fetchOptions['join'] & self::FETCH_PRIVACY)
				&& isset($conditions['privacyUserId'])
			)
			{
				$selectFields .= ',
					shared.shared_user_id, private.private_user_id';
				$joinTables .= '
					LEFT JOIN xengallery_shared_map AS shared ON
						(shared.album_id = media.album_id AND shared.shared_user_id = ' . $this->_getDb()->quote($conditions['privacyUserId']) . ')
					LEFT JOIN xengallery_private_map AS private ON
						(private.album_id = media.album_id AND private.private_user_id = ' .  $this->_getDb()->quote($conditions['privacyUserId']) .')';
			}

			if ($fetchOptions['join'] & self::FETCH_RATING)
			{
				$selectFields .= ',
					rating.content_id, rating.rating_id, rating.rating, rating.rating_date';
				$joinTables .= '
					LEFT JOIN xengallery_rating AS rating ON
						(rating.rating_id = comment.rating_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables,
			'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}

	public function alertTaggedMembers(array $comment, array $tagged)
	{
		/** @var $albumModel XenGallery_Model_Album */
		$albumModel = $this->getModelFromCache('XenGallery_Model_Album');

		$album = array();
		if ($comment['album_id'])
		{
			$album = $albumModel->getAlbumById($comment['album_id']);
			$album = $albumModel->prepareAlbumWithPermissions($album);
		}

		$userIds = XenForo_Application::arrayColumn($tagged, 'user_id');
		$alertedUserIds = array();

		if ($userIds)
		{
			$userModel = $this->getModelFromCache('XenForo_Model_User');
			$users = $userModel->getUsersByIds($userIds, array(
				'join' => XenForo_Model_User::FETCH_USER_FULL | XenForo_Model_User::FETCH_USER_PERMISSIONS
			));
			foreach ($users AS $user)
			{
				if (!isset($alertedUserIds[$user['user_id']]) && $user['user_id'] != $comment['user_id'])
				{
					$canViewMedia = true;
					if ($album)
					{
						$user['permissions'] = @unserialize($user['global_permission_cache']);
						if (!$albumModel->canViewAlbum($album, $null, $user))
						{
							$canViewMedia = false;
						}
					}

					if (!$userModel->isUserIgnored($user, $comment['user_id'])
						&& XenForo_Model_Alert::userReceivesAlert($user, 'xengallery_comment', 'tag')
						&& $canViewMedia
					)
					{
						$alertedUserIds[$user['user_id']] = true;

						XenForo_Model_Alert::alert($user['user_id'],
							$comment['user_id'], $comment['username'],
							'xengallery_comment', $comment['comment_id'],
							'tag'
						);
					}
				}
			}
		}

		return array_keys($alertedUserIds);
	}

	protected function _getLikeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Like');
	}
}