<?php

class XenGallery_Model_InlineMod_Media extends XenForo_Model
{
	/**
	 * From a List of IDs, gets info about the media.
	 *
	 * @param array $mediaIds List of IDs
	 *
	 * @return array Format: list of media
	 */
	public function getMediaData(array $mediaIds, array $fetchOptions = array())
	{
		if (!$mediaIds)
		{
			return array();
		}

		return $this->_getMediaModel()->getMediaByIds($mediaIds, $fetchOptions);
	}

	/**
	 * Determines if the selected media IDs can be deleted.
	 *
	 * @param array $mediaIds List of IDs check
	 * @param string $deleteType The type of deletion being requested (soft or hard)
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canDeleteMedia(array $mediaIds, $deleteType = 'soft', &$errorKey = '', array $viewingUser = null)
	{
		$media = $this->getMediaData($mediaIds);
		return $this->canDeleteMediaData($media, $deleteType, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected media data can be deleted.
	 *
	 * @param array $media List of data to be deleted
	 * @param string $deleteType Type of deletion (soft or hard)
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canDeleteMediaData(array $media, $deleteType, &$errorKey = '', array $viewingUser = null)
	{
		if (!$media)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);
		$mediaModel = $this->_getMediaModel();

		foreach ($media AS $_media)
		{
			if ($_media['media_state'] != 'deleted' && !$mediaModel->canDeleteMedia($_media, $deleteType, $errorKey, $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Deletes the specified media if permissions are sufficient.
	 *
	 * @param array $mediaIds List of IDs to delete
	 * @param array $options Options that control the delete. Supports deleteType (soft or hard).
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function deleteMedia(array $mediaIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		if (!$mediaIds)
		{
			return false;
		}

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
			'join' => XenGallery_Model_Media::FETCH_USER
				| XenGallery_Model_Media::FETCH_ATTACHMENT
				| XenGallery_Model_Media::FETCH_CATEGORY
				| XenGallery_Model_Media::FETCH_ALBUM
		);

		$media = $this->getMediaData($mediaIds, $fetchOptions);

		if (empty($options['skipPermissions']) && !$this->canDeleteMediaData($media, $options['deleteType'], $errorKey, $viewingUser))
		{
			return false;
		}

		foreach ($media AS $_media)
		{
			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
			$dw->setExistingData($_media);
			if (!$dw->get('media_id'))
			{
				continue;
			}

			if ($options['deleteType'] == 'hard')
			{
				$dw->delete();
			}
			else
			{
				$dw->setExtraData(XenGallery_DataWriter_Media::DATA_DELETE_REASON, $options['reason']);
				$dw->set('media_state', 'deleted');
				$dw->save();
			}

			XenForo_Model_Log::logModeratorAction('xengallery_media', $dw->getMergedData(), 'delete_' . $options['deleteType'], array('reason' => $options['reason']));

			if ($_media['media_state'] == 'visible' && $options['authorAlert'])
			{
				$this->_getMediaModel()->sendAuthorAlert(
					$_media, 'xengallery_media', 'delete', $options
				);
			}
		}

		return true;
	}

	public function canRemoveWatermark(array $mediaIds, &$errorKey = '', array $viewingUser = null)
	{
		$media = $this->getMediaData($mediaIds);
		return $this->canRemoveWatermarkData($media, $errorKey, $viewingUser);
	}

	public function canRemoveWatermarkData(array $media, &$errorKey = '', array $viewingUser = null)
	{
		if (!$media)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);
		$watermarkModel = $this->_getWatermarkModel();

		if (isset($media['media_id']))
		{
			if (!$watermarkModel->canRemoveWatermark($media,  $errorKey, $viewingUser))
			{
				return false;
			}
		}
		else
		{
			foreach ($media AS $_media)
			{
				if (!$watermarkModel->canRemoveWatermark($_media,  $errorKey, $viewingUser))
				{
					return false;
				}
			}
		}

		return true;
	}

	public function removeWatermarkFromImage(array $mediaIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$media = $this->getMediaData($mediaIds, array('join' => XenGallery_Model_Media::FETCH_ATTACHMENT));

		foreach ($media AS $mediaId => $_media)
		{
			if (!$this->canRemoveWatermarkData($_media))
			{
				continue;
			}

			if ($this->_getWatermarkModel()->removeWatermarkFromImage($_media))
			{
				XenForo_Model_Log::logModeratorAction('xengallery_media', $_media, 'watermark_remove');
			}
		}

		return true;
	}

	public function canAddWatermark(array $mediaIds, &$errorKey = '', array $viewingUser = null)
	{
		$media = $this->getMediaData($mediaIds);
		return $this->canAddWatermarkData($media, $errorKey, $viewingUser);
	}

	public function canAddWatermarkData(array $media, &$errorKey = '', array $viewingUser = null)
	{
		if (!$media)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);
		$watermarkModel = $this->_getWatermarkModel();

		if (isset($media['media_id']))
		{
			if (!$watermarkModel->canAddWatermark($media,  $errorKey, $viewingUser))
			{
				return false;
			}
		}
		else
		{
			foreach ($media AS $mediaId => $_media)
			{
				if (!$watermarkModel->canAddWatermark($_media,  $errorKey, $viewingUser))
				{
					return false;
				}
			}
		}

		return true;
	}

	public function addWatermarkToImage(array $mediaIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$media = $this->getMediaData($mediaIds, array('join' => XenGallery_Model_Media::FETCH_ATTACHMENT));

		foreach ($media AS $mediaId => $_media)
		{
			if (!$this->canAddWatermarkData($_media))
			{
				continue;
			}

			$imageInfo = $this->_getWatermarkModel()->addWatermarkToImage($_media);
			if ($imageInfo)
			{
				$this->_getMediaModel()->rebuildThumbnail($_media, $imageInfo);
				XenForo_Model_Log::logModeratorAction('xengallery_media', $_media, 'watermark_add');
			}
		}

		return true;
	}

	/**
	 * Determines if the selected media IDs can be edited.
	 *
	 * @param array $mediaIds List of IDs to check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canEditMedia(array $mediaIds, &$errorKey = '', array $viewingUser = null)
	{
		$media = $this->getMediaData($mediaIds);
		return $this->canEditMediaData($media, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected media data can be edited.
	 *
	 * @param array $media List of data to be edited
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canEditMediaData(array $media, &$errorKey = '', array $viewingUser = null)
	{
		if (!$media)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);
		$mediaModel = $this->_getMediaModel();

		foreach ($media AS $_media)
		{
			if (!$mediaModel->canEditMedia($_media,  $errorKey, $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Deletes the specified media if permissions are sufficient.
	 *
	 * @param array $mediaIds List of IDs to delete
	 * @param array $options Options that control the delete. Supports deleteType (soft or hard).
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function editMedia(array $mediaIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
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

		$media = $this->getMediaData(array_keys($mediaIds), $fetchOptions);

		if (empty($options['skipPermissions']) && !$this->canEditMediaData($media, $errorKey, $viewingUser))
		{
			return false;
		}

		foreach ($media AS $mediaId => $_media)
		{
			if (!isset($options['input'][$mediaId]))
			{
				continue;
			}

			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Media', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($mediaId);

			$dw->bulkSet($options['input'][$mediaId]);
			if (!$dw->get('media_id'))
			{
				continue;
			}

			$dw->save();

			$changes = $this->_getLogChanges($dw);
			if ($changes)
			{
				XenForo_Model_Log::logModeratorAction('xengallery_media', $dw->getMergedData(), 'edit', $changes);
			}

			if ($_media['media_state'] == 'visible' && $options['authorAlert'])
			{
				$this->_getMediaModel()->sendAuthorAlert(
					$_media, 'xengallery_media', 'edit', $options
				);
			}
		}

		return true;
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

	/**
	 * Determines if the selected media IDs can be moved.
	 *
	 * @param array $mediaIds List of IDs to check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canMoveMedia(array $mediaIds, &$errorKey = '', array $viewingUser = null)
	{
		$media = $this->getMediaData($mediaIds);
		return $this->canMoveMediaData($media, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected media data can be moveed.
	 *
	 * @param array $media List of data to be moveed
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canMoveMediaData(array $media, &$errorKey = '', array $viewingUser = null)
	{
		if (!$media)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);
		$mediaModel = $this->_getMediaModel();

		foreach ($media AS $key => $_media)
		{
			if (!$mediaModel->canMoveMedia($_media,  $errorKey, $viewingUser))
			{
				unset ($media[$key]);
			}
		}

		return true;
	}

	/**
	 * Moves the specified media if permissions are sufficient.
	 *
	 * @param array $mediaIds List of IDs to delete
	 * @param array $options Options that control the delete. Supports deleteType (soft or hard).
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function moveMedia(array $media, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$options = array_merge(
			array(
				'album' => array(),
				'category' => array(),
				'skipPermissions' => false
			), $options
		);

		if (empty($options['skipPermissions']) && !$this->canMoveMediaData($media, $errorKey, $viewingUser))
		{
			return false;
		}

		foreach ($media AS $_media)
		{
			$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media');
			$mediaWriter->setExistingData($_media);

			if ($options['album'] || $options['category'])
			{
				if ($options['album'])
				{
					$mediaWriter->bulkSet(array(
						'album_id' => $options['album']['album_id'],
						'category_id' => 0,
						'media_privacy' => $options['album']['access_type']
					));

					$container = 'album';
				}
				else
				{
					$mediaWriter->bulkSet(array(
						'category_id' => $options['category']['category_id'],
						'album_id' => 0,
						'media_privacy' => 'category'
					));

					$container = 'category';
				}

				$mediaWriter->save();

				XenForo_Model_Log::logModeratorAction('xengallery_media', $mediaWriter->getMergedData(), 'move_to_' . $container, array('title' => $options[$container][$container . '_title']));
			}
		}

		return true;
	}

	/**
	 * Undeletes the specified media if permissions are sufficient.
	 *
	 * @param array $mediaIds List of IDs to undelete
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function undeleteMedia(array $mediaIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$media = $this->getMediaData($mediaIds);

		if (empty($options['skipPermissions']) && !$this->canDeleteMediaData($media, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateMediaMediaState($media, 'visible', 'deleted');

		return true;
	}

	/**
	 * Determines if the selected media IDs can be approved.
	 *
	 * @param array $mediaIds List of IDs to check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canApproveMedia(array $mediaIds, &$errorKey = '', array $viewingUser = null)
	{
		$media = $this->getMediaData($mediaIds);
		return $this->canApproveMediaData($media, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected media data can be approved.
	 *
	 * @param array $media List of data to be checked
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canApproveMediaData(array $media, &$errorKey = '', array $viewingUser = null)
	{
		if (!$media)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);
		$mediaModel = $this->_getMediaModel();

		foreach ($media AS $_media)
		{
			if ($_media['media_state'] == 'moderated' && !$mediaModel->canApproveMedia($_media,$errorKey, $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Approves the specified media if permissions are sufficient.
	 *
	 * @param array $mediaIds List of IDs to approve
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function approveMedia(array $mediaIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$media = $this->getMediaData($mediaIds);

		if (empty($options['skipPermissions']) && !$this->canApproveMediaData($media, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateMediaMediaState($media, 'visible', 'moderated');

		return true;
	}

	/**
	 * Determines if the selected media IDs can be unapproved.
	 *
	 * @param array $mediaIds List of IDs to check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canUnapproveMedia(array $mediaIds, &$errorKey = '', array $viewingUser = null)
	{
		$media = $this->getMediaData($mediaIds);
		return $this->canUnapproveMediaData($media, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected media data can be unapproved.
	 *
	 * @param array $media List of data to be checked
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canUnapproveMediaData(array $media, &$errorKey = '', array $viewingUser = null)
	{
		if (!$media)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);
		$mediaModel = $this->_getMediaModel();

		foreach ($media AS $_media)
		{
			if ($_media['media_state'] == 'visible' && !$mediaModel->canUnapproveMedia($_media, $errorKey, $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Unapproves the specified media if permissions are sufficient.
	 *
	 * @param array $mediaIds List of IDs to unapprove
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function unapproveMedia(array $mediaIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$media = $this->getMediaData($mediaIds);

		if (empty($options['skipPermissions']) && !$this->canUnapproveMediaData($media, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateMediaMediaState($media, 'moderated', 'visible');

		return true;
	}

	/**
	 * Internal helper to update the media_state of a collection of media items.
	 *
	 * @param array $media Information about the media to update
	 * @param string $newState New media state (visible, moderated, deleted)
	 * @param string|bool $expectedOldState If specified, only updates if the old state matches
	 */
	protected function _updateMediaMediaState(array $media, $newState, $expectedOldState = false)
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

		foreach ($media AS $_media)
		{
			if ($expectedOldState && $_media['media_state'] != $expectedOldState)
			{
				continue;
			}

			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Media', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($_media);
			$dw->set('media_state', $newState);
			$dw->save();

			XenForo_Model_Log::logModeratorAction('xengallery_media', $dw->getMergedData(), $logAction);
		}
	}

	public function canSendActionAlert(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return (
			XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'deleteAny')
			|| XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'editAny')
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
	 * @return XenGallery_Model_Watermark
	 */
	protected function _getWatermarkModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Watermark');
	}
}