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
class sonnb_XenGallery_DataWriter_Content extends sonnb_XenGallery_DataWriter_Abstract
{
	const DATA_DELETE_REASON = 'dataDeleteReason';
	const DATA_CONTENT_STREAMS = 'dataContentStreams';
	const CHECK_CONTENT = 'checkContent';
	const COVER_CONTENT_DATA_ID = 'coverContentDataId';

	protected $_streams = null;
	protected $_updateCustomFields = null;
	
	protected function _getFields()
	{
		return array(
			'sonnb_xengallery_content' => array(
				'content_id' => array(
					'type' => self::TYPE_UINT,
					'autoIncrement' => true
				),
				'content_type' => array(
					'type' => self::TYPE_STRING,
					'default' => 'photo',
					'allowedValues' => array('photo','audio','video'),
				),
				'album_id' => array(
					'type' => self::TYPE_UINT
				),
				'title' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 255,
					'default' => ''
				),
				'description' => array(
					'type' => self::TYPE_STRING,
					'default' => '',
					'maxLength' => 2000,
				),
				'user_id' => array(
					'type' => self::TYPE_UINT,
				),
				'username' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 50,
					'default' => ''
				),
				'collection_id' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'content_data_id' => array(
					'type' => self::TYPE_UINT,
				),
				'content_state' => array(
					'type' => self::TYPE_STRING,
					'default' => 'visible',
					'allowedValues' => array('visible','moderated','deleted'),
				),
				'comment_count' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'view_count' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'tags' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'tag_users' => array(
					'type' => self::TYPE_SERIALIZED,
					'default' => 'a:0:{}'
				),
				'likes' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'like_users' => array(
					'type' => self::TYPE_SERIALIZED,
					'default' => 'a:0:{}'
				),
				'content_streams' => array(
					'type' => self::TYPE_SERIALIZED,
					'default' => 'a:0:{}'
				),
				'content_privacy' => array(
					'type' => self::TYPE_SERIALIZED,
					'verification' => array('$this', '_verifyPrivacy')
				),
				'content_location' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 255,
					'default' => ''
				),
				'position' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'latest_comment_ids' => array(
					'type' => self::TYPE_BINARY,
					'default' => '',
					'maxLength' => 100
				),
				'content_date' => array(
					'type' => self::TYPE_UINT,
					'default' => XenForo_Application::$time,
				),
				'content_updated_date' => array(
					'type' => self::TYPE_UINT,
					'default' => XenForo_Application::$time,
				),
			)
		);
	}
	
	protected function _preSave()
	{
		$xenOptions = XenForo_Application::getOptions();
		$contentType = $this->get('content_type');

		if ($this->getExtraData(self::CHECK_CONTENT) === true && $xenOptions->sonnbXG_requireContentTitle && utf8_strlen($this->get('title')) < 1)
		{
			$this->error(new XenForo_Phrase('please_enter_value_for_required_field_x', array('field' => 'title')), 'title', false);
		}

		$streams = $this->getExtraData(self::DATA_CONTENT_STREAMS);
		if ($streams || @unserialize($this->get('content_streams')))
		{
			if (!is_array($streams))
			{
				$streams = explode(',', $streams);
				$streams = array_filter($streams, 'utf8_trim');
				$streams = array_filter($streams);
				$streams = array_unique($streams);
			}
			$this->_streams = $streams;

			if ($this->isUpdate())
			{
				$this->_processStreams();
			}
		}

		if ($this->isInsert())
		{
			if ($this->_getContentModel()->getContentDefaultState($this->get('content_type')))
			{
				$this->set('content_state', 'moderated');
			}
		}

		if ($this->isUpdate() && 
				($this->isChanged('tags') || $this->isChanged('description')) || $this->isChanged('content_location'))
		{
			$this->set('content_updated_date', XenForo_Application::$time);
		}
		
		if (!$this->get('content_privacy'))
		{
			$privacy = array(
				'allow_view' => $this->_getDefaultPrivacy($contentType, 'view'),
				'allow_view_data' => array(),
				'allow_comment' => $this->_getDefaultPrivacy($contentType, 'comment'),
				'allow_comment_data' => array()
			);
			
			$this->set('content_privacy', $privacy);
		}

		if ($this->isInsert())
		{
			$db = $this->_db;
			$existent = $db->fetchOne("
				SELECT COUNT(*)
				FROM sonnb_xengallery_content
				WHERE content_data_id = ?
			", $this->get('content_data_id'));

			if ($existent)
			{
				$this->error(new XenForo_Phrase('sonnb_xengallery_duplicated_'.$contentType.'_data_id'));
			}
		}

		if ($this->isChanged('description'))
		{
			$this->_taggedUsers = $this->_getUserTaggingModel()->getTaggedUsersInMessage(
				$this->get('description'), $newMessage, 'text'
			);
			$this->set('description', $newMessage);
		}
	}
	
	protected function _postSave()
	{
		if ($this->_streams && $this->isInsert())
		{
			$this->_processStreams();
		}

		if ($this->isChanged('content_state') && $this->get('album_id'))
		{
			/* @var $albumDw sonnb_XenGallery_DataWriter_Album */
			$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);
			$albumDw->setExistingData($this->get('album_id'));
			
			if ($this->get('content_state') === 'visible')
			{
				$albumDw->insertNewContent($this->get('content_type'), $this->get('content_id'), $this->get('content_date'));
			}
			elseif ($this->isUpdate() && $this->getExisting('content_state') === 'visible')
			{
				$albumDw->rebuildContentCounters();
			}

			if ($this->isUpdate())
			{
				$albumDw->rebuildContentPositions();
			}
			
			$albumDw->save();
		}

		if ($this->isUpdate() && $this->isChanged('album_id'))
		{
			$albumUpdateIds = array($this->getExisting('album_id'), $this->get('album_id'));

			foreach ($albumUpdateIds as $_albumId)
			{
				if (intval($_albumId) < 0)
				{
					continue;
				}

				/* @var $albumDw sonnb_XenGallery_DataWriter_Album */
				$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);
				$albumDw->setExistingData($_albumId);
				$albumDw->rebuildContentCounters();
				$albumDw->rebuildContentPositions();
				$albumDw->save();
			}
		}

		$this->_processCollectionChanges();
		
		if ($this->isInsert())
		{
			$this->_logIpExtend('insert');
		}
		
		if ($this->isChanged('content_state'))
		{
			$this->rebuildUserContentCount();
			$this->_updateDeletionLog();
			$this->_updateModerationQueue();
		}

		if ($this->isInsert() && ($this->get('description') || $this->get('title')))
		{
			$this->_rebuildContentIndex();
		}
		elseif ($this->isUpdate() && ($this->isChanged('description') || $this->isChanged('title')))
		{
			$this->_rebuildContentIndex();
		}

		if ($this->isChanged('content_state') && $this->get('content_state') === 'deleted')
		{
			$this->_logIpExtend('soft-delete');
		}

        if ($this->isUpdate() && $this->isChanged('user_id'))
        {
            $oldUserId = $this->getExisting('user_id');
            $newUserId = $this->get('user_id');

            $this->rebuildUserContentCount($oldUserId);
            $this->rebuildUserContentCount($newUserId);
        }

        if ($this->isChanged('collection_id') && $this->get('collection_id'))
        {
            $this->_db->update(
                'sonnb_xengallery_collection',
                array(
                    'last_content_date' => XenForo_Application::$time
                ),
                array(
                    'collection_id = ?' => $this->get('collection_id')
                )
            );
        }

		$this->updateCustomFields();

		if ($this->get('content_state') === 'visible'
			&& ($this->isInsert() || $this->getExisting('content_state') === 'moderated'))
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
					$content['content_id'],
					$this->_getXfContentType($this->get('content_type')),
					$alertTagged
				);
			}
		}

		//TODO: Write to history
	}

	public function setCustomFields(array $fieldValues, array $category = null)
	{
		$fieldModel = $this->_getFieldModel();

		$contentType = $this->get('content_type');
		$contentId = ($this->get('content_id') ? $this->get('content_id') : null);

		$applicableFields = $fieldModel->getApplicableFieldsByContentId($contentType, $contentId, $category);

		if (empty($applicableFields))
		{
			return;
		}

		$finalValues = array();

		foreach ($applicableFields AS $fieldId => $field)
		{
			$multiChoice = ($field['field_type'] == 'checkbox' || $field['field_type'] == 'multiselect');

			if ($multiChoice)
			{
				$value = array();
				if (isset($fieldValues[$fieldId]) && is_array($fieldValues[$fieldId]))
				{
					$value = $fieldValues[$fieldId];
				}
			}
			else
			{
				$value = '';
				if (isset($fieldValues[$fieldId]))
				{
					$value = strval($fieldValues[$fieldId]);
				}
			}

			$existingValue = null;
			if (isset($applicableFields[$fieldId]))
			{
				$existingValue = $applicableFields[$fieldId];
			}

			if (!$this->_importMode)
			{
				$valid = $fieldModel->verifyFieldValue($field, $value, $error);
				if (!$valid)
				{
					$this->error($error, "custom_field_$fieldId");
					continue;
				}

				if ($field['required'] && ($value === '' || $value === array()))
				{
					$this->error(new XenForo_Phrase('please_enter_value_for_all_required_fields'), "required");
					continue;
				}
			}

			if ($value !== $existingValue)
			{
				$finalValues[$fieldId] = $value;
			}
		}

		$this->_updateCustomFields = $finalValues;
	}

	public function updateCustomFields()
	{
		if ($this->_updateCustomFields)
		{
			$user = XenForo_Visitor::getInstance();
			$contentType = $this->get('content_type');
			$contentId = $this->get('content_id');

			foreach ($this->_updateCustomFields AS $fieldId => $value)
			{
				if (is_array($value))
				{
					$value = serialize($value);
				}

				$this->_db->query('
					INSERT INTO sonnb_xengallery_field_value
						(field_id, content_type, content_id, field_value, user_id, username)
					VALUES
						(?, ?, ?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE
						field_value = VALUES(field_value)
				', array($fieldId, $contentType, $contentId, $value, $user['user_id'], $user['username']
				));
			}
		}
	}

	protected function _processStreams()
	{
		$deleteArray = array();
		$existingStreams = $this->get('content_streams');
		$existingStreams = @unserialize($existingStreams);

		if (!empty($this->_streams))
		{
			foreach ($existingStreams as $_index => $_stream)
			{
				if (!in_array($_stream, $this->_streams))
				{
					unset($existingStreams[$_index]);
					$deleteArray[] = $_stream;
				}
			}

			if ($deleteArray)
			{
				$this->_getStreamModel()->removeStreams(
					$this->get('content_type'),
					$this->get('content_id'),
					$deleteArray
				);
			}

			$streams = $this->_getStreamModel()->publishStream(
				$this->get('content_type'),
				$this->get('content_id'),
				$this->_streams
			);

			if ($streams === -1)
			{
				$this->error(new XenForo_Phrase(
					'sonnb_xengallery_you_are_allowed_to_add_x_streams_to_a_single_'.$this->get('content_type').'_album',
					array(
						'limit' => $this->_getGalleryModel()->getMaximumStreamCount()
					)
				), 'stream_name');
			}

			if (!empty($streams))
			{
				if ($existingStreams)
				{
					$existingStreams = array_merge($existingStreams, $streams);
				}
				else
				{
					$existingStreams = $streams;
				}
			}
		}

		$existingStreams = array_unique($existingStreams);
		$existingStreams = serialize($existingStreams);

		XenForo_Application::getDb()->update(
			'sonnb_xengallery_content',
			array('content_streams' => $existingStreams),
			array(
				"content_id = ".$this->get('content_id'),
				"content_type = '".$this->get('content_type')."'"
			)
		);
	}
	
	protected function _verifyPrivacy(&$privacy)
	{
		if ($privacy === null || empty($privacy))
		{
			$contentType = $this->get('content_type');

			$privacy = array(
				'allow_view' => $this->_getDefaultPrivacy($contentType, 'view'),
				'allow_view_data' => array(),
				'allow_comment' => $this->_getDefaultPrivacy($contentType, 'comment'),
				'allow_comment_data' => array()
			);
			
			return true;
		}
		
		return XenForo_DataWriter_Helper_Denormalization::verifySerialized($privacy, $this, 'content_privacy');
	}
	
	public function insertLocation($data)
	{
		if (!isset($data['location_lat']) || !isset($data['location_lng']))
		{
			return false;
		}

		if ($this->isChanged('content_location'))
		{
			$contentType = $this->get('content_type');
			$contentId = $this->get('content_id');
				
			$existingLocation = $this->_getLocationModel()->getLocationByContentId($contentType, $contentId);
				
			if ($existingLocation)
			{
				$this->_db->update('sonnb_xengallery_location',
						array(
							'location_lat' => $data['location_lat'],
							'location_lng' => $data['location_lng'],
							'location_name' => trim($this->get('content_location')),
							'location_url' => sonnb_XenGallery_Model_Gallery::getTitleForUrl(trim($this->get('content_location')))
						),
						'location_id = '.$existingLocation['location_id']
				);
			}
			else
			{
				if (utf8_strlen($this->get('content_location')))
				{
					$this->_db->insert('sonnb_xengallery_location', array(
							'content_type' => $contentType,
							'content_id' => $contentId,
							'location_lat' => $data['location_lat'],
							'location_lng' => $data['location_lng'],
							'location_name' => trim($this->get('content_location')),
							'location_url' => sonnb_XenGallery_Model_Gallery::getTitleForUrl(trim($this->get('content_location')))
					));
				}
			}
		}
	}

	protected function _processCollectionChanges()
	{
		$collectionModel = $this->_getCollectionModel();
		$contentType = $this->get('content_type');
		$contentId = $this->get('content_id');

		if ($this->isInsert() && $this->get('collection_id'))
		{
			$collectionModel->modifyCollectionCount($this->get('collection_id'), 1, $contentType, $contentId);
		}
		elseif ($this->isUpdate() && $this->isChanged('collection_id'))
		{
			if ($this->getExisting('collection_id'))
			{
				$collectionModel->modifyCollectionCount($this->getExisting('collection_id'), -1, $contentType, $contentId);
			}

			if ($this->get('collection_id'))
			{
				$collectionModel->modifyCollectionCount($this->get('collection_id'), 1, $contentType, $contentId);
			}
		}
	}

	public function addTag(array $tag, $overwrite = false)
	{
		$existingTags = array();

		if ($overwrite === false)
		{
			$existingTags = $this->get('tag_users');
			if (!is_array($existingTags))
			{
				$existingTags = @unserialize($existingTags);
			}

			if ($existingTags)
			{
				foreach ($existingTags as $user)
				{
					if ($user['user_id'] === $tag['user_id'])
					{
						return;
					}
				}
			}
		}

		$existingTags[] = array(
			'user_id' => $tag['user_id'],
			'username' => $tag['username']
		);

		$this->set('tag_users', $existingTags);
		$this->set('tags', count($existingTags));
	}

	public function removeTag(array $tag)
	{
		$existingTags = $this->get('tag_users');
		if (!is_array($existingTags))
		{
			$existingTags = @unserialize($existingTags);
		}

		if ($existingTags)
		{
			foreach ($existingTags as $key => $user)
			{
				if ($user['user_id'] === $tag['user_id'])
				{
					unset($existingTags[$key]);

					$this->set('tag_users', $existingTags);
					$this->set('tags', $this->get('tags') - 1);
				}
			}
		}
	}

	protected function _preDelete()
	{
		//$this->set('content_state', 'deleted');
	}
	
	protected function _postDelete()
	{
		$contentType = $this->get('content_type');
		$contentId = $this->get('content_id');

		if ($this->get('album_id') && $this->get('content_state') === 'visible')
		{
			/* @var $albumDw sonnb_XenGallery_DataWriter_Album*/
			$albumDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);
			$albumDw->setExistingData($this->get('album_id'));

			if ($albumDw->get('album_state') != 'deleted')
			{
				if ($albumDw->get('cover_content_id') === $contentId &&
					$albumDw->get('cover_content_type') === $contentType)
				{
					$albumDw->set('cover_content_id', 0);
				}

				$albumDw->rebuildContentCounters();
				$albumDw->save();
				$albumDw->rebuildContentPositions();
			}
		}

		if ($this->get('content_data_id'))
		{
			$dataDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_ContentData', XenForo_DataWriter::ERROR_SILENT);
			$dataDw->setExistingData($this->get('content_data_id'));
			$dataDw->delete();
		}

		$this->_logIpExtend('delete');

		$this->_getCollectionModel()->modifyCollectionCount($this->get('collection_id'), -1, $contentType, $contentId);

		$this->_db->delete('sonnb_xengallery_watch', "content_id = $contentId AND content_type='$contentType'");
		$this->_db->delete('sonnb_xengallery_location', "content_id = $contentId AND content_type='$contentType'");
		$this->_db->delete('sonnb_xengallery_photo_camera', "photo_id = $contentId");
		$this->_db->delete('sonnb_xengallery_tag', "content_id = $contentId AND content_type='$contentType'");
		$this->_db->delete('sonnb_xengallery_stream', "content_id = $contentId AND content_type='$contentType'");

		$this->rebuildUserContentCount();
		$this->_deleteComment();
		$this->_deleteFromNewsFeed();
		$this->_deleteFromAlert();
		$this->_deleteFromSearchIndex();

		$this->_updateDeletionLog(true);
		$this->_updateModerationQueue(true);

		$this->_getFieldModel()->deleteFieldValueByContentId($this->get('content_type'), $this->get('content_id'));
		
		//TODO: Write to history
	}
	
	public function insertCustomPrivacy($privacy)
	{
		$contentPrivacy = $this->get('content_privacy');
		$contentPrivacy = @unserialize($contentPrivacy);
		
		if ($contentPrivacy['allow_view'] === 'custom')
		{
			$contentPrivacy['allow_view_data'] = $this->_getPrivacyUsers($privacy['allow_view_username']);
		}

		if ($contentPrivacy['allow_comment'] === 'custom')
		{
			$contentPrivacy['allow_comment_data'] = $this->_getPrivacyUsers($privacy['allow_comment_username']);
		}
		
		$this->set('content_privacy', $contentPrivacy);
	}
	
	protected function _getPrivacyUsers($usernames, $serialize = true)
	{
		if (!is_array($usernames))
		{
			$usernames = explode(',', $usernames);
			$usernames = array_filter($usernames);
		}
	
		$privacyUsers = array();
	
		if (!empty($usernames))
		{
			$users = $this->_getUserModel()->getUsersByNames($usernames);
	
			$userKeys = array('user_id', 'username');
	
			foreach ($users AS $key => $user)
			{
				$privacyUsers[$key] = XenForo_Application::arrayFilterKeys($user, $userKeys);
			}
		}
	
		if ($serialize)
		{
			$privacyUsers = serialize($privacyUsers);
		}
	
		return $privacyUsers;
	}
	
	protected function _publishToNewsFeed()
	{
		$contentType = $this->_getXfContentType($this->get('content_type'));
		$contentId = $this->get('content_id');

		$this->_getNewsFeedModel()->publish(
			$this->get('user_id'),
			$this->get('username'),
			$contentType,
			$contentId,
			($this->isUpdate() ? 'update' : 'insert'),
			array('content' => $this->getMergedData())
		);
	}

	protected function _deleteFromNewsFeed()
	{
		$contentType = $this->_getXfContentType($this->get('content_type'));
		$contentId = $this->get('content_id');

		$this->_getNewsFeedModel()->delete(
			$contentType,
			$contentId
		);
	}

	public function rebuildUserContentCount($userId = null)
	{
        if ($userId === null)
        {
            $userId = $this->get('user_id');
        }

		$contentType = $this->get('content_type');

		$contentCount = $this->_getContentModel()->countContents(
			array(
				'content_type' => $contentType,
				'user_id' => $userId,
				'content_state' => 'visible'
			)
		);

		$this->_db->update('xf_user', array('sonnb_xengallery_'.$contentType.'_count' => $contentCount), 'user_id = '.$userId);
	}

	protected function _deleteComment()
	{
		$contentType = $this->get('content_type');
		$contentId = $this->get('content_id');

		if ($this->get('comment_count'))
		{
			$comments = $this->_getCommentModel()->getCommentsByContentId($contentType, $contentId);

			if ($comments)
			{
				foreach ($comments as $commentId=>$comment)
				{
					$commentDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Comment', XenForo_DataWriter::ERROR_SILENT);
					$commentDw->setExistingData($commentId);
					$commentDw->delete();
				}
			}
		}
	}
	
	protected function _deleteFromAlert()
	{
		$contentType = $this->_getXfContentType($this->get('content_type'));
		$contentId = $this->get('content_id');

		$this->_getAlertModel()->deleteAlerts($contentType, $contentId);
	}

	protected function _deleteFromSearchIndex()
	{
		$contentType = $this->_getXfContentType($this->get('content_type'));
		$contentId = $this->get('content_id');

		$indexer = new XenForo_Search_Indexer();
		$indexer->deleteFromIndex($contentType, $contentId);
	}
	
	public function insertNewComment($commentId, $commentDate)
	{
		$this->set('comment_count', $this->get('comment_count') + 1);
		
		$latest = $this->get('latest_comment_ids');
		$ids = ($latest ? explode(',', $latest) : array());
		$ids[] = $commentId;

		if (count($ids) > 5)
		{
			$ids = array_slice($ids, -5);
		}

		$this->set('latest_comment_ids', implode(',', $ids));
	}
	
	public function rebuildCommentCounters()
	{
		$contentType = $this->get('content_type');
		$contentId = $this->get('content_id');
		$db = $this->_db;
	
		$counts = $db->fetchOne('
			SELECT COUNT(*)
			FROM sonnb_xengallery_comment
			WHERE
				content_type = ?
				AND content_id = ?
		', array($contentType, $contentId));
	
		if ($counts)
		{
			$ids = $db->fetchCol($db->limit(
					'
					SELECT `comment_id`
					FROM `sonnb_xengallery_comment`
					WHERE
						content_type = ?
						AND content_id = ?
					ORDER BY comment_date DESC
				', 5
			), array($contentType, $contentId));
				
			$ids = array_reverse($ids);
		}
		else
		{
			$ids = array();
		}
	
		$this->set('comment_count', $counts);
		$this->set('latest_comment_ids', implode(',', $ids));
	}

	protected function _updateDeletionLog($hardDelete = false)
	{
		$contentType = $this->_getXfContentType($this->get('content_type'));
		$contentId = $this->get('content_id');

		if ($hardDelete
			|| ($this->isChanged('content_state') && $this->getExisting('content_state') === 'deleted')
		)
		{
			$this->getModelFromCache('XenForo_Model_DeletionLog')->removeDeletionLog(
				$contentType, $contentId
			);
		}

		if ($this->isChanged('content_state') && $this->get('content_state') === 'deleted')
		{
			$reason = $this->getExtraData(self::DATA_DELETE_REASON);
			$this->getModelFromCache('XenForo_Model_DeletionLog')->logDeletion(
				$contentType, $contentId, $reason
			);
		}
	}
	
	protected function _updateModerationQueue($hardDelete = false)
	{
		if (!$this->isChanged('content_state'))
		{
			return;
		}

		$contentType = $this->_getXfContentType($this->get('content_type'));
		$contentId = $this->get('content_id');

		if ($hardDelete || $this->getExisting('content_state') === 'moderated')
		{
			$this->_getModerationQueue()->deleteFromModerationQueue(
				$contentType, $contentId
			);
		}
		elseif ($this->get('content_state') === 'moderated')
		{
			$this->_getModerationQueue()->insertIntoModerationQueue(
				$contentType, $contentId, $this->get('content_date')
			);
		}
	}

	public function rebuildCounters(array $options = array(), array $contentData = null)
	{
		$options = array_merge(array(
			'exif' => false,
			'user' => false,
			'index' => true,
			'streams' => false,
			'tags' => false,
			'thumbnail' => false,

			'thumbnail_information' => false,
			'apply_watermark' => false,

			'move' => false,
			'move_target' => 'local',

            'delete_original' => false
		), $options);

		$this->rebuildCommentCounters();

		if ($contentData === null)
		{
			$contentData = $this->_getContentDataModel()->getDataByDataId($this->get('content_data_id'));
		}

		/*
		 * TODO - FIX ME: This will remove current information???
		if ($this->get('content_location'))
		{
			$locationData = array(
				'location_name' => $this->get('content_location'),
				'location_lat' => '',
				'location_lng' => ''
			);

			$this->_getLocationModel()->insertLocation(
				$contentType,
				$contentId,
				$locationData
			);
		}
		*/

		if (XenForo_Application::isRegistered('addOns'))
		{
			$addOns = XenForo_Application::get('addOns');
		}
		if (!empty($contentData) && $options['move'] && class_exists('bdAttachmentStore_Model_File') && !empty($addOns['bdAttachmentStore']))
		{
			/** @var $dataDw sonnb_XenGallery_DataWriter_ContentData */
			$dataDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_ContentData');
			$dataDw->setExistingData($contentData['content_data_id']);
			$dataDw->moveContentData($options['move_target']);
			$dataDw->save();
		}

		if ($options['user'] && $this->get('user_id'))
		{
			$user = $this->_getUserModel()->getUserById($this->get('user_id'));
			if ($user)
			{
				$this->set('username', $user['username']);
			}
			else
			{
				$this->set('user_id', 0);
			}
		}

		if ($options['index'])
		{
			$this->_rebuildContentIndex();
		}

		//TODO: Rebuild streams
		//TODO: Rebuild tags
	}

	protected function _logIpExtend($action)
	{
		$contentType = $this->_getXfContentType($this->get('content_type'));
		$contentId = $this->get('content_id');

        $this->_logIp($contentType, $contentId, $action);
	}

	protected function _getExistingData($data)
	{
		if (!$contentId = $this->_getExistingPrimaryKey($data, 'content_id'))
		{
			if (!$contentDataId = $this->_getExistingPrimaryKey($data, 'content_data_id'))
			{
				return false;
			}
		}

		if ($contentId)
		{
			$content = $this->_getContentModel()->getContentById($contentId);
		}
		else
		{
			$content = $this->_getContentModel()->getContentByDataId($contentDataId);
		}

		if (!$content)
		{
			return false;
		}

		return $this->getTablesDataFromArray($content);
	}
	
	protected function _getUpdateCondition($tableName)
	{
		return 'content_id = ' . $this->_db->quote($this->getExisting('content_id'));
	}

    /**
     * @return bdAttachmentStore_Model_File
     */
    protected function _bdAttachmentStore_getFileModel()
    {
        return $this->getModelFromCache('bdAttachmentStore_Model_File');
    }
}