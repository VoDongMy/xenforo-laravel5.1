<?php

class XenGallery_DataWriter_Album extends XenForo_DataWriter
{
	/**
	* Holds the reason for soft deletion.
	*
	* @var string
	*/
	const DATA_DELETE_REASON = 'deleteReason';

	/**
	 * Option that controls the maximum number of characters that are allowed in
	 * an album title.
	 *
	 * @var string
	 */
	const OPTION_MAX_TITLE_LENGTH = 'maxTitleLength';

	/**
	 * Option that controls the maximum number of characters that are allowed in
	 * an album description.
	 *
	 * @var string
	 */
	const OPTION_MAX_DESCRIPTION_LENGTH = 'maxDescriptionLength';

	/**
	 * Holds the access type for the view permission.
	 *
	 * @var string
	 */
	const DATA_ACCESS_TYPE = 'permissionViewAccessType';

	/**
	 * Holds the access type for the add permission.
	 *
	 * @var string
	 */
	const DATA_ACCESS_TYPE_ADD = 'permissionAddAccessType';

	/**
	* Title of the phrase that will be created when a call to set the
	* existing data fails (when the data doesn't exist).
	*
	* @var string
	*/
	protected $_existingDataErrorPhrase = 'xengallery_requested_album_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xengallery_album' => array(
				'album_id' => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'album_title' => array('type' => self::TYPE_STRING, 'required' => true,
					'requiredError' => 'xengallery_enter_valid_album_title'
				),
				'album_description' => array('type' => self::TYPE_STRING, 'default' => ''),
				'album_create_date' => array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'last_update_date' => array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'media_cache' => array('type' => self::TYPE_SERIALIZED),
				'manual_media_cache' => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'album_state' => array('type' => self::TYPE_STRING, 'default' => 'visible',
					'allowedValues' => array('visible', 'moderated', 'deleted')
				),
				'album_user_id' => array('type' => self::TYPE_UINT, 'default' => 0),
				'album_username' => array('type' => self::TYPE_STRING, 'maxLength' => 50),
				'ip_id' => array('type' => self::TYPE_UINT, 'default' => 0),
				'album_likes' => array('type' => self::TYPE_UINT),
				'album_like_users' => array('type' => self::TYPE_SERIALIZED),
				'album_media_count' => array ('type' => self::TYPE_UINT, 'default' => 0),
				'album_view_count' => array('type' => self::TYPE_UINT, 'default' => 0),				
				'album_rating_count' => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'album_rating_sum' => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'album_rating_avg' => array('type' => self::TYPE_FLOAT, 'default' => 0),
				'album_rating_weighted' => array('type' => self::TYPE_FLOAT, 'default' => 0),
				'album_comment_count' => array('type' => self::TYPE_UINT, 'default' => 0),
				'album_last_comment_date' => array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'album_warning_id' => array('type' => self::TYPE_UINT, 'default' => 0),
				'album_warning_message' => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 255),
				'album_default_order' => array('type' => self::TYPE_STRING, 'default' => '',
					'allowedValues' => array('', 'custom')
				),
				'album_thumbnail_date' => array('type' => self::TYPE_UINT, 'default' => 0),
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

		return array('xengallery_album' => $this->_getAlbumModel()->getAlbumById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'album_id = ' . $this->_db->quote($this->getExisting('album_id'));
	}
	
	protected function _preSave()
	{
		if ($this->isChanged('album_title'))
		{
			$maxLength = $this->getOption(self::OPTION_MAX_TITLE_LENGTH);
			if ($maxLength && utf8_strlen($this->get('album_title')) > $maxLength)
			{
				$this->error(new XenForo_Phrase('xengallery_please_enter_a_title_with_no_more_than_x_characters', array('count' => $maxLength)), 'album_title');
			}

			if ($this->get('album_title') == '')
			{
				$this->error(new XenForo_Phrase('xengallery_please_enter_a_valid_album_title'));
			}
		}

		if ($this->isChanged('album_description'))
		{
			$maxLength = $this->getOption(self::OPTION_MAX_DESCRIPTION_LENGTH);
			if ($maxLength && utf8_strlen($this->get('album_description')) > $maxLength)
			{
				$this->error(new XenForo_Phrase('xengallery_please_enter_a_description_with_no_more_than_x_characters', array('count' => $maxLength)), 'album_description');
			}
		}
	}
	
