<?php

class XenGallery_Model_InlineMod_Album extends XenForo_Model
{
	/**
	 * From a List of IDs, gets info about the album.
	 *
	 * @param array $albumIds List of IDs
	 *
	 * @return array Format: list of album
	 */
	public function getAlbumData(array $albumIds, array $fetchOptions = array())
	{
		if (!$albumIds)
		{
			return array();
		}

		return $this->_getAlbumModel()->getAlbums(array('album_id' => $albumIds, 'deleted' => true), $fetchOptions);
	}

	/**
	 * Determines if the selected album IDs can be deleted.
	 *
	 * @param array $albumIds List of IDs check
	 * @param string $deleteType The type of deletion being requested (soft or hard)
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canDeleteAlbum(array $albumIds, $deleteType = 'soft', &$errorKey = '', array $viewingUser = null)
	{
		$album = $this->getAlbumData($albumIds);
		return $this->canDeleteAlbumData($album, $deleteType, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected album data can be deleted.
	 *
	 * @param array $album List of data to be deleted
	 * @param string $deleteType Type of deletion (soft or hard)
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canDeleteAlbumData(array $album, $deleteType, &$errorKey = '', array $viewingUser = null)
	{
		if (!$album)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);
		$albumModel = $this->_getAlbumModel();

		foreach ($album AS $_album)
		{
			if ($_album['album_state'] != 'deleted' && !$albumModel->canDeleteAlbum($_album, $deleteType, $errorKey, $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Determines if the selected album IDs can be edited.
	 *
	 * @param array $albumIds List of IDs to check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canEditAlbum(array $albumIds, &$errorKey = '', array $viewingUser = null)
	{
		$album = $this->getAlbumData($albumIds);
		return $this->canEditAlbumData($album, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected album data can be edited.
	 *
	 * @param array $album List of data to be edited
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canEditAlbumData(array $album, &$errorKey = '', array $viewingUser = null)
	{
		if (!$album)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);
		$albumModel = $this->_getAlbumModel();

		foreach ($album AS $_album)
		{
			if (!$albumModel->canEditAlbum($_album,  $errorKey, $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Determines if the selected album IDs can be have their privacy changed.
	 *
	 * @param array $albumIds List of IDs to check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canChangeAlbumViewPerm(array $albumIds, &$errorKey = '', array $viewingUser = null)
	{
		$albums = $this->getAlbumData($albumIds);
		return $this->canChangeAlbumViewPermData($albums, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected album data can be edited.
	 *
	 * @param array $album List of data to be edited
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canChangeAlbumViewPermData(array $albums, &$errorKey = '', array $viewingUser = null)
	{
		if (!$albums)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);
		$albumModel = $this->_getAlbumModel();

		foreach ($albums AS $album)
		{
			if (!$albumModel->canChangeAlbumViewPerm($album,  $errorKey, $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Deletes the specified album if permissions are sufficient.
	 *
	 * @param array $albumIds List of IDs to delete
	 * @param array $options Options that control the delete. Supports deleteType (soft or hard).
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function editAlbum(array $albumIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$options = array_merge(
			array(
				'input' => array(),
				'authorAlert' => false,
				'authorAlertReason' => ''
			), $options
		);

		$fetchOptions = array(
			'join' => XenGallery_Model_Album::FETCH_USER
		);

		$albums = $this->getAlbumData(array_keys($albumIds), $fetchOptions);

		if (empty($options['skipPermissions']) && !$this->canEditAlbumData($albums, $errorKey, $viewingUser))
		{
			return false;
		}

		foreach ($albums AS $albumId => $_album)
		{
			if (!isset($options['input'][$albumId]))
			{
				continue;
			}

			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($albumId);

			$dw->bulkSet($options['input'][$albumId]);
			if (!$dw->get('album_id'))
			{
				continue;
			}

			$dw->save();

			$changes = $this->_getLogChanges($dw);
			if ($changes)
			{
				XenForo_Model_Log::logModeratorAction('xengallery_album', $dw->getMergedData(), 'edit', $changes);
			}

			if ($_album['album_state'] == 'visible' && $options['authorAlert'])
			{
				$this->_getMediaModel()->sendAuthorAlert(
					$_album, 'xengallery_album', 'edit', $options, array(), 'album_user_id'
				);
			}
		}

		return true;
	}

	/**
	 * Changes the album privacy if permissions are sufficient.
	 *
	 * @param array $albumIds List of IDs to change
	 * @param array $options Options that control the delete. Supports deleteType (soft or hard).
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function changeAlbumPrivacy(array $albums, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		if (empty($options['skipPermissions']) && !$this->canChangeAlbumViewPermData($albums, $errorKey, $viewingUser))
		{
			return false;
		}

		foreach ($albums AS $albumId => $album)
		{
			/** @var $dw XenGallery_DataWriter_Album */
			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);

			$dw->setExistingData($albumId);
			$dw->setExtraData(XenGallery_DataWriter_Album::DATA_ACCESS_TYPE, $album['album_privacy']);

			$dw->bulkSet($album);
			if (!$dw->get('album_id'))
			{
				continue;
			}

			$dw->changeAlbumPrivacy();
			$dw->save();

			XenForo_Model_Log::logModeratorAction('xengallery_album', $dw->getMergedData(), 'permission', array(
				'permission' => 'view', 'access_type' => $album['album_privacy']
			));
		}

		return true;
	}

	/**
	 * Deletes the specified album if permissions are sufficient.
	 *
	 * @param array $albumIds List of IDs to delete
	 * @param array $options Options that control the delete. Supports deleteType (soft or hard).
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function deleteAlbum(array $albumIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$options = array_merge(
			array(
				'deleteType' => '',
				'reason' => '',

				'authorAlert' => false,
				'authorAlertReason' => ''
			), $options
		);

		if (!$options['deleteType'])
		{
			throw new XenForo_Exception('No deletion type specified.');
		}

		$fetchOptions = array(
			'join' => XenGallery_Model_Album::FETCH_USER
		);

		$album = $this->getAlbumData($albumIds, $fetchOptions);

		if (empty($options['skipPermissions']) && !$this->canDeleteAlbumData($album, $options['deleteType'], $errorKey, $viewingUser))
		{
			return false;
		}

		foreach ($album AS $_album)
		{
			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Album');
			$dw->setExistingData($_album);
			if (!$dw->get('album_id'))
			{
				continue;
			}

			if ($options['deleteType'] == 'hard')
			{
				$dw->delete();
			}
			else
			{
				$dw->setExtraData(XenGallery_DataWriter_Album::DATA_DELETE_REASON, $options['reason']);
				$dw->set('album_state', 'deleted');
				$dw->save();
			}

			XenForo_Model_Log::logModeratorAction('xengallery_album', $dw->getMergedData(), 'delete_' . $options['deleteType'], array('reason' => $options['reason']));

			if ($_album['album_state'] == 'visible' && $options['authorAlert'])
			{
				$this->_getMediaModel()->sendAuthorAlert(
					$_album, 'xengallery_album', 'delete', $options, array(), 'album_user_id'
				);
			}
		}

		return true;
	}

	/**
	 * Undeletes the specified album if permissions are sufficient.
	 *
	 * @param array $albumIds List of IDs to undelete
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function undeleteAlbum(array $albumIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$album = $this->getAlbumData($albumIds);

		if (empty($options['skipPermissions']) && !$this->canDeleteAlbumData($album, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateAlbumAlbumState($album, 'visible', 'deleted');

		return true;
	}

	/**
	 * Internal helper to update the album_state of a collection of album items.
	 *
	 * @param array $album Information about the album to update
	 * @param string $newState New album state (visible, moderated, deleted)
	 * @param string|bool $expectedOldState If specified, only updates if the old state matches
	 */
	protected function _updateAlbumAlbumState(array $album, $newState, $expectedOldState = false)
	{
		switch ($newState)
		{
			case 'visible':
				switch (strval($expectedOldState))
				{
					case 'visible': return;
					case 'moderated': $logAction = 'approve'; break;
					case 'deleted': $logAction = 'undelete'; break;
					default: $logAction = 'undelete'; break;
				}
				break;

			case 'moderated':
				switch (strval($expectedOldState))
				{
					case 'visible': $logAction = 'unapprove'; break;
					case 'moderated': return;
					case 'deleted': $logAction = 'unapprove'; break;
					default: $logAction = 'unapprove'; break;
				}
				break;

			case 'deleted':
				switch (strval($expectedOldState))
				{
					case 'visible': $logAction = 'delete_soft'; break;
					case 'moderated': $logAction = 'delete_soft'; break;
					case 'deleted': return;
					default: $logAction = 'delete_soft'; break;
				}
				break;

			default: return;
		}

		foreach ($album AS $_album)
		{
			if ($expectedOldState && $_album['album_state'] != $expectedOldState)
			{
				continue;
			}

			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($_album);
			$dw->set('album_state', $newState);
			$dw->save();

			XenForo_Model_Log::logModeratorAction('xengallery_album', $dw->getMergedData(), $logAction);
		}
	}

	protected function _getLogChanges(XenForo_DataWriter $dw)
	{
		$newData = $dw->getMergedNewData();
		$oldData = $dw->getMergedExistingData();
		$changes = array();

		foreach ($newData AS $key => $newValue)
		{
			if (isset($oldData[$key]))
			{
				$changes[$key] = $oldData[$key];
			}
		}

		return $changes;
	}

	public function canSendActionAlert(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return (
			XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteAlbumAny')
			|| XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editAlbumAny')
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
}