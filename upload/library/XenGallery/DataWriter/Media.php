<?php
 
class XenGallery_DataWriter_Media extends XenForo_DataWriter
{
	/**
	* Holds the reason for soft deletion.
	*
	* @var string
	*/
	const DATA_DELETE_REASON = 'deleteReason';

	/**
	 * Option that controls the maximum number of characters that are allowed in
	 * a media title.
	 *
	 * @var string
	 */
	const OPTION_MAX_TITLE_LENGTH = 'maxTitleLength';

	/**
	 * Option that controls the maximum number of characters that are allowed in
	 * a media description.
	 *
	 * @var string
	 */
	const OPTION_MAX_DESCRIPTION_LENGTH = 'maxDescriptionLength';

	/**
	* Title of the phrase that will be created when a call to set the
	* existing data fails (when the data doesn't exist).
	*
	* @var string
	*/
	protected $_existingDataErrorPhrase = 'xengallery_requested_media_not_found';

	/**
	 * The custom fields to be updated. Use setCustomFields to manage this.
	 *
	 * @var array
	 */
	protected $_updateCustomFields = null;

	/**
	 * Stores the file size of the deleted media
	 *
	 * @var int
	 */
	protected $_deletedFileSize = 0;

	/**
	 * Stores the original album ID for updating the media cache.
	 *
	 * @var int
	 */
	protected $_originalAlbumId = 0;
	protected $_albumCountUpdated = false;

