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
class sonnb_XenGallery_DataWriter_Album extends sonnb_XenGallery_DataWriter_Abstract
{
    const CHECK_CATEGORY = 'enableCheckCategory';
	const DATA_DELETE_REASON = 'dataDeleteReason';
	const DATA_ALBUM_STREAMS = 'dataAlbumStreams';
	const COVER_CONTENT_DATA_ID = 'coverContentDataId';

	protected $_streams = null;
	protected $_updateCustomFields = null;

	protected function _getFields()
	{
		return array(
			'sonnb_xengallery_album' => array(
				'album_id' => array(
					'type' => self::TYPE_UINT,
					'autoIncrement' => true
				),
				'title' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 255,
					'required' => true,
				),
				'description' => array(
					'type' => self::TYPE_STRING,
					'default' => '',
					'maxLength' => 2000,
				),
				'user_id' => array(
					'type' => self::TYPE_UINT,
					'required' => true,
				),
				'username' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 50,
					'required' => true,
					'default' => ''
				),
				'album_type' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'album_state' => array(
					'type' => self::TYPE_STRING,
					'default' => 'visible',
					'allowedValues' => array('visible','moderated','deleted'),
				),
				'category_id' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'collection_id' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'comment_count' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'view_count' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),

				'photo_count' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'video_count' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'audio_count' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'content_count' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),

				'likes' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'like_users' => array(
					'type' => self::TYPE_SERIALIZED,
					'default' => 'a:0:{}'
				),
				'tags' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'tag_users' => array(
					'type' => self::TYPE_SERIALIZED,
					'default' => 'a:0:{}'
				),
				'album_streams' => array(
					'type' => self::TYPE_SERIALIZED,
					'default' => 'a:0:{}'
				),
				'album_privacy' => array(
					'type' => self::TYPE_SERIALIZED,
					'verification' => array('$this', '_verifyPrivacy')
				),
				'album_location' => array(
					'type' => self::TYPE_STRING,
					'maxLength' => 255,
					'default' => ''
				),
				'album_hover' => array(
					'type' => self::TYPE_SERIALIZED,
					'default' => 'a:0:{}'
				),

				'cover_content_id' => array(
					'type' => self::TYPE_UINT,
					'default' => 0,
				),
				'cover_content_type' => array(
					'type' => self::TYPE_STRING,
					'default' => 'photo',
					'allowedValues' => array('photo','audio','video'),
				),

				'latest_photo_ids' => array(
					'type' => self::TYPE_BINARY,
					'default' => '',
					'maxLength' => 100
				),
				'latest_video_ids' => array(
					'type' => self::TYPE_BINARY,
					'default' => '',
					'maxLength' => 100
				),
				'latest_audio_ids' => array(
					'type' => self::TYPE_BINARY,
					'default' => '',
					'maxLength' => 100
				),
				'latest_content_ids' => array(
					'type' => self::TYPE_BINARY,
					'default' => '',
					'maxLength' => 100
				),
				'latest_comment_ids' => array(
					'type' => self::TYPE_BINARY,
					'default' => '',
					'maxLength' => 100
				),

				'album_date' => array(
					'type' => self::TYPE_UINT,
					'default' => XenForo_Application::$time,
				),
				'album_updated_date' => array(
					'type' => self::TYPE_UINT,
					'default' => XenForo_Application::$time,
				)
			)
		);
	}

	protected function _preSave()
	{
		$xenOptions = XenForo_Application::getOptions();
		if ($this->getExtraData(self::CHECK_CATEGORY) === true && $this->get('album_type') == sonnb_XenGallery_Model_Album::ALBUM_TYPE_NORMAL)
		{
			if (!$xenOptions->sonnbXG_disableCategory &&
				$xenOptions->sonnbXG_requireCategory
				&& $this->_getCategoryModel()->countCategories()
				&& !$this->get('category_id'))
			{
				$this->error(new XenForo_Phrase('sonnb_xengallery_please_select_a_category_for_your_album'), 'category_id');
			}
		}

		$streams = $this->getExtraData(self::DATA_ALBUM_STREAMS);
		if ($streams || @unserialize($this->get('album_streams')))
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
			$visitor = XenForo_Visitor::getInstance();
			$albumModel = $this->_getAlbumModel();

			$albumLimit = $albumModel->getUserMaximumAllowedAlbumCount();
			if ($albumLimit > 0 && $visitor->sonnb_xengallery_album_count >= $albumLimit)
			{
				$this->error(new XenForo_Phrase('sonnb_xengallery_you_have_reached_total_allowed_album_limit', array('limit' => $albumLimit)));
			}

			$conditionMobileAlbum = array(
				'user_id' => $visitor->user_id,
				'album_type' => sonnb_XenGallery_Model_Album::ALBUM_TYPE_MOBILE
			);
			if ($this->get('album_type') == sonnb_XenGallery_Model_Album::ALBUM_TYPE_MOBILE &&
				$albumModel->countAlbums($conditionMobileAlbum))
			{
				$this->error(new XenForo_Phrase('sonnb_xengallery_you_only_can_create_one_mobile_upload_album'));
			}

			$conditionProfileAlbum = array(
				'user_id' => $visitor->user_id,
				'album_type' => sonnb_XenGallery_Model_Album::ALBUM_TYPE_PROFILE
			);
			if ($this->get('album_type') == sonnb_XenGallery_Model_Album::ALBUM_TYPE_PROFILE &&
				$albumModel->countAlbums($conditionProfileAlbum))
			{
				$this->error(new XenForo_Phrase('sonnb_xengallery_you_only_can_create_one_profile_album'));
			}

			if ($albumModel->getAlbumDefaultState())
			{
				$this->set('album_state', 'moderated');
			}
		}

		$changedArray = array(
			'title', 'description', 'album_location', 'album_privacy',
			'photo_count', 'video_count', 'audio_count', 'content_count',
			'cover_content_id', 'category_id', 'album_streams', 'tags'
		);
		if ($this->isUpdate())
		{
			foreach ($changedArray as $_changeField)
			{
				if ($this->isChanged($_changeField))
				{
					$this->set('album_updated_date', XenForo_Application::$time);
					break;
				}
			}
		}

		if (!$this->get('album_privacy'))
		{
			$xenOptions = XenForo_Application::getOptions();

			$privacy = array(
				'allow_view' => $xenOptions->sonnbXG_albumPrivacyView,
				'allow_view_data' => array(),
				'allow_comment' => $xenOptions->sonnbXG_albumPrivacyComment,
				'allow_comment_data' => array(),
				'allow_download' => $xenOptions->sonnbXG_albumPrivacyDownload,
				'allow_download_data' => array(),
				'allow_add_photo' => $xenOptions->sonnbXG_albumPrivacyAdd,
				'allow_add_photo_data' => array(),
				'allow_add_video' => $xenOptions->sonnbXG_albumPrivacyAddVideo,
				'allow_add_video_data' => array()
			);

			$this->set('album_privacy', $privacy);
		}

		if ($this->isChanged('description'))
		{
			$this->_taggedUsers = $this->_getUserTaggingModel()->getTaggedUsersInMessage(
				$this->get('description'), $newMessage, 'text'
			);

			$this->set('description', $newMessage);
		}

		//TODO: ???
	}

	protected function _postSave()
	{
		if ($this->_streams && $this->isInsert())
		{
			$this->_processStreams();
		}

		$this->_processCategoryChanges();
		$this->_processCollectionChanges();

		if ($this->isInsert())
		{
			$this->_logIpExtend('insert');

			if ($this->get('album_type') == sonnb_XenGallery_Model_Album::ALBUM_TYPE_NORMAL)
			{
				$this->_publishToNewsFeed();
			}
		}

		if ($this->isChanged('album_state'))
		{
			$this->_updateDeletionLog();
			$this->_updateModerationQueue();
			$this->rebuildUserAlbumCount();
		}

		if ($this->isInsert() && ($this->get('description') || $this->get('title')))
		{
			$indexer = new XenForo_Search_Indexer();
			$dataHandler = XenForo_Search_DataHandler_Abstract::create('sonnb_XenGallery_Search_DataHandler_Album');
			$dataHandler->insertIntoIndex($indexer, $this->getMergedData());
		}
		elseif ($this->isUpdate() && ($this->isChanged('description') || $this->isChanged('title')))
		{
			$indexer = new XenForo_Search_Indexer();
			$dataHandler = XenForo_Search_DataHandler_Abstract::create('sonnb_XenGallery_Search_DataHandler_Album');
			$dataHandler->insertIntoIndex($indexer, $this->getMergedData());
		}

		if ($this->isChanged('album_state') && $this->get('album_state') === 'deleted')
		{
			$this->_logIpExtend('soft-delete');
		}

        if ($this->isUpdate() && $this->isChanged('user_id'))
        {
            $oldUserId = $this->getExisting('user_id');
            $newUserId = $this->get('user_id');

            $this->rebuildUserAlbumCount($oldUserId);
            $this->rebuildUserAlbumCount($newUserId);
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

		if ($this->get('album_state') === 'visible'
			&& ($this->isInsert() || $this->getExisting('album_state') === 'moderated'))
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
					$content['album_id'],
					sonnb_XenGallery_Model_Album::$xfContentType,
					$alertTagged
				);
			}
		}

		//TODO: Write to history
	}

	protected function _preDelete()
	{

	}

	protected function _postDelete()
	{
		$albumId = $this->get('album_id');
		$albumModel = $this->_getAlbumModel();
		$contentType = sonnb_XenGallery_Model_Album::$contentType;

		$this->_logIpExtend('delete');

		if ($this->get('category_id') && $this->get('album_state') === 'visible')
		{
			$albumModel->modifyAlbumCount($this->getExisting('category_id'), -1);
		}

		if ($this->get('collection_id') && $this->get('album_state') === 'visible')
		{
			$albumModel->modifyCollectionCount($this->getExisting('collection_id'), -1, $this->getMergedData());
		}

		if ($this->get('content_count'))
		{
			$contents = $this->_getContentModel()->getContentsByAlbumId($this->getExisting('album_id'));

			if ($contents)
			{
				foreach ($contents as $contentId => $content)
				{
					$contentDw = $this->_getXfContentDw($content['content_type'], XenForo_DataWriter::ERROR_SILENT);
					$contentDw->setExistingData($contentId);
					$contentDw->delete();
				}
			}
		}

		$this->_db->delete('sonnb_xengallery_watch', "content_id = $albumId AND content_type='$contentType'");
		$this->_db->delete('sonnb_xengallery_location', "content_id = $albumId AND content_type='$contentType'");
		$this->_db->delete('sonnb_xengallery_tag', "content_id = $albumId AND content_type='$contentType'");
		$this->_db->delete('sonnb_xengallery_stream', "content_id = $albumId AND content_type='$contentType'");

		$this->rebuildUserAlbumCount();

		$this->_deleteFromNewsFeed();
		$this->_deleteFromAlert();

		$this->_updateDeletionLog(true);
		$this->_updateModerationQueue(true);

		if ($this->get('comment_count'))
		{
			$comments = $this->_getCommentModel()->getCommentsByContentId(sonnb_XenGallery_Model_Album::$contentType, $albumId);

			if ($comments)
			{
				foreach ($comments as $comment)
				{
					$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Comment', XenForo_DataWriter::ERROR_SILENT);
					$dw->setExistingData($comment, true);

					$dw->delete();
				}
			}
		}

		$indexer = new XenForo_Search_Indexer();
		$indexer->deleteFromIndex('sonnb_xengallery_album', $albumId);

		$this->_getFieldModel()->deleteFieldValueByContentId(sonnb_XenGallery_Model_Album::$contentType, $albumId);

		//TODO: Write to history
	}

	public function setCustomFields(array $fieldValues)
	{
		$fieldModel = $this->_getFieldModel();

		$contentType = sonnb_XenGallery_Model_Album::$contentType;
		$contentId = ($this->get('album_id') ? $this->get('album_id') : null);
		$category = ($this->get('category_id') ? array('category_id' => $this->get('category_id')) : null);

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
			$albumId = $this->get('album_id');

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
				', array(
					$fieldId,
					sonnb_XenGallery_Model_Album::$contentType,
					$albumId,
					$value,
					$user['user_id'],
					$user['username']
				));
			}
		}
	}

	protected function _processStreams()
	{
		$deleteArray = array();
		$existingStreams = $this->get('album_streams');
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
					sonnb_XenGallery_Model_Album::$contentType,
					$this->get('album_id'),
					$deleteArray
				);
			}

			$streams = $this->_getStreamModel()->publishStream(
				sonnb_XenGallery_Model_Album::$contentType,
				$this->get('album_id'),
				$this->_streams
			);

			if ($streams === -1)
			{
				$this->error(new XenForo_Phrase(
					'sonnb_xengallery_you_are_allowed_to_add_x_streams_to_a_single_photo_album',
					array(
						'limit' => $this->getModelFromCache('sonnb_XenGallery_Model_Gallery')->getMaximumStreamCount()
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
			'sonnb_xengallery_album',
			array('album_streams' => $existingStreams),
			'album_id = '.$this->get('album_id')
		);
	}

	protected function _verifyPrivacy(&$privacy)
	{
		if ($privacy === null)
		{
			$xenOptions = XenForo_Application::getOptions();

			$privacy = array(
				'allow_view' => $xenOptions->sonnbXG_albumPrivacyView,
				'allow_view_data' => array(),
				'allow_comment' => $xenOptions->sonnbXG_albumPrivacyComment,
				'allow_comment_data' => array(),
				'allow_download' => $xenOptions->sonnbXG_albumPrivacyDownload,
				'allow_download_data' => array(),
				'allow_add_photo' => $xenOptions->sonnbXG_albumPrivacyAdd,
				'allow_add_photo_data' => array(),
				'allow_add_video' => $xenOptions->sonnbXG_albumPrivacyAddVideo,
				'allow_add_video_data' => array()
			);

			$visitor = XenForo_Visitor::getInstance();
			if (!empty($visitor['xengallery']))
			{
				$privacy = array(
					'allow_view' => $visitor['xengallery']['album_allow_view'],
					'allow_view_data' => array(),
					'allow_comment' => $visitor['xengallery']['album_allow_comment'],
					'allow_comment_data' => array(),
					'allow_download' => $visitor['xengallery']['album_allow_download'],
					'allow_download_data' => array(),
					'allow_add_photo' => $visitor['xengallery']['album_allow_add_photo'],
					'allow_add_photo_data' => array(),
					'allow_add_video' => $visitor['xengallery']['album_allow_add_video'],
					'allow_add_video_data' => array()
				);
			}

			return true;
		}

		return XenForo_DataWriter_Helper_Denormalization::verifySerialized($privacy, $this, 'album_privacy');
	}

	protected function _processCategoryChanges()
	{
		$albumModel = $this->_getAlbumModel();
		if ($this->isInsert() && $this->get('category_id'))
		{
			if ($this->get('album_state') === 'visible')
			{
				$albumModel->modifyAlbumCount($this->get('category_id'), 1);
			}
		}
		elseif ($this->isUpdate() && $this->isChanged('album_state')
			&& !$this->isChanged('category_id') && $this->get('category_id'))
		{
			if ($this->get('album_state') === 'visible')
			{
				$albumModel->modifyAlbumCount($this->get('category_id'), 1);
			}
			elseif ($this->isUpdate() && $this->getExisting('album_state') === 'visible')
			{
				$albumModel->modifyAlbumCount($this->get('category_id'), -1);
			}
		}
		elseif ($this->isUpdate() && $this->isChanged('category_id')
			&& $this->get('album_state') === 'visible')
		{
			if ($this->getExisting('category_id') && $this->getExisting('album_state') === 'visible')
			{
				$albumModel->modifyAlbumCount($this->getExisting('category_id'), -1);
			}

			if ($this->get('category_id'))
			{
				$albumModel->modifyAlbumCount($this->get('category_id'), 1);
			}
		}
	}

	protected function _processCollectionChanges()
	{
		$albumModel = $this->_getAlbumModel();
		if ($this->isInsert() && $this->get('collection_id'))
		{
			$albumModel->modifyCollectionCount($this->get('collection_id'), 1, $this->getMergedData());
		}
		elseif ($this->isUpdate() && $this->isChanged('collection_id'))
		{
			if ($this->getExisting('collection_id'))
			{
				$albumModel->modifyCollectionCount($this->getExisting('collection_id'), -1, $this->getMergedData());
			}

			if ($this->get('collection_id'))
			{
				$albumModel->modifyCollectionCount($this->get('collection_id'), 1, $this->getMergedData());
			}
		}
	}

	protected function _triggerNewPhotosAdded($count, $photoIds)
	{
		$visitor = XenForo_Visitor::getInstance();
		$contentType = sonnb_XenGallery_Model_Album::$contentType;
		$xfContentType = sonnb_XenGallery_Model_Album::$xfContentType;

		/*
		 * Massive sending alert to watched users.
		 */
		$this->_getWatchModel()->sendAlertToWatchedUsersByContentId(
			$contentType,
			$this->get('album_id'),
			$visitor,
			'add_photo',
			array(
				'count' => $count,
				'photoIds' => $photoIds
			)
		);

		/*
		 * Send alert to owner.
		 */
		if ($visitor['user_id'] != $this->get('user_id'))
		{
			$userModel = $this->_getUserModel();
			$contentUser = $userModel->getUserById($this->get('user_id'), array(
				'join' => XenForo_Model_User::FETCH_USER_OPTION | XenForo_Model_User::FETCH_USER_PROFILE
			));

			if ($contentUser)
			{
				if (!$userModel->isUserIgnored($contentUser, $visitor['user_id'])
					&& XenForo_Model_Alert::userReceivesAlert($contentUser, $xfContentType, 'add_photo')
				)
				{
					XenForo_Model_Alert::alert(
						$this->get('user_id'),
						$visitor['user_id'],
						$visitor['username'],
						$xfContentType,
						$this->get('album_id'),
						'add_photo',
						array(
							'count' => $count,
							'photoIds' => $photoIds
						)
					);
				}
			}
		}

		/*
		 * Publish to newsfeed.
		 */
		$this->_getNewsFeedModel()->publish(
			$visitor['user_id'],
			$visitor['username'],
			$xfContentType,
			$this->get('album_id'),
			'add_photo',
			array(
				'count' => $count,
				'photoIds' => $photoIds
			)
		);
	}

	protected function _triggerNewVideosAdded($count, $videoIds)
	{
		$visitor = XenForo_Visitor::getInstance();
		$contentType = sonnb_XenGallery_Model_Album::$contentType;
		$xfContentType = sonnb_XenGallery_Model_Album::$xfContentType;

		/*
		 * Massive sending alert to watched users.
		 */
		$this->_getWatchModel()->sendAlertToWatchedUsersByContentId(
			$contentType,
			$this->get('album_id'),
			$visitor,
			'add_video',
			array(
				'count' => $count,
				'videoIds' => $videoIds
			)
		);

		/*
		 * Send alert to owner.
		 */
		if ($visitor['user_id'] != $this->get('user_id'))
		{
			$userModel = $this->_getUserModel();
			$contentUser = $userModel->getUserById($this->get('user_id'), array(
				'join' => XenForo_Model_User::FETCH_USER_OPTION | XenForo_Model_User::FETCH_USER_PROFILE
			));

			if ($contentUser)
			{
				if (!$userModel->isUserIgnored($contentUser, $visitor['user_id'])
					&& XenForo_Model_Alert::userReceivesAlert($contentUser, $xfContentType, 'add_video')
				)
				{
					XenForo_Model_Alert::alert(
						$this->get('user_id'),
						$visitor['user_id'],
						$visitor['username'],
						$xfContentType,
						$this->get('album_id'),
						'add_video',
						array(
							'count' => $count,
							'videoIds' => $videoIds
						)
					);
				}
			}
		}

		/*
		 * Publish to newsfeed.
		 */
		$this->_getNewsFeedModel()->publish(
			$visitor['user_id'],
			$visitor['username'],
			$xfContentType,
			$this->get('album_id'),
			'add_video',
			array(
				'count' => $count,
				'videoIds' => $videoIds
			)
		);
	}

	public function insertCustomPrivacy($privacy)
	{
		$albumPrivacy = $this->get('album_privacy');
		$albumPrivacy = @unserialize($albumPrivacy);

		if ($albumPrivacy['allow_view'] === 'custom')
		{
			$albumPrivacy['allow_view_data'] = $this->_getPrivacyUsers($privacy['allow_view_username']);
		}

		if ($albumPrivacy['allow_comment'] === 'custom')
		{
			$albumPrivacy['allow_comment_data'] = $this->_getPrivacyUsers($privacy['allow_comment_username']);
		}

		if ($albumPrivacy['allow_download'] === 'custom')
		{
			$albumPrivacy['allow_download_data'] = $this->_getPrivacyUsers($privacy['allow_download_username']);
		}

		if ($albumPrivacy['allow_add_photo'] === 'custom')
		{
			$albumPrivacy['allow_add_photo_data']= $this->_getPrivacyUsers($privacy['allow_add_photo_username']);
		}

		if ($albumPrivacy['allow_add_video'] === 'custom')
		{
			$albumPrivacy['allow_add_video_data']= $this->_getPrivacyUsers($privacy['allow_add_video_username']);
		}

		$this->set('album_privacy', $albumPrivacy);
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

	public function insertContents(array $data, array $deletes = array(), array $photoPrivacy = array(), array $videoPrivacy = array())
	{
		$hash = $data['content_data_hash'];

		$videos = $photos = array();
		foreach ($data['content_title'] as $dataId => $_data)
		{
			switch($data['content_type'][$dataId])
			{
				case sonnb_XenGallery_Model_Photo::$contentType:
					$photos['content_title'][$dataId] = $_data;
					$photos['content_description'][$dataId] = isset($data['content_description'][$dataId]) ? $data['content_description'][$dataId] : '';
					$photos['content_people'][$dataId] = isset($data['content_people'][$dataId]) ? $data['content_people'][$dataId] : '';

					$photos['content_location'][$dataId] = isset($data['content_location'][$dataId]) ? $data['content_location'][$dataId] : '';
					$photos['location_lat'][$dataId] = isset($data['location_lat'][$dataId]) ? $data['location_lat'][$dataId] : '';
					$photos['location_lng'][$dataId] = isset($data['location_lng'][$dataId]) ? $data['location_lng'][$dataId] : '';
					break;
				case sonnb_XenGallery_Model_Video::$contentType:
					$videos['content_title'][$dataId] = $_data;
					$videos['content_description'][$dataId] = isset($data['content_description'][$dataId]) ? $data['content_description'][$dataId] : '';
					$videos['content_people'][$dataId] = isset($data['content_people'][$dataId]) ? $data['content_people'][$dataId] : '';

					$videos['content_location'][$dataId] = isset($data['content_location'][$dataId]) ? $data['content_location'][$dataId] : '';
					$videos['location_lat'][$dataId] = isset($data['location_lat'][$dataId]) ? $data['location_lat'][$dataId] : '';
					$videos['location_lng'][$dataId] = isset($data['location_lng'][$dataId]) ? $data['location_lng'][$dataId] : '';

					$videos['video_key'][$dataId] = isset($data['video_key'][$dataId]) ? $data['video_key'][$dataId] : '';
					$videos['video_type'][$dataId] = isset($data['video_type'][$dataId]) ? $data['video_type'][$dataId] : '';
					break;
			}
		}

		if (!empty($photos))
		{
			$this->insertPhotos($photos, $hash, $deletes, $photoPrivacy);
		}
		if (!empty($videos))
		{
			$this->insertVideos($videos, $hash, $deletes, $videoPrivacy);
		}
	}

	public function insertPhotos($photoDataInsert, $hash, array $deletes = array(), array $privacy = array())
	{
		$conditions = array(
			'temp_hash' => $hash,
			'content_type' => sonnb_XenGallery_Model_Photo::$contentType,
			'unassociated' => 1
		);
		$fetchOptions = array('order' => 'recent');

		$photoModel = $this->_getPhotoModel();
		$photoDataModel = $this->_getPhotoDataModel();
		$xenOptions = XenForo_Application::getOptions();
		$photoData = $photoDataModel->getData($conditions, $fetchOptions);

		$coverContentDataId = $this->getExtraData(self::COVER_CONTENT_DATA_ID);
		if ($photoData)
		{
			foreach ($photoData as $photoDataId => $_photoData)
			{
				if (isset($deletes[$photoDataId]))
				{
					unset($photoData[$photoDataId]);

					unset($photoDataInsert['content_title'][$photoDataId]);
					unset($photoDataInsert['content_description'][$photoDataId]);
					unset($photoDataInsert['content_location'][$photoDataId]);
					unset($photoDataInsert['location_lat'][$photoDataId]);
					unset($photoDataInsert['location_lng'][$photoDataId]);
				}
			}

			$visitor = XenForo_Visitor::getInstance();
			$photoData = array_reverse($photoData, true);

			$conditions = array('content_type' => sonnb_XenGallery_Model_Photo::$contentType);
			$maximumPhotoCount = $photoModel->getPhotoCountLimit();
			$existingPhotoCount = $photoModel->countContentsByAlbumId($this->get('album_id'), $conditions);
			if ($maximumPhotoCount && ($existingPhotoCount + count($photoData) > $maximumPhotoCount))
			{
				$this->error(new XenForo_Phrase('sonnb_xengallery_this_album_reached_photo_limit', array('limit' => $maximumPhotoCount)));
				return false;
			}

			$currentPosition = $this->get('content_count');

			$addedPhotoIds = array();

			foreach ($photoData as $photoDataId => $_photoData)
			{
				if (isset($deletes[$photoDataId]))
				{
					continue;
				}

				$photoDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Photo', XenForo_DataWriter::ERROR_ARRAY);
				$photoDw->bulkSet(array(
					'user_id' => $visitor['user_id'],
					'username' => $visitor['username'],
					'album_id' => $this->get('album_id'),
					'content_data_id' => $_photoData['content_data_id'],
					'title' => isset($photoDataInsert['content_title'][$photoDataId]) ? $photoDataInsert['content_title'][$photoDataId] : '',
					'description' => isset($photoDataInsert['content_description'][$photoDataId]) ? $photoDataInsert['content_description'][$photoDataId] : '',
					'content_location' => isset($photoDataInsert['content_location'][$photoDataId]) ? $photoDataInsert['content_location'][$photoDataId] : '',
					'position' => $currentPosition
				));
				$currentPosition++;

				if ($xenOptions->sonnbXG_nudityFilter)
				{
					$file = $photoDataModel->getContentDataFile($_photoData);
					if ($xenOptions->sonnbXG_disableOriginal)
					{
						$file = $photoDataModel->getContentDataLargeThumbnailFile($_photoData);
					}

					$score = sonnb_XenGallery_Model_PhotoFilter::getInstance()->getScore($file);

					if ($score > $xenOptions->sonnbXG_nudityScore)
					{
						switch ($xenOptions->sonnbXG_nudityAction)
						{
							case 'refuse':
								$this->error(new XenForo_Phrase('sonnb_xengallery_your_photo_might_contain_nudity_content'));
								continue;
								break;
							case 'moderated':
								$photoDw->set('content_state', 'moderated');
								break;
							default:
								break;
						}
					}
				}

				$photoDw->set('photo_exif', $photoModel->getPhotoExif($_photoData));

				if (isset($privacy['photo_allow_view']))
				{
					$photoDw->set('content_privacy', array(
						'allow_view' => $privacy['photo_allow_view'],
						'allow_comment' => $privacy['photo_allow_comment']
					));
					$photoDw->insertCustomPrivacy(array(
						'allow_view_username' => $privacy['photo_allow_view_username'],
						'allow_comment_username' => $privacy['photo_allow_comment_username']
					));
				}

				$photoDw->preSave();

				if ($errors = $photoDw->getErrors())
				{
					$this->mergeErrors($errors);
				}
				else
				{
					$photoDw->save();

					$photo = $photoDw->getMergedData();

					if ($photo['content_state'] === 'visible')
					{
						$addedPhotoIds[] = $photo['content_id'];
					}

					if (!empty($coverContentDataId) && $coverContentDataId == $photoDataId)
					{
						$this->set('cover_content_id', $photo['content_id'], '', array('setAfterPreSave' => true));
						$this->_db->update(
							'sonnb_xengallery_album',
							array(
								'cover_content_id' => $photo['content_id']
							),
							array(
								'album_id = ?' => $this->get('album_id')
							)
						);
					}

					$locationLatlng = array(
						'location_name' => isset($photoDataInsert['content_location'][$photoDataId]) ? $photoDataInsert['content_location'][$photoDataId] : '',
						'location_lat' => isset($photoDataInsert['location_lat'][$photoDataId]) ? $photoDataInsert['location_lat'][$photoDataId] : 0,
						'location_lng' => isset($photoDataInsert['location_lng'][$photoDataId]) ? $photoDataInsert['location_lng'][$photoDataId] : 0
					);
					$this->_getLocationModel()->insertLocation(
						sonnb_XenGallery_Model_Photo::$contentType,
						$photo['content_id'],
						$locationLatlng
					);

					unset($photoDataInsert['content_title'][$photoDataId]);
					unset($photoDataInsert['content_description'][$photoDataId]);
					unset($photoDataInsert['content_location'][$photoDataId]);
					unset($photoDataInsert['location_lat'][$photoDataId]);
					unset($photoDataInsert['location_lng'][$photoDataId]);

					if (!empty($photoDataInsert['content_people'][$photoDataId]))
					{
						/* @var $tagModel sonnb_XenGallery_Model_Tag */
						$tagModel = $this->getModelFromCache('sonnb_XenGallery_Model_Tag');
						$tagModel->addTagUsers(
							$photoDataInsert['content_people'][$photoDataId],
							sonnb_XenGallery_Model_Photo::$contentType,
							$photo['content_id'],
							true
						);
					}

					if (!$this->get('cover_content_id'))
					{
						$this->set('cover_content_id', $photo['content_id'], '', array('setAfterPreSave' => true));
						$this->set('cover_content_type', $photo['content_type'], '', array('setAfterPreSave' => true));
					}

					$this->_db->update(
						'sonnb_xengallery_content_data',
						array(
							'temp_hash' => '',
							'unassociated' => 0,
						),
						array(
						     'content_data_id = ?' => $photoDataId
					    )
					);
				}
			}

			$this->set('photo_count', $this->get('photo_count') + count($addedPhotoIds), '', array('setAfterPreSave' => true));
			$this->set('content_count', $this->get('content_count') + count($addedPhotoIds), '', array('setAfterPreSave' => true));

			if ($this->isUpdate() && count($addedPhotoIds) > 0)
			{
				$this->_triggerNewPhotosAdded(count($addedPhotoIds), $addedPhotoIds);
			}
		}

		foreach ($photoDataInsert['content_description'] as $photoDataId => $description)
		{
			$photoDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Photo', XenForo_DataWriter::ERROR_ARRAY);
			$photoDw->setExistingData(array('content_data_id' => $photoDataId));

			if ($photoDw->hasErrors())
			{
				continue;
			}

			$_photo = $photoDw->getMergedData();
			if (!$photoModel->canEditContent($_photo))
			{
				continue;
			}

			if (isset($deletes[$photoDataId]))
			{
				if (!$photoModel->canDeleteContent($_photo, 'soft'))
				{
					continue;
				}

				if ($photoModel->canDeleteContent($_photo, 'hard'))
				{
					$photoDw->delete();
					continue;
				}
				else
				{
					$photoDw->set('content_state', 'deleted');
				}
			}

			$photoDw->bulkSet(array(
				'title' => isset($photoDataInsert['content_title'][$photoDataId]) ? $photoDataInsert['content_title'][$photoDataId] : '',
				'description' => isset($photoDataInsert['content_description'][$photoDataId]) ? $photoDataInsert['content_description'][$photoDataId] : '',
				'content_location' => isset($photoDataInsert['content_location'][$photoDataId]) ? $photoDataInsert['content_location'][$photoDataId] : '',
			));
			$photoDw->preSave();

			if ($errors = $photoDw->getErrors())
			{
				$this->mergeErrors($errors);
			}
			else
			{
				$photoDw->save();

				$this->_getTagModel()->addTagUsers(
					$photoDataInsert['content_people'][$photoDataId],
					sonnb_XenGallery_Model_Photo::$contentType,
					$photoDw->get('content_id'),
					true
				);

				if ($photoDw->isChanged('content_location'))
				{
					$locationLatlng = array(
						'location_name' => isset($photoDataInsert['content_location'][$photoDataId]) ? $photoDataInsert['content_location'][$photoDataId] : '',
						'location_lat' => isset($photoDataInsert['location_lat'][$photoDataId]) ? $photoDataInsert['location_lat'][$photoDataId] : '',
						'location_lng' => isset($photoDataInsert['location_lng'][$photoDataId]) ? $photoDataInsert['location_lng'][$photoDataId] : ''
					);

					$this->_getLocationModel()->insertLocation(sonnb_XenGallery_Model_Photo::$contentType, $photoDw->get('content_id'), $locationLatlng);
				}
			}
		}

	}

	public function insertVideos($videoDataInsert, $hash, array $deletes = array(), array $privacy = array())
	{
		$conditions = array(
			'temp_hash' => $hash,
			'content_type' => sonnb_XenGallery_Model_Video::$contentType,
			'unassociated' => 1
		);
		$fetchOptions = array('order' => 'recent');

		$videoModel = $this->_getVideoModel();
		$videoDataModel = $this->_getVideoDataModel();
		$videoData = $videoDataModel->getData($conditions, $fetchOptions);

		if ($videoData)
		{
			foreach ($videoData as $videoDataId => $_videoData)
			{
				if (isset($deletes[$videoDataId]))
				{
					unset($videoData[$videoDataId]);

					unset($videoDataInsert['content_description'][$videoDataId]);
					unset($videoDataInsert['content_title'][$videoDataId]);
					unset($videoDataInsert['content_location'][$videoDataId]);
					unset($videoDataInsert['location_lat'][$videoDataId]);
					unset($videoDataInsert['location_lng'][$videoDataId]);

					unset($videoDataInsert['video_key'][$videoDataId]);
					unset($videoDataInsert['video_type'][$videoDataId]);
				}
			}

			$visitor = XenForo_Visitor::getInstance();
			$videoData = array_reverse($videoData, true);

			$conditions = array('content_type' => sonnb_XenGallery_Model_Video::$contentType);
			$maximumVideoCount = $videoModel->getVideoCountLimit();
			$existingVideoCount = $videoModel->countContentsByAlbumId($this->get('album_id'), $conditions);
			if ($maximumVideoCount && ($existingVideoCount + count($videoData) > $maximumVideoCount))
			{
				$this->error(new XenForo_Phrase('sonnb_xengallery_this_album_reached_video_limit', array('limit' => $maximumVideoCount)));
				return false;
			}

			$currentPosition = $this->get('content_count');

			$coverContentDataId = $this->getExtraData(self::COVER_CONTENT_DATA_ID);
			$addedVideoIds = array();
			foreach ($videoData as $videoDataId => $_videoData)
			{
				if (isset($deletes[$videoDataId]))
				{
					continue;
				}

				$videoDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Video', XenForo_DataWriter::ERROR_ARRAY);
				$videoDw->bulkSet(array(
					'user_id' => $visitor['user_id'],
					'username' => $visitor['username'],
					'album_id' => $this->get('album_id'),
					'content_data_id' => $_videoData['content_data_id'],

					'video_key' => isset($videoDataInsert['video_key'][$videoDataId]) ? $videoDataInsert['video_key'][$videoDataId] : '',
					'video_type' => isset($videoDataInsert['video_type'][$videoDataId]) ? $videoDataInsert['video_type'][$videoDataId] : '',

					'title' => isset($videoDataInsert['content_title'][$videoDataId]) ? $videoDataInsert['content_title'][$videoDataId] : '',
					'description' => isset($videoDataInsert['content_description'][$videoDataId]) ? $videoDataInsert['content_description'][$videoDataId] : '',
					'content_location' => isset($videoDataInsert['content_location'][$videoDataId]) ? $videoDataInsert['content_location'][$videoDataId] : '',
					'position' => $currentPosition
				));
				$currentPosition++;

				if (isset($privacy['video_allow_view']))
				{
					$videoDw->set('content_privacy', array(
						'allow_view' => $privacy['video_allow_view'],
						'allow_comment' => $privacy['video_allow_comment']
					));
					$videoDw->insertCustomPrivacy(array(
						'allow_view_username' => $privacy['video_allow_view_username'],
						'allow_comment_username' => $privacy['video_allow_comment_username']
					));
				}

				$videoDw->preSave();

				if ($errors = $videoDw->getErrors())
				{
					$this->mergeErrors($errors);
				}
				else
				{
					$videoDw->save();

					$video = $videoDw->getMergedData();

					if ($video['content_state'] === 'visible')
					{
						$addedVideoIds[] = $video['content_id'];
					}

					if (!empty($coverContentDataId) && $coverContentDataId == $videoDataId)
					{
						$this->set('cover_content_id', $video['content_id'], '', array('setAfterPreSave' => true));
						$this->_db->update(
							'sonnb_xengallery_album',
							array(
								'cover_content_id' => $video['content_id']
							),
							array(
								'album_id = ?' => $this->get('album_id')
							)
						);
					}

					$locationLatlng = array(
						'location_name' => isset($videoDataInsert['content_location'][$videoDataId]) ? $videoDataInsert['content_location'][$videoDataId] : '',
						'location_lat' => isset($videoDataInsert['location_lat'][$videoDataId]) ? $videoDataInsert['location_lat'][$videoDataId] : 0,
						'location_lng' => isset($videoDataInsert['location_lng'][$videoDataId]) ? $videoDataInsert['location_lng'][$videoDataId] : 0
					);
					$this->_getLocationModel()->insertLocation(
						sonnb_XenGallery_Model_Video::$contentType,
						$video['content_id'],
						$locationLatlng
					);

					unset($videoDataInsert['content_description'][$videoDataId]);
					unset($videoDataInsert['content_title'][$videoDataId]);
					unset($videoDataInsert['content_location'][$videoDataId]);
					unset($videoDataInsert['location_lat'][$videoDataId]);
					unset($videoDataInsert['location_lng'][$videoDataId]);

					unset($videoDataInsert['video_key'][$videoDataId]);
					unset($videoDataInsert['video_type'][$videoDataId]);

					if (!empty($videoDataInsert['content_people'][$videoDataId]))
					{
						/* @var $tagModel sonnb_XenGallery_Model_Tag */
						$tagModel = $this->getModelFromCache('sonnb_XenGallery_Model_Tag');
						$tagModel->addTagUsers(
							$videoDataInsert['content_people'][$videoDataId],
							sonnb_XenGallery_Model_Video::$contentType,
							$video['content_id']
						);
					}

					if (!$this->get('cover_content_id'))
					{
						$this->set('cover_content_id', $video['content_id'], '', array('setAfterPreSave' => true));
						$this->set('cover_content_type', $video['content_type'], '', array('setAfterPreSave' => true));
					}

					$this->_db->update(
						'sonnb_xengallery_content_data', array(
							'temp_hash' => '',
							'unassociated' => 0
						),
						array(
						     'content_data_id = ?' => $videoDataId
						)
					);
				}
			}

			$this->set('video_count', $this->get('video_count') + count($addedVideoIds), '', array('setAfterPreSave' => true));
			$this->set('content_count', $this->get('content_count') + count($addedVideoIds), '', array('setAfterPreSave' => true));

			if ($this->isUpdate() && count($addedVideoIds) > 0)
			{
				$this->_triggerNewVideosAdded(count($addedVideoIds), $addedVideoIds);
			}
		}

		foreach ($videoDataInsert['content_description'] as $videoDataId => $description)
		{
			$videoDw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Video', XenForo_DataWriter::ERROR_ARRAY);
			$videoDw->setExistingData(array('content_data_id' => $videoDataId));

			if ($videoDw->hasErrors())
			{
				continue;
			}

			$_video = $videoDw->getMergedData();
			if (!$videoModel->canEditContent($_video))
			{
				continue;
			}

			if (isset($deletes[$videoDataId]))
			{
				if (!$videoModel->canDeleteContent($_video, 'soft'))
				{
					continue;
				}

				if ($videoModel->canDeleteContent($_video, 'hard'))
				{
					$videoDw->delete();
					continue;
				}
				else
				{
					$videoDw->set('content_state', 'deleted');
				}
			}

			$videoDw->bulkSet(array(
				'title' => isset($videoDataInsert['content_title'][$videoDataId]) ? $videoDataInsert['content_title'][$videoDataId] : '',
				'description' => isset($videoDataInsert['content_description'][$videoDataId]) ? $videoDataInsert['content_description'][$videoDataId] : '',
				'content_location' => isset($videoDataInsert['content_location'][$videoDataId]) ? $videoDataInsert['content_location'][$videoDataId] : '',

				'video_key' => isset($videoDataInsert['video_key'][$videoDataId]) ? $videoDataInsert['video_key'][$videoDataId] : '',
				'video_type' => isset($videoDataInsert['video_type'][$videoDataId]) ? $videoDataInsert['video_type'][$videoDataId] : '',
			));

			if ($errors = $videoDw->getErrors())
			{
				$this->mergeErrors($errors);
			}
			else
			{
				$videoDw->save();

				$this->_getTagModel()->addTagUsers(
					$videoDataInsert['content_people'][$videoDataId],
					sonnb_XenGallery_Model_Video::$contentType,
					$videoDw->get('content_id'),
					true
				);

				if ($videoDw->isChanged('content_location'))
				{
					$locationLatlng = array(
						'location_name' => isset($videoDataInsert['content_location'][$videoDataId]) ? $videoDataInsert['content_location'][$videoDataId] : '',
						'location_lat' => isset($videoDataInsert['location_lat'][$videoDataId]) ? $videoDataInsert['location_lat'][$videoDataId] : '',
						'location_lng' => isset($videoDataInsert['location_lng'][$videoDataId]) ? $videoDataInsert['location_lng'][$videoDataId] : ''
					);

					$this->_getLocationModel()->insertLocation(sonnb_XenGallery_Model_Video::$contentType, $videoDw->get('content_id'), $locationLatlng);
				}
			}
		}
	}

	public function insertNewContent($contentType, $contentId, $contentDate)
	{
		switch ($contentType)
		{
			case sonnb_XenGallery_Model_Photo::$contentType:
				$this->insertNewPhoto($contentId, $contentDate);
				break;
			case sonnb_XenGallery_Model_Video::$contentType:
				$this->insertNewVideo($contentId, $contentDate);
				break;
		}
	}

	public function insertNewPhoto($photoId, $photoDate)
	{
		$this->set('photo_count', $this->get('photo_count') + 1);
		$this->set('content_count', $this->get('content_count') + 1);

		$latest = $this->get('latest_photo_ids');
		$ids = ($latest ? explode(',', $latest) : array());
		$ids[] = $photoId;

		if (count($ids) > 5)
		{
			$ids = array_slice($ids, -5);
		}
		$this->set('latest_photo_ids', implode(',', $ids));

		$latest = $this->get('latest_content_ids');
		$ids = ($latest ? explode(',', $latest) : array());
		$ids[] = $photoId;

		if (count($ids) > 5)
		{
			$ids = array_slice($ids, -5);
		}
		
		$this->set('latest_content_ids', implode(',', $ids));

		if (!$this->get('cover_content_id'))
		{
			$this->set('cover_content_id', $photoId);
			$this->set('cover_content_type', sonnb_XenGallery_Model_Photo::$contentType);
		}
	}

	public function insertNewVideo($videoId, $videoDate)
	{
		$this->set('video_count', $this->get('video_count') + 1);
		$this->set('content_count', $this->get('content_count') + 1);

		$latest = $this->get('latest_video_ids');
		$ids = ($latest ? explode(',', $latest) : array());
		$ids[] = $videoId;

		if (count($ids) > 5)
		{
			$ids = array_slice($ids, -5);
		}
		$this->set('latest_video_ids', implode(',', $ids));

		$latest = $this->get('latest_content_ids');
		$ids = ($latest ? explode(',', $latest) : array());
		$ids[] = $videoId;

		if (count($ids) > 5)
		{
			$ids = array_slice($ids, -5);
		}
		$this->set('latest_content_ids', implode(',', $ids));

		if (!$this->get('cover_content_id'))
		{
			$this->set('cover_content_id', $videoId);
			$this->set('cover_content_type', sonnb_XenGallery_Model_Video::$contentType);
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
			$existingTags = unserialize($existingTags);
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

	public function rebuildContentCounters()
	{
		$this->rebuildVideoCounters();
		$this->rebuildPhotoCounters();

		$db = $this->_db;
		$albumId = $this->get('album_id');

		$counts = $db->fetchOne("
			SELECT COUNT(*) AS content_count
			FROM sonnb_xengallery_content
			WHERE
				content_state = 'visible'
				AND album_id = ?
		", $albumId);

		if ($counts)
		{
			$contentIds = $db->fetchAssoc($db->limit("
				SELECT content_id, content_type
				FROM sonnb_xengallery_content
				WHERE
					content_state = 'visible'
					AND album_id = ?
				ORDER BY content_updated_date DESC
			", 5
			), $albumId);

			$contentIds = array_reverse($contentIds, true);
			$ids = array_keys($contentIds);

			if (!$this->get('cover_content_id'))
			{
				$firstContent = reset($contentIds);

				$this->set('cover_content_id', $firstContent['content_id']);
				$this->set('cover_content_type', $firstContent['content_type']);
			}
		}
		else
		{
			$ids = array();
			$this->set('cover_content_id', 0);
		}

		$this->set('content_count', $counts);
		$this->set('latest_content_ids', implode(',', $ids));

		return $counts;
	}

	public function rebuildVideoCounters()
	{
		$db = $this->_db;
		$albumId = $this->get('album_id');

		$counts = $db->fetchOne('
			SELECT COUNT(*) AS video_count
			FROM sonnb_xengallery_content
			WHERE
				content_state = \'visible\'
				AND content_type = \''. sonnb_XenGallery_Model_Video::$contentType .'\'
				AND album_id = ?
		', $albumId);

		if ($counts)
		{
			$ids = $db->fetchCol($db->limit(
				'
				SELECT content_id
				FROM sonnb_xengallery_content
				WHERE
					content_state = \'visible\'
					AND content_type = \''. sonnb_XenGallery_Model_Video::$contentType .'\'
					AND album_id = ?
				ORDER BY content_updated_date DESC
			', 5
			), $albumId);

			$ids = array_reverse($ids);

			if (!$this->get('cover_content_id'))
			{
				$this->set('cover_content_id', reset($ids));
				$this->set('cover_content_type', sonnb_XenGallery_Model_Video::$contentType);
			}
		}
		else
		{
			$ids = array();
			$this->set('cover_content_id', 0);
		}

		$this->set('video_count', $counts);
		$this->set('latest_video_ids', implode(',', $ids));

		return $counts;
	}

	public function rebuildPhotoCounters()
	{
		$db = $this->_db;
		$albumId = $this->get('album_id');

		$counts = $db->fetchOne("
			SELECT COUNT(*) AS photo_count
			FROM sonnb_xengallery_content
			WHERE
				content_state = 'visible'
				AND content_type = '". sonnb_XenGallery_Model_Photo::$contentType ."'
				AND album_id = ?
		", $albumId);

		if ($counts)
		{
			$ids = $db->fetchCol($db->limit("
				SELECT content_id
				FROM sonnb_xengallery_content
				WHERE
					content_state = 'visible'
					AND content_type = '". sonnb_XenGallery_Model_Photo::$contentType ."'
					AND album_id = ?
				ORDER BY content_updated_date DESC
			", 5
			), $albumId);

			$ids = array_reverse($ids);

			if (!$this->get('cover_content_id'))
			{
				$this->set('cover_content_id', reset($ids));
				$this->set('cover_content_type', sonnb_XenGallery_Model_Photo::$contentType);
			}
		}
		else
		{
			$ids = array();
			$this->set('cover_content_id', 0);
		}

		$this->set('photo_count', $counts);
		$this->set('latest_photo_ids', implode(',', $ids));

		return $counts;
	}

	public function rebuildPhotoPositions()
	{
		$this->rebuildContentPositions();
	}

	public function rebuildContentPositions()
	{
		$db = $this->_db;
		$albumId = $this->get('album_id');

		$contents = $db->fetchAssoc('
			SELECT *
			FROM sonnb_xengallery_content
			WHERE
				album_id = ?
			ORDER BY position ASC, content_date ASC
		', $albumId);

		if ($contents)
		{
			$position = 0;
			foreach ($contents as $contentId => $content)
			{
				$db->update(
					'sonnb_xengallery_content',
					array(
						'position' => $position
					),
					'content_id = '.$db->quote($contentId)
				);

				$position++;
			}
		}
	}

	protected function _updateDeletionLog($hardDelete = false)
	{
		if ($hardDelete
			|| ($this->isChanged('album_state') && $this->getExisting('album_state') === 'deleted')
		)
		{
			$this->getModelFromCache('XenForo_Model_DeletionLog')->removeDeletionLog(
				sonnb_XenGallery_Model_Album::$xfContentType, $this->get('album_id')
			);
		}

		if ($this->isChanged('album_state') && $this->get('album_state') === 'deleted')
		{
			$reason = $this->getExtraData(self::DATA_DELETE_REASON);
			$this->getModelFromCache('XenForo_Model_DeletionLog')->logDeletion(
				sonnb_XenGallery_Model_Album::$xfContentType, $this->get('album_id'), $reason
			);
		}
	}

	protected function _updateModerationQueue($hardDelete = false)
	{
		if (!$this->isChanged('album_state'))
		{
			return;
		}

		if ($hardDelete || $this->getExisting('album_state') === 'moderated')
		{
			$this->_getModerationQueueModel()->deleteFromModerationQueue(
				sonnb_XenGallery_Model_Album::$xfContentType, $this->get('album_id')
			);
		}
		elseif ($this->get('album_state') === 'moderated')
		{
			$this->_getModerationQueueModel()->insertIntoModerationQueue(
				sonnb_XenGallery_Model_Album::$xfContentType, $this->get('album_id'), $this->get('album_date')
			);
		}
	}

	protected function _publishToNewsFeed()
	{
		$this->_getNewsFeedModel()->publish(
			$this->get('user_id'),
			$this->get('username'),
			sonnb_XenGallery_Model_Album::$xfContentType,
			$this->get('album_id'),
			'insert'
		);
	}

	protected function _deleteFromNewsFeed()
	{
		$this->_getNewsFeedModel()->delete(
			sonnb_XenGallery_Model_Album::$xfContentType,
			$this->get('album_id')
		);
	}

	protected function _deleteFromAlert()
	{
		$this->_getAlertModel()->deleteAlerts(sonnb_XenGallery_Model_Album::$xfContentType, $this->get('photo_id'));
	}

	public function rebuildUserAlbumCount($userId = null)
	{
        if ($userId === null)
        {
            $userId = $this->get('user_id');
        }

		$albumCount = $this->_getAlbumModel()->countAlbumsByUserId($userId, array('album_state' => 'visible'));

		$this->_db->update('xf_user', array('sonnb_xengallery_album_count' => $albumCount), 'user_id = '.$userId);
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
		$db = $this->_db;
		$albumId = $this->get('album_id');

		$counts = $db->fetchOne("
			SELECT COUNT(*)
			FROM sonnb_xengallery_comment
			WHERE 
				content_type = '".sonnb_XenGallery_Model_Album::$contentType."'
				AND content_id = ?
		", $albumId);

		if ($counts)
		{
			$ids = $db->fetchCol($db->limit(
				"
				SELECT comment_id
				FROM sonnb_xengallery_comment
				WHERE
					comment_state = 'visible'
					AND content_type = '".sonnb_XenGallery_Model_Album::$contentType."'
						AND content_id = ?
					ORDER BY comment_date DESC
				", 5
			), $albumId);

			$ids = array_reverse($ids);
		}
		else
		{
			$ids = array();
		}

		$this->set('comment_count', $counts);
		$this->set('latest_comment_ids', implode(',', $ids));
	}

	public function rebuildCounters(array $options = array())
	{
		$options = array_merge(array(
			'contents' => true,
			'user' => false,
			'index' => true,
			'streams' => false,
			'tags' => false,

			'position' => true,
			'position_direction' => 'oldest'
		), $options);

		$albumId = $this->get('album_id');

		$this->rebuildContentCounters();
		$this->rebuildCommentCounters();

		/*
		 * TODO - FIX ME: This will remove current information???
		if ($this->get('album_location'))
		{
			$locationData = array(
				'location_name' => $this->get('album_location'),
				'location_lat' => '',
				'location_lng' => ''
			);

			$this->_getLocationModel()->insertLocation(
				sonnb_XenGallery_Model_Album::$contentType,
				$albumId,
				$locationData
			);
		}
		*/

		if ($options['position'])
		{
			$contentsInAlbums = $this->_getContentModel()->getContentIdsByAlbumId(
				$albumId,
				array(
					'content_state' => 'visible'
				),
				array(
					'order' => 'position_date'
				)
			);

			if ($contentsInAlbums)
			{
				$position = 0;
				//TODO: Optimize
				foreach ($contentsInAlbums as $contentId)
				{
					$this->_db->update(
						'sonnb_xengallery_content',
						array(
							'position' => $position
						),
						array('content_id = ?' => $contentId)
					);
					$position++;
				}
			}
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
	}

	protected function _logIpExtend($action)
	{
        $this->_logIp(sonnb_XenGallery_Model_Album::$xfContentType, $this->get('album_id'), $action);
	}

	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'album_id'))
		{
			return false;
		}

		if (!$album = $this->_getAlbumModel()->getAlbumById($id))
		{
			return false;
		}

		return $this->getTablesDataFromArray($album);
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'album_id = ' . $this->_db->quote($this->getExisting('album_id'));
	}
}