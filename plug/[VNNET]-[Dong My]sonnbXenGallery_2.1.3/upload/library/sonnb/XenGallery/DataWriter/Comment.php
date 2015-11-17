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
class sonnb_XenGallery_DataWriter_Comment extends sonnb_XenGallery_DataWriter_Abstract
{
	const DATA_DELETE_REASON = 'dataDeleteReason';
	const DATA_CONTENT = 'dataContent';
	
	protected function _getFields()
	{
		return array(
			'sonnb_xengallery_comment' => array(
				'comment_id' => array(
					'type' => self::TYPE_UINT,
					'autoIncrement' => true
				),
				'content_type' => array(
					'type' => self::TYPE_STRING,
					'default' => 'album',
					'allowedValues' => array('album','photo','video','audio'),
				),
				'content_id' => array(
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'user_id' => array(
					'type' => self::TYPE_UINT,
					'default' => 0
				),
				'username' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 50,
					'default' => ''
				),
				'comment_state' => array(
					'type' => self::TYPE_STRING,
					'default' => 'visible',
					'allowedValues' => array('visible','moderated','deleted'),
				),
				'message' => array(
					'type' => self::TYPE_STRING,
					'required' => true,
					'maxLength' => 500,
				),
				'likes' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'like_users' => array(
					'type' => self::TYPE_SERIALIZED,
					'default' => 'a:0:{}'
				),
				'comment_date' => array(
					'type' => self::TYPE_UINT,
					'default' => XenForo_Application::$time,
				),
			),
		);
	}
	
	protected function _preSave()
	{
		if ($this->isChanged('message'))
		{
			if (utf8_strlen($this->get('message')) > 500)
			{
				$this->error(new XenForo_Phrase('please_enter_message_with_no_more_than_x_characters', array('count' => 500)), 'message');
			}
		}
		
		if ($this->isInsert())
		{
			if ($this->_getCommentModel()->getCommentDefaultState())
			{
				$this->set('comment_state', 'moderated');
			}
		}

		$this->_taggedUsers = $this->_getUserTaggingModel()->getTaggedUsersInMessage(
			$this->get('message'), $newMessage, 'text'
		);
		$this->set('message', $newMessage);
	}
	
	protected function _postSave()
	{
		if ($this->isUpdate() && $this->isChanged('comment_state'))
		{
			if ($this->get('comment_state') === 'visible')
			{
				$this->_publishToNewsFeed();
			}
		}
		
		if ($this->isInsert() && $this->get('comment_state') === 'visible')
		{
			$dwHandler = $this->_getXfContentDw();
			$dwHandler->setExistingData($this->get('content_id'));
			$dwHandler->insertNewComment($this->get('comment_id'), $this->get('comment_date'));
			$dwHandler->save();
			
			$this->_publishToNewsFeed();
			
			$this->_sendAlertToContentWatchers();
		}
		
		if ($this->isChanged('comment_state'))
		{
			$this->_updateDeletionLog();
			$this->_updateModerationQueue();
		}
		
		if ($this->isInsert())
		{
			$this->_logIpExtend('insert');

			$content = $this->_getContentExtraData();
			
			if ($this->get('user_id') != $content['user_id'])
			{
				$this->_getWatchModel()->insertUpdateWatcherByContentId(
					XenForo_Visitor::getInstance(), 
					$this->get('content_type'), 
					$this->get('content_id')
				);
			}
		}

		if ($this->isChanged('comment_state') && $this->get('comment_state') === 'deleted')
		{
			$this->_logIpExtend('soft-delete');
		}

		if ($this->get('comment_state') === 'visible'
			&& ($this->isInsert() || $this->getExisting('comment_state') === 'moderated'))
		{
			$maxTagged = 20;
			if ($this->_taggedUsers)
			{
				if ($maxTagged > 0)
				{
					$alertTagged = array_slice($this->_taggedUsers, 0, $maxTagged, true);
				}
				else
				{
					$alertTagged = $this->_taggedUsers;
				}

				$content = $this->getMergedData();
				$this->_getUserTaggingModel()->alertTaggedMembers(
					$content,
					$content['comment_id'],
					sonnb_XenGallery_Model_Comment::$xfContentType,
					$alertTagged
				);
			}
		}
	}

	protected function _postSaveAfterTransaction()
	{
		if ($this->isUpdate() && $this->isChanged('comment_state'))
		{
			$dwHandler = $this->_getXfContentDw();
			$dwHandler->setExistingData($this->get('content_id'));
			$dwHandler->rebuildCommentCounters();
			$dwHandler->save();
		}
	}
	
	protected function _postDelete()
	{
		if ($this->get('comment_state') === 'visible')
		{
			$dwHandler = $this->_getXfContentDw(null, XenForo_DataWriter::ERROR_SILENT);
			$dwHandler->setExistingData($this->get('content_id'));

			if ($this->_checkContentState($dwHandler) != 'deleted')
			{
				$dwHandler->rebuildCommentCounters();
				$dwHandler->save();
			}
		}

		$this->_logIpExtend('delete');

		$this->_deleteFromNewsFeed();
		
		$this->_deleteFromAlert();
		
		$this->_updateDeletionLog(true);
		$this->_updateModerationQueue(true);
	}
	
