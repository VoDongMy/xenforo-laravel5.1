<?php

/**
* Data writer for media comments
*/
class XenGallery_DataWriter_Comment extends XenForo_DataWriter
{
	/**
	 * Holds the reason for soft deletion.
	 *
	 * @var string
	 */
	const DATA_DELETE_REASON = 'deleteReason';

	/**
	 * Option that controls the maximum number of characters that are allowed in
	 * a message.
	 *
	 * @var string
	 */
	const OPTION_MAX_MESSAGE_LENGTH = 'maxMessageLength';

	/**
	 * Maximum number of images allowed in a message.
	 *
	 * @var string
	 */
	const OPTION_MAX_IMAGES = 'maxImages';

	/**
	 * Maximum pieces of media allowed in a message.
	 *
	 * @var string
	 */
	const OPTION_MAX_MEDIA = 'maxMedia';
		
	const OPTION_MAX_TAGGED_USERS = 'maxTaggedUsers';
	
	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'xengallery_requested_comment_not_found';
	
	protected $_taggedUsers = array();

	protected $_alertedUsers = array();

	protected $_contentDw = null;

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xengallery_comment' => array(
				'comment_id' => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'content_id' => array('type' => self::TYPE_UINT, 'required' => true),
				'content_type' => array('type' => self::TYPE_STRING, 'default' => 'media',
					'allowedValues' => array('media', 'album')
				),
				'message' => array('type' => self::TYPE_STRING, 'required' => true,
					'requiredError' => 'xengallery_please_enter_valid_comment'
				),
				'user_id' => array('type' => self::TYPE_UINT, 'required' => true),
				'username' => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
					'requiredError' => 'xengallery_please_enter_valid_username'
				),
				'ip_id' => array('type' => self::TYPE_UINT, 'default' => 0),
				'comment_date'  => array('type' => self::TYPE_UINT,   'required' => true, 'default' => XenForo_Application::$time),
				'comment_state' => array('type' => self::TYPE_STRING, 'default' => 'visible',
					'allowedValues' => array('visible', 'moderated', 'deleted')
				),
				'rating_id' => array('type' => self::TYPE_UINT, 'default' => 0),
                'likes'
                    => array('type' => self::TYPE_UINT),
                'like_users'
                    => array('type' => self::TYPE_SERIALIZED),
				'warning_id' => array('type' => self::TYPE_UINT, 'default' => 0),
				'warning_message' => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 255)
			)
		);
	}

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xengallery_comment' => $this->_getCommentModel()->getCommentById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'comment_id = ' . $this->_db->quote($this->getExisting('comment_id'));
	}
	
	/**
	 * Gets the default options.
	 */
	protected function _getDefaultOptions()
	{
		$options = XenForo_Application::getOptions();

		return array(
			self::OPTION_MAX_TAGGED_USERS => 0,
			self::OPTION_MAX_MESSAGE_LENGTH => $options->messageMaxLength,
			self::OPTION_MAX_IMAGES => $options->messageMaxImages,
			self::OPTION_MAX_MEDIA => $options->messageMaxMedia
		);
	}

	/**
	 * Check that the contents of the message are valid, based on length, images, etc.
	 */
	protected function _checkMessageValidity()
	{
		$message = $this->get('message');

		$maxLength = $this->getOption(self::OPTION_MAX_MESSAGE_LENGTH);
		if ($maxLength && utf8_strlen($message) > $maxLength)
		{
			$this->error(new XenForo_Phrase('please_enter_message_with_no_more_than_x_characters', array('count' => $maxLength)), 'message');
		}
		else
		{
			$maxImages = $this->getOption(self::OPTION_MAX_IMAGES);
			$maxMedia = $this->getOption(self::OPTION_MAX_MEDIA);
			if ($maxImages || $maxMedia)
			{
				/** @var $formatter XenForo_BbCode_Formatter_BbCode_Filter */
				$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_BbCode_Filter');
				$parser = XenForo_BbCode_Parser::create($formatter);
				$parser->render($message);

				if ($maxImages && $formatter->getTagTally('img') + $formatter->getTagTally('gallery') > $maxImages)
				{
					$this->error(new XenForo_Phrase('please_enter_message_with_no_more_than_x_images', array('count' => $maxImages)), 'message');
				}
				if ($maxMedia && $formatter->getTagTally('media') > $maxMedia)
				{
					$this->error(new XenForo_Phrase('please_enter_message_with_no_more_than_x_media', array('count' => $maxMedia)), 'message');
				}
			}
		}
	}

	protected function _preSave()
	{
		if ($this->isChanged('message'))
		{
			$this->_checkMessageValidity();
		}

		/** @var $taggingModel XenForo_Model_UserTagging */
		$taggingModel = $this->getModelFromCache('XenForo_Model_UserTagging');
		
		$this->_taggedUsers = $taggingModel->getTaggedUsersInMessage(
			$this->get('message'), $newMessage, 'bb'
		);

		$this->set('message', $newMessage);

		/*if (!$this->isChanged('position'))
		{
			if ($this->isInsert() || $this->isChanged('comment_state'))
			{
				$contentDw = $this->_getContentDw();
				if ($this->isInsert())
				{
					if ($contentDw && $contentDw->isUpdate())
					{
						$content = $contentDw->getMergedData();
						$countKey = ($content && isset($content['comment_count'])
							? 'comment_count'
							: 'album_comment_count'
						);

						if ($this->get('comment_state') == 'visible')
						{
							$this->set('position', $content[$countKey]);
						}
						else
						{
							$this->set('position', $content[$countKey] - 1);
						}
					}
				}
				else
				{
					// updated the state on an existing message -- need to slot in
					if ($this->get('comment_state') == 'visible' && $this->getExisting('comment_state') != 'visible')
					{
						$this->set('position', $this->get('position') + 1);
					}
					else if ($this->get('comment_state') != 'visible' && $this->getExisting('comment_state') == 'visible')
					{
						$this->set('position', $this->get('position') - 1);
					}
				}
			}
		}*/
	}

	protected function _postSave()
	{
		if ($this->isInsert())
		{
			$contentId = $this->get('content_id');
			$contentType = $this->get('content_type');

			$userId = XenForo_Visitor::getUserId();

			$draftKey = $contentType . '-' . $contentId;
			$draftModel = $this->_getDraftModel();
			$draft = $draftModel->getDraftByUserKey($draftKey, $userId);
			if ($draft)
			{
				$draftModel->deleteDraft($draftKey, array());
			}

			if ($contentType == 'media')
			{
				$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
				$mediaWriter->setExistingData($contentId);

				$commentCount = $mediaWriter->getExisting('comment_count');

				$mediaWriter->set('comment_count', $commentCount + 1);
				$mediaWriter->set('last_comment_date', XenForo_Application::$time);

				$mediaWriter->save();
				
				$content = $this->_getMediaModel()->getMediaById($contentId, array(
					'join' => XenGallery_Model_Media::FETCH_USER
						| XenGallery_Model_Media::FETCH_USER_OPTION
						| XenGallery_Model_Media::FETCH_ALBUM
				));

				$this->_getMediaModel()->markMediaViewed($content);
			}
			else
			{
				$albumWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
				$albumWriter->setExistingData($contentId);

				$commentCount = $albumWriter->getExisting('album_comment_count');

				$newValue = $commentCount + 1;
				if (intval($newValue) < 1)
				{
					$newValue = 0;
				}

				$albumWriter->set('album_comment_count', $newValue);
				$albumWriter->set('album_last_comment_date', XenForo_Application::$time);

				$albumWriter->save();
				
				$content = $this->_getAlbumModel()->getAlbumById($contentId, array(
					'join' => XenGallery_Model_Album::FETCH_USER
						| XenGallery_Model_Album::FETCH_USER_OPTION
				));
			}
			
			$commentUser = array(
				'user_id' => $this->get('user_id'),
				'username' => $this->get('username')			
			);
			
			$commentId = $this->get('comment_id');

			$maxTagged = $this->getOption(self::OPTION_MAX_TAGGED_USERS);
			if ($maxTagged && $this->_taggedUsers)
			{
				if ($maxTagged > 0)
				{
					$alertTagged = array_slice($this->_taggedUsers, 0, $maxTagged, true);
				}
				else
				{
					$alertTagged = $this->_taggedUsers;
				}

				$this->_alertedUsers = $this->_getCommentModel()->alertTaggedMembers($this->_getCommentModel()->getCommentById($commentId, array('join' => XenGallery_Model_Comment::FETCH_MEDIA | XenGallery_Model_Comment::FETCH_ALBUM)), $alertTagged);
			}
			
			if ($content && XenForo_Model_Alert::userReceivesAlert($content, 'xengallery_comment', 'insert')
				&& $content['user_id'] != $commentUser['user_id']
			)
			{
				if (!in_array($content['user_id'], $this->_alertedUsers))
				{
					XenForo_Model_Alert::alert(
						$content['user_id'],
						$commentUser['user_id'],
						$commentUser['username'],
						'xengallery_comment',
						$commentId,
						'insert'
					);
				}

				$this->_alertedUsers[] = $content['user_id'];
			}
			
			$this->_getNewsFeedModel()->publish(
				$commentUser['user_id'],
				$commentUser['username'],
				'xengallery_comment',
				$commentId,
				'insert'
			);					
			
			$ipId = XenForo_Model_Ip::log(
				$this->get('user_id'), 'xengallery_comment', $this->get('comment_id'), 'insert'
			);
			
			$this->_db->update('xengallery_comment', array(
				'ip_id' => $ipId
			), 'comment_id = ' . $this->get('comment_id'));
		}
		
		if ($this->isChanged('comment_state') && ($this->getExisting('comment_state') == 'deleted')
			|| $this->getExisting('comment_state') == 'moderated'
		)
		{
			$this->updateCommentCount();
			$this->_updateLastCommentDate();
		}

		if ($this->isUpdate() && $this->isChanged('comment_state'))
		{
			$this->_updateLastCommentDate();
			// $this->_updateMessagePositionList();
		}
		
		if ($this->isChanged('comment_state') && $this->getExisting('comment_state') == 'visible')
		{
			$this->updateCommentCount(false);
			$this->_updateLastCommentDate();

			$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts('xengallery_comment', $this->get('comment_id'));
		}

		$this->_updateModerationQueue($this->getMergedData());

		$this->_updateDeletionLog(true);
	}

	protected function _postSaveAfterTransaction()
	{
		if ($this->get('comment_state') == 'visible')
		{
			if ($this->get('content_type') == 'album')
			{
				if ($this->isInsert() || $this->getExisting('comment_state') == 'moderated')
				{
					$comment = $this->getMergedData();
					$album = $this->_getAlbumModel()->getAlbumById($this->get('content_id'));

					$this->getModelFromCache('XenGallery_Model_AlbumWatch')->sendNotificationToWatchUsersOnCommentInsert($comment, $album, $this->_alertedUsers);
				}
			}
			else
			{
				if ($this->isInsert() || $this->getExisting('comment_state') == 'moderated')
				{
					$comment = $this->getMergedData();
					$media = $this->_getMediaModel()->getMediaById($this->get('content_id'));

					$this->getModelFromCache('XenGallery_Model_MediaWatch')->sendNotificationToWatchUsersOnCommentInsert($comment, $media, $this->_alertedUsers);
				}
			}
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$this->_updateDeletionLog(true);

		if ($this->get('comment_state') != 'deleted')
		{
			$this->updateCommentCount(false);
			$this->_updateLastCommentDate();

			$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts('xengallery_comment', $this->get('comment_id'));
		}
	}

	protected function _updateLastCommentDate()
	{
		$latestDate = $this->_getCommentModel()->getLatestDate($this->getMergedData(), true);

		if ($this->get('content_type') == 'media')
		{
			$writer = XenForo_DataWriter::create('XenGallery_DataWriter_Media', XenForo_DataWriter::ERROR_SILENT);

			if ($writer->setExistingData($this->get('content_id')))
			{
				$writer->set('last_comment_date', $latestDate);
				$writer->save();
			}
		}
		else
		{
			$writer = XenForo_DataWriter::create('XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);

			if ($writer->setExistingData($this->get('content_id')))
			{
				$writer->set('album_last_comment_date', $latestDate);
				$writer->save();
			}
		}
	}
	
	public function updateCommentCount($increase = true)
	{
		if ($this->get('content_type') == 'media')
		{
			$writer = XenForo_DataWriter::create('XenGallery_DataWriter_Media', XenForo_DataWriter::ERROR_SILENT);

			if ($writer->setExistingData($this->get('content_id')))
			{
				$value = $writer->get('comment_count') + 1;

				if (!$increase)
				{
					$value = $writer->get('comment_count') - 1;
				}

				if ($value < 0)
				{
					$value = 0;
				}

				$writer->set('comment_count', $value);

				$writer->save();
			}
		}
		else
		{
			$writer = XenForo_DataWriter::create('XenGallery_DataWriter_Album', self::ERROR_SILENT);

			if ($writer->setExistingData($this->get('content_id')))
			{
				$value = $writer->get('album_comment_count') + 1;

				if (!$increase)
				{
					$value = $writer->get('album_comment_count') - 1;
				}

				if ($value < 0)
				{
					$value = 0;
				}

				$writer->set('album_comment_count', $value);

				$writer->save();
			}
		}
	}

	/**
	 * Updates the position list based on state changes.
	 */
	protected function _updateMessagePositionList()
	{
		if ($this->get('comment_state') == 'visible' && $this->getExisting('comment_state') != 'visible')
		{
			// $this->_adjustPositionListForInsert();
		}
		else if ($this->get('comment_state') != 'visible' && $this->getExisting('comment_state') == 'visible')
		{
			// $this->_adjustPositionListForRemoval();
		}
	}

	/**
	 * Adjust the position list surrounding this message, when this message
	 * has been put from a position that "counts" (removed or hidden).
	 */
	protected function _adjustPositionListForInsert()
	{
		if ($this->get('comment_state') != 'visible')
		{
			// only renumber if becoming visible
			return;
		}

		$contentType = $this->get('content_type');
		$contentId = $this->get('content_id');

		$contentCondition = "content_type = '$contentType' AND content_id = $contentId";

		$positionQuoted = $this->_db->quote($this->getExisting('position'));
		$commentDateQuoted = $this->_db->quote($this->get('comment_date'));
		$commentCondition = 'comment_id <> ' . $this->_db->quote($this->get('comment_id'));

		$this->_db->query("
			UPDATE xengallery_comment
			SET position = position + 1
			WHERE $contentCondition
				AND (position > $positionQuoted
					OR (position = $positionQuoted AND comment_date > $commentDateQuoted)
				)
				AND $commentCondition
		");
	}

	/**
	 * Adjust the position list surrounding this message, when this message
	 * has been removed from a position that "counts" (removed or hidden).
	 */
	protected function _adjustPositionListForRemoval()
	{
		if ($this->getExisting('comment_state') != 'visible')
		{
			// no need to renumber after removal something that didn't count
			return;
		}

		$contentType = $this->get('content_type');
		$contentId = $this->get('content_id');

		$contentCondition = "content_type = '$contentType' AND content_id = $contentId";

		$commentCondition = 'comment_id <> ' . $this->_db->quote($this->get('comment_id'));

		$this->_db->query('
			UPDATE xengallery_comment
			SET position = IF(position > 0, position - 1, 0)
			WHERE ' . $contentCondition . '
				AND position >= ?
				AND ' . $commentCondition . '
		', $this->getExisting('position'));
	}
	
	protected function _updateDeletionLog($hardDelete = false)
	{
		if ($hardDelete
			|| ($this->isChanged('comment_state') && $this->getExisting('comment_state') == 'deleted')
		)
		{
			$this->getModelFromCache('XenForo_Model_DeletionLog')->removeDeletionLog(
				'xengallery_comment', $this->get('comment_id')
			);			
		}

		if ($this->isChanged('comment_state') && $this->get('comment_state') == 'deleted')
		{
			$reason = $this->getExtraData(self::DATA_DELETE_REASON);
			$this->getModelFromCache('XenForo_Model_DeletionLog')->logDeletion(
				'xengallery_comment', $this->get('comment_id'), $reason
			);
		}
	}

	protected function _updateModerationQueue(array $comment)
	{
		if (!$this->isChanged('comment_state'))
		{
			return;
		}

		if ($this->get('comment_state') == 'moderated')
		{
			$this->getModelFromCache('XenForo_Model_ModerationQueue')->insertIntoModerationQueue(
				'xengallery_comment', $this->get('comment_id'), $this->get('comment_date')
			);
		}
		else if ($this->getExisting('comment_state') == 'moderated')
		{
			$this->getModelFromCache('XenForo_Model_ModerationQueue')->deleteFromModerationQueue(
				'xengallery_comment', $this->get('comment_id')
			);
		}
	}

	protected function _getContentDw()
	{
		if ($this->_contentDw === null)
		{
			if ($this->get('content_type') == 'media')
			{
				$this->_contentDw = XenForo_DataWriter::create('XenGallery_DataWriter_Media', self::ERROR_SILENT);
			}
			else
			{
				$this->_contentDw = XenForo_DataWriter::create('XenGallery_DataWriter_Album', self::ERROR_SILENT);
			}

			$this->_contentDw->setExistingData($this->get('content_id'));
		}

		return $this->_contentDw;
	}

	/**
	 * @return XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Comment');
	}
	
	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Media');
	}

	/**
	 * @return XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Album');
	}

	/**
	 * @return XenForo_Model_Draft
	 */
	protected function _getDraftModel()
	{
		return $this->getModelFromCache('XenForo_Model_Draft');
	}
}