<?php

class XenGallery_DataWriter_UserTag extends XenForo_DataWriter
{
	/**
	 * Gets the fields that are defined for the table. See parent for explanation.
	 *
	 * @return array
	 */
	protected function _getFields()
	{
		return array(
			'xengallery_user_tag' => array(
				'tag_id'			=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'media_id'			=> array('type' => self::TYPE_UINT, 'required' => true),
				'user_id'			=> array('type' => self::TYPE_UINT, 'required' => true),
				'username'			=> array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
					'requiredError' => 'xengallery_please_enter_valid_username'
				),
				'tag_data'		=> array('type' => self::TYPE_SERIALIZED, 'required' => true,
					'requiredError' => 'xengallery_please_enter_valid_tag_data'
				),
				'tag_date'			=> array('type' => self::TYPE_UINT, 'required' => true, 'default' => XenForo_Application::$time),
				'tag_by_user_id'	=> array('type' => self::TYPE_UINT, 'required' => true),
				'tag_by_username'	=> array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
					'requiredError' => 'xengallery_please_enter_valid_username'
				),
				'tag_state' => array('type' => self::TYPE_STRING, 'default' => 'approved',
					'allowedValues' => array('approved', 'pending', 'rejected')
				),
				'tag_state_date'	=> array('type' => self::TYPE_UINT, 'required' => true, 'default' => XenForo_Application::$time)
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'tag_id'))
		{
			return false;
		}
	
		return array('xengallery_user_tag' => $this->_getTaggingModel()->getTagById($id));
	}
	
	/**
	 * Gets SQL condition to update the existing record.
	 *
	 * @return string
	 */
	protected function _getUpdateCondition($tableName)
	{
		return 'tag_id = ' . $this->_db->quote($this->getExisting('tag_id'));
	}
	
	protected function _preSave()
	{
		$alreadyTagged = $this->_getTaggingModel()->getTagByMediaAndUserId($this->get('media_id'), $this->get('user_id'), 'approved');
		if ($alreadyTagged && $this->isInsert())
		{
			$this->error(new XenForo_Phrase('xengallery_you_have_already_tagged_this_user_in_this_media'));
		}

		$tagData = @unserialize($this->get('tag_data'));
		// Always square
		$size = $tagData['tag_width'] / $tagData['tag_multiplier'];
		if (intval($size) < 50)
		{
			$size = 50;

			$tagData['tag_width'] = $size / $tagData['tag_multiplier'];
			$tagData['tag_height'] = $size / $tagData['tag_multiplier'];
		}

		$this->set('tag_data', serialize($tagData));
	}
	
	protected function _postSave()
	{
		$content = $this->_getMediaModel()->getMediaById($this->get('media_id'), array(
			'join' => XenGallery_Model_Media::FETCH_USER
				| XenGallery_Model_Media::FETCH_USER_OPTION
				| XenGallery_Model_Media::FETCH_ALBUM
		));

		$visitor = XenForo_Visitor::getInstance();

		$viewingUser = XenForo_Model::create('XenForo_Model_User')->getUserById($this->get('user_id'), array(
			'join' => XenForo_Model_User::FETCH_USER_FULL
				| XenForo_Model_User::FETCH_USER_PERMISSIONS
		));
		$viewingUser['permissions'] = @unserialize($viewingUser['global_permission_cache']);

		$canViewMedia = true;
		if ($content['album_id'])
		{
			$albumModel = $this->_getAlbumModel();
			$album = $albumModel->getAlbumById($content['album_id']);
			$album = $albumModel->prepareAlbumWithPermissions($album);

			if (!$albumModel->canViewAlbum($album, $null, $viewingUser))
			{
				$canViewMedia = false;
			}
		}
		elseif ($content['category_id'])
		{
			$categoryModel = $this->_getCategoryModel();
			$category = $categoryModel->getCategoryById($content['category_id']);

			if (!$categoryModel->canViewCategory($category, $null, $viewingUser))
			{
				$canViewMedia = false;
			}
		}

		if ($this->isInsert())
		{
			if ($this->get('tag_state') == 'pending' && $canViewMedia)
			{
				XenForo_Model_Alert::alert(
					$this->get('user_id'),
					$this->get('tag_by_user_id'),
					$this->get('tag_by_username'),
					'xengallery_media',
					$this->get('media_id'),
					'tag_approve', array('tag_id' => $this->get('tag_id'))
				);

				// Just to suppress the second alert.
				$canViewMedia = false;
			}

			if ($content && XenForo_Model_Alert::userReceivesAlert($content, 'xengallery_media', 'tag') && $canViewMedia)
			{
				if ($visitor->user_id != $this->get('user_id'))
				{
					XenForo_Model_Alert::alert(
						$this->get('user_id'),
						$this->get('tag_by_user_id'),
						$this->get('tag_by_username'),
						'xengallery_media',
						$this->get('media_id'),
						'tag', array('tag_id' => $this->get('tag_id'))
					);
				}
			}
		}

		if ($this->isUpdate())
		{
			if ($this->get('tag_state') == 'approved')
			{
				if ($content && XenForo_Model_Alert::userReceivesAlert($content, 'xengallery_media', 'tag') && $canViewMedia)
				{
					if ($visitor->user_id != $this->get('user_id'))
					{
						XenForo_Model_Alert::alert(
							$this->get('user_id'),
							$this->get('tag_by_user_id'),
							$this->get('tag_by_username'),
							'xengallery_media',
							$this->get('media_id'),
							'tag', array('tag_id' => $this->get('tag_id'))
						);
					}
				}
			}
		}
	}
	
	protected function _postDelete()
	{
		$alertsToDelete = $this->_db->fetchAll("
			SELECT *
			FROM xf_user_alert
			WHERE alerted_user_id = ? AND content_type = ?
			AND action IN (" . $this->_db->quote(array('tag', 'tag_approve')) . ") AND content_id = ?
		", array($this->get('user_id'), 'xengallery_media', $this->get('media_id')));

		foreach ($alertsToDelete AS $alertToDelete)
		{
			if ($extraData = @unserialize($alertToDelete['extra_data']))
			{
				if ($extraData['tag_id'] == $this->get('tag_id'))
				{
					$this->_db->delete('xf_user_alert', 'alert_id = ' . $this->_db->quote($alertToDelete['alert_id']));
				}
			}
		}
	}
	
	/**
	 * @return XenGallery_Model_UserTag
	 */
	protected function _getTaggingModel()
	{
		return $this->getModelFromCache('XenGallery_Model_UserTag');
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
}