	protected function _postSave()
	{
		$this->changeAlbumPrivacy();

		if ($this->isInsert())
		{
			$albumId = $this->get('album_id');

			$ipId = XenForo_Model_Ip::log(
				$this->get('album_user_id'), 'xengallery_album', $albumId, 'insert'
			);
			
			$this->_db->update('xengallery_album', array(
				'ip_id' => $ipId
			), 'album_id = ' . $albumId);
			
			$this->updateUserAlbumCount();
		}

		if ($this->isUpdate())
		{
			if ($this->isChanged('album_state'))
			{
				if ($this->get('album_state') == 'deleted')
				{
					$this->updateUserAlbumCount(false);
					$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts('xengallery_album', $this->get('album_id'));

					$db = $this->_db;

					try
					{
						$db->query('
							INSERT IGNORE INTO xengallery_private_map
								(album_id, private_user_id)
							VALUES
								(?, ?)
						', array($this->get('album_id'), $this->get('album_user_id')));
						$db->delete('xengallery_shared_map', 'album_id = ' . $this->get('album_id'));
						$db->update('xengallery_media', array('media_privacy' => 'private'), 'album_id = ' . $db->quote($this->get('album_id')));
					}
					catch (Zend_Db_Exception $e) {}

					$album = $this->_getAlbumModel()->getAlbumById($this->get('album_id'));

					$albumPermDw = XenForo_DataWriter::create('XenGallery_DataWriter_AlbumPermission');
					$albumPermDw->setExistingData($album);

					$albumPermDw->set('access_type', 'private');
					$albumPermDw->save();
				}

				if ($this->getExisting('album_state') == 'deleted')
				{
					$this->updateUserAlbumCount();
				}
			}
		}
	}
	
	/**
	 * Pre-delete handling.
	 */
	protected function _preDelete()
	{
		$media = $this->_getMediaModel()->getMedia(array('album_id' => $this->get('album_id')));
		foreach ($media AS $item)
		{
			$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
			$mediaWriter->setExistingData($item);

			$mediaWriter->delete();
		}
		
		$comments = $this->_getCommentModel()->getComments(array('album_id' => $this->get('album_id')));
		foreach ($comments AS $comment)
		{
			$commentWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Comment');
			$commentWriter->setExistingData($comment);

			$commentWriter->delete();
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		if ($this->getExisting('album_state') == 'visible')
		{
			$this->updateUserAlbumCount(false);
			$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts('xengallery_album', $this->get('album_id'));
		}
	}

	public function rebuildCommentPositions()
	{
		// $this->_getCommentModel()->rebuildCommentPositions($this->get('album_id'), 'album');
	}
	
	public function updateRating($adjustSum = null, $adjustCount = null)
	{
		if ($adjustSum === null && $adjustCount === null)
		{
			$rating = $this->_db->fetchRow("
				SELECT COUNT(*) AS total, SUM(rating) AS sum
				FROM xengallery_rating
				WHERE content_id = ?
				AND content_type = 'album'
				", $this->get('album_id'));
	
			$this->set('album_rating_sum', $rating['sum']);
			$this->set('album_rating_count', $rating['total']);
		}
		else
		{
			if ($adjustSum !== null)
			{
				$this->set('album_rating_sum', $this->get('album_rating_sum') + $adjustSum);
			}
			if ($adjustCount !== null)
			{
				$this->set('album_rating_count', $this->get('album_rating_count') + $adjustCount);
			}
		}
	
		if ($this->get('album_rating_count'))
		{
			$this->set('album_rating_avg', $this->get('album_rating_sum') / $this->get('album_rating_count'));
		}
		else
		{
			$this->set('album_rating_avg', 0);
		}

		$this->set('album_rating_weighted', $this->_getAlbumModel()->getWeightedRating(
			$this->get('album_rating_count'), $this->get('album_rating_sum')
		));
	}

	public function updateUserAlbumCount($increase = true)
	{
		if ($increase)
		{
			$this->_db->query('
				UPDATE xf_user
				SET xengallery_album_count = xengallery_album_count + 1
				WHERE user_id = ?
			', $this->get('album_user_id'));

			$this->_handleUserMediaCountAdjustments();
		}
		else
		{
			$this->_db->query('
				UPDATE xf_user
				SET xengallery_album_count = IF(xengallery_album_count > 0, xengallery_album_count - 1, 0)
				WHERE user_id = ?
			', $this->get('album_user_id'));

			$this->_handleUserMediaCountAdjustments(false);
		}
	}

	protected function _handleUserMediaCountAdjustments($increase = true, $counts = null)
	{
		if ($counts === null)
		{
			$counts = $this->_getVisibleMediaInAlbumPairs();
		}

		$userIds = array();

		if ($increase)
		{
			foreach ($counts AS $userId => $count)
			{
				$this->_db->query('
					UPDATE xf_user
					SET xengallery_media_count = xengallery_media_count + ?
					WHERE user_id = ?
				', array($count, $userId));

				$userIds[] = $userId;
			}
		}
		else
		{
			foreach ($counts AS $userId => $count)
			{
				$this->_db->query('
					UPDATE xf_user
					SET xengallery_media_count = GREATEST(0, xengallery_media_count - ?)
					WHERE user_id = ?
				', array($count, $userId));

				$userIds[] = $userId;
			}
		}

		$this->_getMediaModel()->rebuildUserMediaQuota(array_unique($userIds));
	}

	protected function _getVisibleMediaInAlbumPairs()
	{
		return $this->_db->fetchPairs('
		  SELECT user_id, COUNT(*)
		  FROM xengallery_media
		  WHERE album_id = ?
			AND media_state = \'visible\'
		  GROUP BY user_id
		', $this->get('album_id'));
	}

	public function changeAlbumPrivacy($type = 'view')
	{
		switch ($type)
		{
			case 'add':

				$accessType = $this->getExtraData(self::DATA_ACCESS_TYPE_ADD);
				break;

			case 'view':
			default:

				$accessType = $this->getExtraData(self::DATA_ACCESS_TYPE);
				break;
		}

		if ($accessType)
		{
			$albumModel = $this->_getAlbumModel();
			$albumId = $this->get('album_id');

			$existingPermission = $albumModel->getUserAlbumPermission(
				$albumId, $type
			);

			$albumPermDw = XenForo_DataWriter::create('XenGallery_DataWriter_AlbumPermission');
			if ($existingPermission)
			{
				$albumPermDw->setExistingData($existingPermission);
			}
			$albumPermDw->setExtraData(
				XenGallery_DataWriter_AlbumPermission::DATA_ALBUM_USER_ID,
				$this->get('album_user_id')
			);

			$albumPermDw->bulkSet(array(
				'album_id' => $albumId,
				'permission' => $type,
				'access_type' => $accessType
			));
			$albumPermDw->save();
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
	 * @return XenGallery_Model_Comment
	 */
	protected function _getCommentModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Comment');
	}
}