	protected function _getContentExtraData()
	{
		$content = $this->getExtraData(self::DATA_CONTENT);
		
		if (!$content)
		{
			switch ($this->get('content_type'))
			{
				case sonnb_XenGallery_Model_Album::$contentType:
					$content = $this->_getAlbumModel()->getAlbumById($this->get('content_id'));
					break;
				case sonnb_XenGallery_Model_Photo::$contentType:
				case sonnb_XenGallery_Model_Video::$contentType:
				default:
					$content = $this->_getContentModel()->getContentById($this->get('content_type'), $this->get('content_id'));
					break;
			}
			
			$this->setExtraData(self::DATA_CONTENT, $content);
		}
		
		return $content;
	}
	
	protected function _publishToNewsFeed()
	{
		$contentType = $this->_getXfContentType($this->get('content_type'));
		
		$this->_getNewsFeedModel()->publish(
			$this->get('user_id'),
			$this->get('username'),
			$contentType,
			$this->get('content_id'),
			'comment',
			array('comment' => $this->getMergedData())
		);
	}
	
	protected function _deleteFromNewsFeed()
	{
		$this->_getNewsFeedModel()->delete(
			sonnb_XenGallery_Model_Comment::$xfContentType,
			$this->get('comment_id')
		);
	}
	
	protected function _sendAlertToContentWatchers()
	{
		$visitor = XenForo_Visitor::getInstance();
		$contentType = $this->_getXfContentType($this->get('content_type'));
		$contentData = $this->getExtraData(self::DATA_CONTENT);
		
		/*
		 * Massive sending alert to watched users.
		*/
		$this->_getWatchModel()->sendAlertToWatchedUsersByContentId(
			$this->get('content_type'),
			$this->get('content_id'),
			$visitor,
			'comment',
			array(),
			array_keys($this->_taggedUsers)
		);
		
		/*
		 * Send alert to owner.
		*/
		if ($visitor['user_id'] != $contentData['user_id'])
		{
			$userModel = $this->_getUserModel();
			$contentUser = $userModel->getUserById($contentData['user_id'], array(
					'join' => XenForo_Model_User::FETCH_USER_OPTION | XenForo_Model_User::FETCH_USER_PROFILE
			));
				
			if ($contentUser)
			{
				if (!$userModel->isUserIgnored($contentUser, $visitor['user_id'])
						&& XenForo_Model_Alert::userReceivesAlert($contentUser, $contentType, 'comment')
				)
				{
					XenForo_Model_Alert::alert(
						$contentData['user_id'],
						$visitor['user_id'],
						$visitor['username'],
						$contentType,
						$this->get('content_id'),
						'comment'
					);

					//TODO: Send email to owner if he/she accepts emails
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
				}
			}
		}
	}

	protected function _checkContentState(XenForo_DataWriter $dw)
	{
		switch ($this->get('content_type'))
		{
			case sonnb_XenGallery_Model_Album::$contentType:
				return $dw->get('album_state');
				break;
			case sonnb_XenGallery_Model_Photo::$contentType:
			case sonnb_XenGallery_Model_Video::$contentType:
			default:
				return $dw->get('content_state');
				break;
		}
	}
	
	protected function _deleteFromAlert()
	{
		$db = $this->_db;
		
		$commentId = $db->quote($this->get('comment_id'));
		
		$db->delete('xf_user_alert', "content_id = $commentId AND content_type = '". sonnb_XenGallery_Model_Comment::$xfContentType ."'");
	}

	protected function _updateDeletionLog($hardDelete = false)
	{
		if ($hardDelete
			|| ($this->isChanged('comment_state') && $this->getExisting('comment_state') === 'deleted')
		)
		{
			$this->getModelFromCache('XenForo_Model_DeletionLog')->removeDeletionLog(
				sonnb_XenGallery_Model_Comment::$xfContentType, $this->get('comment_id')
			);
		}

		if ($this->isChanged('comment_state') && $this->get('album_state') === 'deleted')
		{
			$reason = $this->getExtraData(self::DATA_DELETE_REASON);
			$this->getModelFromCache('XenForo_Model_DeletionLog')->logDeletion(
				sonnb_XenGallery_Model_Comment::$xfContentType, $this->get('comment_id'), $reason
			);
		}
	}

	protected function _updateModerationQueue($hardDelete = false)
	{
		if (!$this->isChanged('comment_state'))
		{
			return;
		}

		if ($hardDelete || $this->getExisting('comment_state') === 'moderated')
		{
			$this->getModelFromCache('XenForo_Model_ModerationQueue')->deleteFromModerationQueue(
				sonnb_XenGallery_Model_Comment::$xfContentType, $this->get('comment_id')
			);
		}
		elseif ($this->get('comment_state') === 'moderated')
		{
			$this->getModelFromCache('XenForo_Model_ModerationQueue')->insertIntoModerationQueue(
				sonnb_XenGallery_Model_Comment::$xfContentType, $this->get('comment_id'), $this->get('comment_date')
			);
		}
	}

	protected function _logIpExtend($action)
	{
		$this->_logIp(sonnb_XenGallery_Model_Comment::$xfContentType, $this->get('comment_id'), $action);
	}
	
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'comment_id'))
		{
			return false;
		}
	
		return array('sonnb_xengallery_comment' => $this->_getCommentModel()->getCommentById($id));
	}
	
	protected function _getUpdateCondition($tableName)
	{
		return 'comment_id = ' . $this->_db->quote($this->getExisting('comment_id'));
	}
}