	/**
	 * Stores the original category ID for updating the media cache.
	 *
	 * @var int
	 */
	protected $_originalCategoryId = 0;
	protected $_categoryCountUpdated = false;

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xengallery_media' => array(
				'media_id' => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'media_title' => array('type' => self::TYPE_STRING),
				'media_description' => array('type' => self::TYPE_STRING, 'default' => ''),
				'media_date' => array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'last_edit_date' => array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'last_comment_date' => array('type' => self::TYPE_UINT, 'default' => 0),				
				'media_type' => array('type' => self::TYPE_STRING, 'default' => 'image_upload',
					'allowedValues' => array('image_upload', 'video_upload', 'video_embed')
				),
				'media_tag' => array('type' => self::TYPE_STRING),
				'media_embed_url' => array('type' => self::TYPE_STRING),                
				'media_state' => array('type' => self::TYPE_STRING, 'default' => 'visible',
					'allowedValues' => array('visible', 'moderated', 'deleted')
				),
				'album_id' => array('type' => self::TYPE_UINT, 'default' => 0),
				'media_privacy' => array('type' => self::TYPE_STRING, 'default' => 'public',
					'allowedValues' => array('public', 'private', 'shared', 'members', 'followed', 'category')
				),
				'category_id' => array('type' => self::TYPE_UINT, 'default' => 0),
				'attachment_id' => array('type' => self::TYPE_UINT, 'default' => 0),
				'user_id' => array('type' => self::TYPE_UINT, 'default' => 0),
				'username' => array('type' => self::TYPE_STRING, 'maxLength' => 50),
				'ip_id' => array('type' => self::TYPE_UINT, 'default' => 0),
				'likes' => array('type' => self::TYPE_UINT),
				'like_users' => array('type' => self::TYPE_SERIALIZED),
				'comment_count' => array('type' => self::TYPE_UINT),
				'media_view_count' => array('type' => self::TYPE_UINT, 'default' => 0),
				'rating_count' => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'rating_sum' => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'rating_avg' => array('type' => self::TYPE_FLOAT, 'default' => 0),
				'rating_weighted' => array('type' => self::TYPE_FLOAT, 'default' => 0),
				'watermark_id' => array('type' => self::TYPE_UINT, 'default' => 0),
				'custom_media_fields' => array('type' => self::TYPE_SERIALIZED, 'default' => ''),
				'media_exif_data_cache' => array('type' => self::TYPE_SERIALIZED, 'default' => ''),
				'media_exif_data_cache_full' => array('type' => self::TYPE_JSON, 'default' => ''),
				'warning_id' => array('type' => self::TYPE_UINT, 'default' => 0),
				'warning_message' => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 255),
				'position' => array('type' => self::TYPE_UINT, 'default' => 0),
				'imported' => array('type' => self::TYPE_UINT, 'default' => 0),
				'thumbnail_date' => array('type' => self::TYPE_UINT, 'default' => 0),
				'tags' => array('type' => self::TYPE_SERIALIZED, 'default' => '')
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

		return array('xengallery_media' => $this->_getMediaModel()->getMediaById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'media_id = ' . $this->_db->quote($this->getExisting('media_id'));
	}

	public function setCustomFields(array $fieldValues, array $fieldsShown = null)
	{
		$fieldModel = $this->_getFieldModel();
		$fields = $fieldModel->getGalleryFields(array(), array('valueMediaId' => $this->get('media_id')));

		if (!is_array($fieldsShown))
		{
			$fieldsShown = array_keys($fields);
		}

		if ($this->get('media_id') && !$this->_importMode)
		{
			$existingValues = $fieldModel->getMediaFieldValues($this->get('media_id'));
		}
		else
		{
			$existingValues = array();
		}

		$finalValues = array();

		foreach ($fieldsShown AS $fieldId)
		{
			if (!isset($fields[$fieldId]))
			{
				continue;
			}

			$field = $fields[$fieldId];
			$multiChoice = ($field['field_type'] == 'checkbox' || $field['field_type'] == 'multiselect');

			if ($multiChoice)
			{
				// multi selection - array
				$value = array();
				if (isset($fieldValues[$fieldId]))
				{
					if (is_string($fieldValues[$fieldId]))
					{
						$value = array($fieldValues[$fieldId]);
					}
					else if (is_array($fieldValues[$fieldId]))
					{
						$value = $fieldValues[$fieldId];
					}
				}
			}
			else
			{
				// single selection - string
				if (isset($fieldValues[$fieldId]))
				{
					if (is_array($fieldValues[$fieldId]))
					{
						$value = count($fieldValues[$fieldId]) ? strval(reset($fieldValues[$fieldId])) : '';
					}
					else
					{
						$value = strval($fieldValues[$fieldId]);
					}
				}
				else
				{
					$value = '';
				}
			}

			$existingValue = (isset($existingValues[$fieldId]) ? $existingValues[$fieldId] : null);

			if (!$this->_importMode)
			{
				$valid = $fieldModel->verifyMediaFieldValue($field, $value, $error);
				if (!$valid)
				{
					$this->error($error, "custom_field_$fieldId");
					continue;
				}
			}

			if ($value !== $existingValue)
			{
				$finalValues[$fieldId] = $value;
			}
		}

		$this->_updateCustomFields = $this->_filterValidFields($finalValues + $existingValues, $fields);
		$this->set('custom_media_fields', $this->_updateCustomFields);
	}

	protected function _filterValidFields(array $values, array $fields)
	{
		$newValues = array();
		foreach ($fields AS $field)
		{
			if (isset($values[$field['field_id']]))
			{
				$newValues[$field['field_id']] = $values[$field['field_id']];
			}
		}

		return $newValues;
	}

	protected function _preSave()
	{
		if ($this->isChanged('media_title'))
		{
			$maxLength = $this->getOption(self::OPTION_MAX_TITLE_LENGTH);
			if ($maxLength && utf8_strlen($this->get('media_title')) > $maxLength)
			{
				$this->error(new XenForo_Phrase('xengallery_please_enter_a_title_with_no_more_than_x_characters', array('count' => $maxLength)), 'media_title');
			}

			if ($this->get('media_title') == '')
			{
				$this->error(new XenForo_Phrase('xengallery_please_enter_a_valid_media_title'));
			}
		}

		if ($this->isChanged('media_description'))
		{
			$maxLength = $this->getOption(self::OPTION_MAX_DESCRIPTION_LENGTH);
			if ($maxLength && utf8_strlen($this->get('media_description')) > $maxLength)
			{
				$this->error(new XenForo_Phrase('xengallery_please_enter_a_description_with_no_more_than_x_characters', array('count' => $maxLength)), 'media_description');
			}
		}

		if ($this->isInsert())
		{
			$this->updateRating(
				intval($this->get('rating_sum')), intval($this->get('rating_count'))
			);

			if (!$this->isAlbumMedia())
			{
				$this->set('media_privacy', 'category');
			}
		}

		if ($this->isUpdate())
		{
			if ($this->isChanged('category_id') && $this->get('category_id') > 0)
			{
				$categoryModel = $this->_getCategoryModel();
				$destinationCategory = $categoryModel->getCategoryById($this->get('category_id'));

				$allowedTypes = unserialize($destinationCategory['allowed_types']);

				$mediaType = $this->get('media_type');

				if (in_array('all', $allowedTypes))
				{
					return true;
				}

				if (!in_array($mediaType, $allowedTypes))
				{
					if ($mediaType == 'image_upload')
					{
						$this->error(new XenForo_Phrase('xengallery_you_cannot_put_images_in_this_category'));
					}

					if ($mediaType == 'video_embed' || $mediaType == 'video_upload')
					{
						$this->error(new XenForo_Phrase('xengallery_you_cannot_put_videos_in_this_category'));
					}
				}
			}
		}
	}

	protected function _postSave()
	{
		$this->updateCustomFields();
		$this->_updateTaggingVisibility();

		if ($this->isInsert())
		{
			$this->updateUserMediaCount();
			
			if ($albumId = $this->isAlbumMedia())
			{
				$this->updateAlbumCountAndDate();
				
				$album = $this->_getAlbumModel()->getAlbumById($albumId);
				
				$this->_db->update('xengallery_media', array('media_privacy' => $album['access_type']), 'media_id = ' . $this->get('media_id'));

				if ($album['album_default_order'] == 'custom')
				{
					$this->_db->query("
						UPDATE xengallery_media
						SET position = position + 1
						WHERE album_id = ?
						AND media_id != ?
					", array($albumId, $this->get('media_id')));
				}
			}
			else
			{
				$this->updateCategoryMediaCount();
			}

			$this->_getNewsFeedModel()->publish(
				$this->get('user_id'),
				$this->get('username'),
				'xengallery_media',
				$this->get('media_id'),
				'insert'
			);

			$ipId = XenForo_Model_Ip::log(
				$this->get('user_id'), 'xengallery_media', $this->get('media_id'), 'insert'
			);

			$this->_db->update('xengallery_media', array(
				'ip_id' => $ipId
			), 'media_id = ' . $this->get('media_id'));

			$this->_getMediaModel()->markMediaViewed(array('media_id' => $this->get('media_id')));
		}

		$media = $this->_getMediaModel()->getMediaById($this->get('media_id'), array('join' => XenGallery_Model_Media::FETCH_ATTACHMENT));
		
		$indexer = new XenForo_Search_Indexer();
		$dataHandler = XenForo_Search_DataHandler_Abstract::create('XenGallery_Search_DataHandler_Media');

		$dataHandler->insertIntoIndex($indexer, $this->getMergedData(), $media);
			
		$this->_updateDeletionLog();

		if ($this->isChanged('media_state') || $this->isInsert())
		{
			if ($this->get('media_state') == 'deleted')
			{
				$this->_deleteTagsForMedia();

				$this->updateUserMediaCount(false);
				$this->updateUserMediaQuota(false);

				if ($this->isAlbumMedia())
				{
					$this->updateAlbumCountAndDate(false);
				}
				else
				{
					$this->updateCategoryMediaCount(false);
				}

				$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts('xengallery_media', $this->get('media_id'));
			}

			if ($this->getExisting('media_state') == 'deleted')
			{
				$this->updateUserMediaCount();
				$this->updateUserMediaQuota();

				if ($this->isAlbumMedia())
				{
					$this->updateAlbumCountAndDate();
				}
				else
				{
					$this->updateCategoryMediaCount();
				}
			}

			$this->_updateModerationQueue($media);
		}

		if ($this->isChanged('category_id') && $this->isChanged('album_id'))
		{
			if ($this->getExisting('category_id') && $this->get('album_id'))
			{
				// From category to album
				$this->updateCategoryMediaCount(false, $this->getExisting('category_id'));
				$this->updateAlbumCountAndDate();

				$albumId = $this->get('album_id');
			}

			if ($this->getExisting('album_id') && $this->get('category_id'))
			{
				// From album to category
				$this->updateAlbumCountAndDate(false, $this->getExisting('album_id'));
				$this->updateCategoryMediaCount();

				$albumId = $this->getExisting('album_id');
			}

			$this->_updateAlbumCache($albumId);
		}
		else
		{
			if ($this->isChanged('album_id') && $this->isAlbumMedia())
			{
				$this->_originalAlbumId = $this->getExisting('album_id');
			}

			if ($this->isChanged('category_id') && !$this->isAlbumMedia())
			{
				$this->_originalCategoryId = $this->getExisting('category_id');
			}
		}
	}

	protected function _postSaveAfterTransaction()
	{
		if ($this->isChanged('album_id') && $this->isAlbumMedia())
		{
			$this->_updateAlbumCache($this->get('album_id'));

			if (!$this->_albumCountUpdated)
			{
				if ($this->_originalAlbumId)
				{
					$this->_updateAlbumCache($this->_originalAlbumId);
					$this->updateAlbumCountAndDate(false, $this->_originalAlbumId);
				}

				$this->updateAlbumCountAndDate();
			}

			$album = $this->_getAlbumModel()->getAlbumById($this->get('album_id'));
			$this->_db->update('xengallery_media', array('media_privacy' => $album['access_type']), 'media_id = ' . $this->get('media_id'));
		}

		if ($this->isChanged('category_id') && !$this->isAlbumMedia())
		{
			if (!$this->_categoryCountUpdated)
			{
				if ($this->_originalCategoryId)
				{
					$this->updateCategoryMediaCount(false, $this->_originalCategoryId);
				}

				$this->updateCategoryMediaCount();
			}
		}

		$media = $this->getMergedData();

		if ($this->get('media_state') == 'visible' && $this->isAlbumMedia())
		{
			if ($this->isInsert() || $this->getExisting('media_state') == 'moderated')
			{
				$album = $this->_getAlbumModel()->getAlbumById($this->get('album_id'));

				if (is_array($album))
				{
					$this->getModelFromCache('XenGallery_Model_AlbumWatch')->sendNotificationToWatchUsersOnMediaInsert($media, $album);
				}
			}
		}

		if ($this->get('media_state') == 'visible' && !$this->isAlbumMedia())
		{
			if ($this->isInsert() || $this->getExisting('media_state') == 'moderated')
			{
				$category = $this->_getCategoryModel()->getCategoryById($this->get('category_id'));

				if (is_array($category))
				{
					$this->getModelFromCache('XenGallery_Model_CategoryWatch')->sendNotificationToWatchUsersOnMediaInsert($media, $category);
				}
			}
		}
	}

	protected function _updateTaggingVisibility()
	{
		$newState = $this->get('media_state');
		$oldState = $this->getExisting('media_state');

		if ($newState == 'visible' && $oldState != 'visible')
		{
			$newVisible = true;
		}
		else if ($oldState == 'visible' && $newState != 'visible')
		{
			$newVisible = false;
		}
		else
		{
			return;
		}

		/** @var XenForo_Model_Tag $tagModel */
		$tagModel = $this->getModelFromCache('XenForo_Model_Tag');
		$tagModel->updateContentVisibility('xengallery_media', $this->get('media_id'), $newVisible);
	}

	protected function _deleteTagsForMedia()
	{
		/** @var XenForo_Model_Tag $tagModel */
		$tagModel = $this->getModelFromCache('XenForo_Model_Tag');
		$tagModel->deleteContentTags('xengallery_media', $this->get('media_id'));
	}

	public function updateCustomFields()
	{
		if (is_array($this->_updateCustomFields))
		{
			$mediaId = $this->get('media_id');

			$this->_db->query('DELETE FROM xengallery_field_value WHERE media_id = ?', $mediaId);

			foreach ($this->_updateCustomFields AS $fieldId => $value)
			{
				if (is_array($value))
				{
					$value = serialize($value);
				}
				$this->_db->query('
					INSERT INTO xengallery_field_value
						(media_id, field_id, field_value)
					VALUES
						(?, ?, ?)
					ON DUPLICATE KEY UPDATE
						field_value = VALUES(field_value)
				', array($mediaId, $fieldId, $value));
			}
		}
	}

	protected function _preDelete()
	{
		$fetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_ATTACHMENT
		);
		$media = $this->_getMediaModel()->getMediaById($this->get('media_id'), $fetchOptions);

		$this->_deletedFileSize = $media['file_size'];
	}

	/**
	* Post-delete handling.
	*/
	protected function _postDelete()
	{
		if ($this->isAlbumMedia())
		{
			$this->_updateAlbumCache($this->get('album_id'));
		}

		if ($this->getExisting('media_state') == 'visible')
		{
			if ($this->isAlbumMedia())
			{
				$this->updateAlbumCountAndDate(false);
			}
			else
			{
				$this->updateCategoryMediaCount(false);
			}

			$this->_deleteTagsForMedia();

			$this->updateUserMediaCount(false);
			$this->updateUserMediaQuota(false);

			$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts('xengallery_media', $this->get('media_id'));
		}

		$db = $this->_db;

		$mediaId = $db->quote($this->get('media_id'));

		$this->_getCommentModel()->deleteCommentsByMediaId($mediaId);
		$this->_getExifModel()->deleteExifByMediaId($mediaId);
		$this->_getRatingModel()->deleteRatingsByMediaId($mediaId);
		$this->_getUserTagModel()->deleteTagsByMediaId($mediaId);

		$associatedTables = array(
			'xengallery_field_value',
			'xengallery_media_user_view',
			'xengallery_media_view'
		);

		foreach ($associatedTables AS $table)
		{
			$db->delete($table, "media_id = $mediaId");
		}

		if ($this->get('media_type') == 'image_upload' || $this->get('media_type') == 'video_upload')
		{
			/** @var $attachmentModel XenForo_Model_Attachment */
			$attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');

			$attachment = $attachmentModel->getAttachmentById($this->get('attachment_id'));
			if ($attachment)
			{
				$attachmentModel->deleteAttachmentsFromContentIds(
					'xengallery_media', array($this->get('media_id'))
				);

				$mediaModel = $this->_getMediaModel();

				$thumbnailPath = $mediaModel->getMediaThumbnailFilePath($attachment);
				@unlink ($thumbnailPath);

				$originalPath = $mediaModel->getOriginalDataFilePath($attachment);
				@unlink ($originalPath);
			}
		}

		if ($this->get('media_type') == 'video_embed')
		{
			list ($videoThumbnail, $videoOriginal) = $this->_getMediaModel()->getMediaThumbnailFilePath($this->get('media_tag'));

			@unlink ($videoThumbnail);
			@unlink ($videoOriginal);
		}
		
		$indexer = new XenForo_Search_Indexer();
		$dataHandler = XenForo_Search_DataHandler_Abstract::create('XenGallery_Search_DataHandler_Media');

		$dataHandler->deleteFromIndex($indexer, $this->getMergedData());

		$this->_updateDeletionLog(true);
	}

	protected function _updateDeletionLog($hardDelete = false)
	{
		if ($hardDelete
			|| ($this->isChanged('media_state') && $this->getExisting('media_state') == 'deleted')
		)
		{
			$this->getModelFromCache('XenForo_Model_DeletionLog')->removeDeletionLog(
				'xengallery_media', $this->get('media_id')
			);

			$this->_updateAlbumCache($this->get('album_id'));
		}

		if ($this->isChanged('media_state') && $this->get('media_state') == 'deleted')
		{
			$reason = $this->getExtraData(self::DATA_DELETE_REASON);
			$this->getModelFromCache('XenForo_Model_DeletionLog')->logDeletion(
				'xengallery_media', $this->get('media_id'), $reason
			);

			$this->_updateAlbumCache($this->get('album_id'));
		}
	}
	
	protected function _updateModerationQueue(array $media)
	{
		if (!$this->isChanged('media_state'))
		{
			return;
		}

		if ($this->get('media_state') == 'moderated')
		{
			$this->getModelFromCache('XenForo_Model_ModerationQueue')->insertIntoModerationQueue(
				'xengallery_media', $this->get('media_id'), $this->get('media_date')
			);
			
			$indexer = new XenForo_Search_Indexer();
			$dataHandler = XenForo_Search_DataHandler_Abstract::create('XenGallery_Search_DataHandler_Media');

			$dataHandler->deleteFromIndex($indexer, $media);
			$this->_updateAlbumCache($this->get('album_id'));
		}
		else if ($this->getExisting('media_state') == 'moderated')
		{
			$this->getModelFromCache('XenForo_Model_ModerationQueue')->deleteFromModerationQueue(
				'xengallery_media', $this->get('media_id')
			);
			$this->_updateAlbumCache($this->get('album_id'));
		}
	}

	public function rebuildCommentPositions()
	{
		// $this->_getCommentModel()->rebuildCommentPositions($this->get('media_id'), 'media');
	}

	public function updateRating($adjustSum = null, $adjustCount = null)
	{
		if ($adjustSum === null && $adjustCount === null)
		{
			$rating = $this->_db->fetchRow("
				SELECT COUNT(*) AS total, SUM(rating) AS sum
				FROM xengallery_rating
				WHERE content_id = ?
				AND content_type = 'media'
				", $this->get('media_id'));

			$this->set('rating_sum', $rating['sum']);
			$this->set('rating_count', $rating['total']);
		}
		else
		{
			if ($adjustSum !== null)
			{
				$this->set('rating_sum', $this->get('rating_sum') + $adjustSum);
			}
			if ($adjustCount !== null)
			{
				$this->set('rating_count', $this->get('rating_count') + $adjustCount);
			}
		}

		if ($this->get('rating_count'))
		{
			$this->set('rating_avg', $this->get('rating_sum') / $this->get('rating_count'));
		}
		else
		{
			$this->set('rating_avg', 0);
		}

		$this->set('rating_weighted', $this->_getMediaModel()->getWeightedRating(
			$this->get('rating_count'), $this->get('rating_sum')
		));
	}

	public function updateUserMediaCount($increase = true, $userId = 0)
	{
		if (!$userId)
		{
			$userId = $this->get('user_id');
		}

		if ($increase)
		{
			$this->_db->query('
				UPDATE xf_user
				SET xengallery_media_count = xengallery_media_count + 1
				WHERE user_id = ?
			', $userId);
		}
		else
		{
			$this->_db->query('
				UPDATE xf_user
				SET xengallery_media_count = IF(xengallery_media_count > 0, xengallery_media_count - 1, 0)
				WHERE user_id = ?
			', $userId);
		}
	}

	public function updateUserMediaQuota($increase = true)
	{
		$fetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_ATTACHMENT
		);
		$media = $this->_getMediaModel()->getMediaById($this->get('media_id'), $fetchOptions);
		if (!$media)
		{
			if (!$this->_deletedFileSize)
			{
				return false;
			}

			$media = array();
			$media['file_size'] = $this->_deletedFileSize;
		}

		$operator = '+';
		$value = $media['file_size'];

		if (!$increase)
		{
			$operator = '-';
		}

		try
		{
			$this->_db->query("
				UPDATE xf_user
				SET xengallery_media_quota = xengallery_media_quota $operator $value
				WHERE user_id = ?
			", $this->get('user_id'));
		}
		catch (Zend_Db_Exception $e)
		{
			$this->_db->query("
				UPDATE xf_user
				SET xengallery_media_quota = 0
				WHERE user_id = ?
			", $this->get('user_id'));
		}
	}

	public function updateCategoryMediaCount($increase = true, $categoryId = 0)
	{
		$writer = XenForo_DataWriter::create('XenGallery_DataWriter_Category', XenForo_DataWriter::ERROR_SILENT);

		if (!$categoryId)
		{
			$writer->setExistingData($this->get('category_id'));
		}
		else
		{
			$writer->setExistingData($categoryId);
		}

		$value = $writer->get('category_media_count') + 1;

		if (!$increase)
		{
			$value = $writer->get('category_media_count') - 1;
		}

		if ($value < 1)
		{
			$value = 0;
		}

		$writer->bulkSet(array(
			'category_media_count' => $value
		));

		$writer->save();

		$this->_categoryCountUpdated = true;
	}
	
	public function updateAlbumCountAndDate($increase = true, $albumId = 0)
	{
		$writer = XenForo_DataWriter::create('XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);

		if (!$albumId)
		{
			if (!$writer->setExistingData($this->get('album_id')))
			{
				return false;
			}
		}
		else
		{
			if (!$writer->setExistingData($albumId))
			{
				return false;
			}
		}
		
		$value = $writer->get('album_media_count') + 1;
	
		if (!$increase)
		{
			$value = $writer->get('album_media_count') - 1;
		}

		if ($value < 1)
		{
			$value = 0;
		}
		
		$writer->bulkSet(array(
			'album_media_count' => $value,
			'last_update_date' => XenForo_Application::$time
		));
		
		$writer->save();

		$this->_albumCountUpdated = true;
	}
	
	public function isAlbumMedia()
	{
		$albumId = $this->get('album_id');
		
		return $albumId;
	}

	protected function _updateAlbumCache($albumId)
	{
		if ($albumId)
		{
			$writer = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
			$writer->setExistingData($albumId);

			if (!$writer->get('manual_media_cache') && !$writer->get('album_thumbnail_date'))
			{
				$media = $this->_getMediaModel()->getMediaForAlbumCache($albumId);

				$writer->bulkSet(array(
					'last_update_date' => XenForo_Application::$time,
					'media_cache' => serialize($media)
				));

				$writer->save();
			}
		}
	}

	/**
	 * Gets the default set of options for this data writer.
	 *
	 * @return array
	 */
	protected function _getDefaultOptions()
	{
		$options = XenForo_Application::getOptions();

		return array(
			self::OPTION_MAX_TITLE_LENGTH => $options->get('xengalleryMaxTitleLength'),
			self::OPTION_MAX_DESCRIPTION_LENGTH => $options->get('xengalleryMaxDescLength')
		);
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
	* @return XenGallery_Model_Category
	*/
	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Category');
	}

	/**
	 * @return XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Comment');
	}

	/**
	 * @return XenGallery_Model_Rating
	 */
	protected function _getRatingModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Rating');
	}

	/**
	 * @return XenGallery_Model_UserTag
	 */
	protected function _getUserTagModel()
	{
		return $this->getModelFromCache('XenGallery_Model_UserTag');
	}

	/**
	 * @return XenGallery_Model_Exif
	 */
	protected function _getExifModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Exif');
	}

	/**
	 * @return XenGallery_Model_Watermark
	 */
	protected function _getWatermarkModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Watermark');
	}

	/**
	 * @return XenForo_Model_Attachment
	 */
	protected function _getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
	}

	/**
	 * @return XenGallery_Model_Field
	 */
	protected function _getFieldModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Field');
	}
}