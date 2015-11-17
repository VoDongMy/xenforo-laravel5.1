<?php

class XenGallery_DataWriter_AlbumPermission extends XenForo_DataWriter
{
	/**
	 * Holds the user ID for the album owner.
	 *
	 * @var string
	 */
	const DATA_ALBUM_USER_ID = 'albumUserId';

	/**
	 * Option that represents whether the alerts should be skipped. Defaults to false.
	 *
	 * @var string
	 */
	const OPTION_SKIP_ALERTS = 'skipAlerts';

	protected $_preventDoubleAlert = array();

	protected function _getFields()
	{
		return array(
			'xengallery_album_permission' => array(
				'album_id'		=> array('type' => self::TYPE_UINT, 'required' => true),
				'permission'	=> array('type' => self::TYPE_STRING, 'required' => true,
					'allowedValues' => array('view', 'add')
				),
				'access_type'	=> array('type' => self::TYPE_STRING, 'default' => 'public',
					'allowedValues' => array('public', 'followed', 'members', 'private', 'shared')
				),
				'share_users' => array('type' => self::TYPE_SERIALIZED, 'default' => '')
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
		if (!is_array($data))
		{
			return false;
		}
		else if (isset($data['album_id'], $data['permission']))
		{
			$albumId = $data['album_id'];
			$permission = $data['permission'];
		}
		else if (isset($data[0], $data[1]))
		{
			$albumId = $data[0];
			$permission = $data[1];
		}
		else
		{
			return false;
		}

		return array('xengallery_album_permission' => $this->_getAlbumModel()->getUserAlbumPermission($albumId, $permission));
	}

	/**
	 * Gets SQL condition to update the existing record.
	 *
	 * @return string
	 */
	protected function _getUpdateCondition($tableName)
	{
		return 'album_id = ' . $this->_db->quote($this->getExisting('album_id'))
			. ' AND permission = ' . $this->_db->quote($this->getExisting('permission'));
	}

	/**
	 * Gets the default options for this data writer.
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_SKIP_ALERTS => false,
		);
	}

	protected function _preSave()
	{
		if (!$this->get('share_users')
			|| $this->get('access_type') == 'public'
			|| $this->get('access_type') == 'private'
			|| $this->get('access_type') == 'members'
		)
		{
			$this->set('share_users', array());
		}
	}

	protected function _postSave()
	{
		$albumUserId = $this->getExtraData(self::DATA_ALBUM_USER_ID);
		if ($albumUserId && $this->isChanged('access_type'))
		{
			$this->_setShareUsers($albumUserId, true);
		}

		if ($this->isChanged('share_users'))
		{
			$shareUsers = @unserialize($this->get('share_users'));
			if ($shareUsers)
			{
				$existingUsers = @unserialize($this->getExisting('share_users'));
				if (!$existingUsers)
				{
					$existingUsers = array();
				}

				$newUsers = array();
				foreach ($shareUsers AS $key => $shareUser)
				{
					if (!isset($existingUsers[$key]))
					{
						$newUsers[$key] = $shareUser;
					}
				}

				$users = $this->getModelFromCache('XenForo_Model_User')->getUsersByIds(
					$newUsers, array(
						'join' => XenForo_Model_User::FETCH_USER_OPTION
					)
				);

				$albumModel = $this->_getAlbumModel();

				$album = $albumModel->getAlbumById($this->get('album_id'));
				foreach ($users AS $user)
				{
					if ($album['album_user_id'] != $user['user_id'])
					{
						if (XenForo_Model_Alert::userReceivesAlert($user, 'xengallery_album', 'share')
							&& !$this->getOption(self::OPTION_SKIP_ALERTS)
						)
						{
							if ($this->get('permission') == 'add')
							{
								XenForo_Model_Alert::alert(
									$user['user_id'],
									$album['album_user_id'],
									$album['album_username'],
									'xengallery_album',
									$this->get('album_id'),
									'share_add'
								);
							}
							elseif ($this->get('permission') == 'view')
							{
								XenForo_Model_Alert::alert(
									$user['user_id'],
									$album['album_user_id'],
									$album['album_username'],
									'xengallery_album',
									$this->get('album_id'),
									'share'
								);
							}
						}
					}
				}
			}
		}
	}

	protected function _setShareUsers($albumUserId = 0, $addSelf = false)
	{
		if ($this->get('permission') == 'view')
		{
			$this->_db->update('xengallery_media', array(
				'media_privacy' => $this->get('access_type')
			), 'album_id = ' . $this->get('album_id'));
		}

		if ($this->get('access_type') == 'followed' && $albumUserId)
		{
			$userModel = $this->_getUserModel();

			$followedUsers = $userModel->getFollowingDenormalizedValue($albumUserId);
			$followedUsers = explode(',', $followedUsers);

			$users = $userModel->getUsersByIds($followedUsers);

			$albumModel = $this->_getAlbumModel();

			$album = $albumModel->getAlbumById($this->get('album_id'));

			if ($this->get('permission') == 'view')
			{
				$shareUsers = $albumModel->prepareViewShareUsers($users, $album);
			}
			elseif ($this->get('permission') == 'add')
			{
				$shareUsers = $albumModel->prepareAddShareUsers($users, $album);
			}

			$this->_updateShareUsers($shareUsers);
		}

		if ($this->get('permission') == 'view')
		{
			if ($this->getExisting('access_type')  == 'private')
			{
				$this->_db->delete('xengallery_private_map', 'album_id = ' . $this->_db->quote($this->get('album_id')));
			}

			if ($this->getExisting('access_type') == 'shared' || $this->getExisting('access_type') == 'followed')
			{
				$this->_db->delete('xengallery_shared_map', 'album_id = ' . $this->_db->quote($this->get('album_id')));
			}
		}

		if ($this->get('permission') == 'add')
		{
			if ($this->get('access_type')  == 'public'
				|| $this->get('access_type') == 'members'
			)
			{
				$this->_db->delete('xengallery_add_map', 'album_id = ' . $this->_db->quote($this->get('album_id')));
			}

			if ($this->get('access_type') == 'private')
			{
				$this->_db->delete('xengallery_add_map', 'album_id = ' . $this->_db->quote($this->get('album_id')) . ' AND add_user_id <> ' . $this->_db->quote($albumUserId));
			}
		}

		if ($addSelf && $albumUserId)
		{
			if ($this->get('access_type') == 'private'
				&& $this->get('permission') == 'view'
			)
			{
				$this->_db->query('
					INSERT IGNORE INTO xengallery_private_map
						(album_id, private_user_id)
					VALUES
						(?, ?)
				', array($this->get('album_id'), $albumUserId));
			}

			if (($this->get('access_type') == 'shared'
					|| $this->get('access_type') == 'followed')
				&& $this->get('permission') == 'view'
			)
			{
				$this->_db->query('
					INSERT IGNORE INTO xengallery_shared_map
						(album_id, shared_user_id)
					VALUES
						(?, ?)
				', array($this->get('album_id'), $albumUserId));
			}

			if (($this->get('access_type') == 'private'
					|| $this->get('access_type') == 'shared'
					|| $this->get('access_type') == 'followed')
				&& $this->get('permission') == 'add'
			)
			{
				$this->_db->query('
					INSERT IGNORE INTO xengallery_add_map
						(album_id, add_user_id)
					VALUES
						(?, ?)
				', array($this->get('album_id'), $albumUserId));
			}
		}
	}

	protected function _updateShareUsers(array $shareUsers)
	{
		$albumPermDw = XenForo_DataWriter::create('XenGallery_DataWriter_AlbumPermission', self::ERROR_SILENT);
		$albumPermDw->setExistingData($this->getMergedData());

		$albumPermDw->set('share_users', $shareUsers);
		$albumPermDw->save();
	}

	/**
	 * @return XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Album');
	}
}