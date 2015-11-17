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
class sonnb_XenGallery_Model_InlineMod_Content extends XenForo_Model
{
	public $enableLogging = true;

	public function canEditContents(array $contentIds, &$errorKey = '', $viewingUser = null)
	{
		return $this->getContentModel()->canEditAnyContent($viewingUser);
	}

	public function canEditContentsData(array $contentIds, &$errorKey = '', $viewingUser = null)
	{
		return $this->getContentModel()->canEditAnyContent($viewingUser);
	}

	public function canDeleteContents(array $contentIds, $deleteType = 'soft', &$errorKey = '', array $viewingUser = null)
	{
		return $this->getContentModel()->canDeleteAnyContent($deleteType, $viewingUser);
	}

	public function canDeleteContentsData(array $contentIds, $deleteType, &$errorKey = '', array $viewingUser = null)
	{
		return $this->getContentModel()->canDeleteAnyContent($deleteType, $viewingUser);
	}

	public function deleteContents(array $contentIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$options = array_merge(
			array(
				'deleteType' => '',
				'reason' => ''
			), $options
		);

		if (!$options['deleteType'])
		{
			throw new XenForo_Exception('No deletion type specified.');
		}

		$contents = $this->getContentsAndParentData($contentIds, $viewingUser);

		if (!$this->canDeleteContentsData($contents, $options['deleteType'], $errorKey, $viewingUser))
		{
			return false;
		}

		foreach ($contents AS $content)
		{
			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Content', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($content);
			if (!$dw->get('content_id'))
			{
				continue;
			}

			if ($options['deleteType'] === 'hard')
			{
				$dw->delete();

				if ($this->enableLogging)
				{
					XenForo_Model_Log::logModeratorAction('sonnb_xengallery_'.$content['content_type'], $content, 'delete_hard');
				}
			}
			else
			{
				$dw->setExtraData(sonnb_XenGallery_DataWriter_Content::DATA_DELETE_REASON, $options['reason']);
				$dw->set('content_state', 'deleted');
				$dw->save();

				if ($this->enableLogging)
				{
					XenForo_Model_Log::logModeratorAction('sonnb_xengallery_'.$content['content_type'], $content, 'delete_soft', array('reason' => $options['reason']));
				}
			}
		}

		return true;
	}

	public function canUndeleteContents(array $contentIds, &$errorKey = '', array $viewingUser = null)
	{
		return $this->getContentModel()->canDeleteAnyContent('soft', $viewingUser);
	}

	public function canUndeleteContentsData(array $contents, &$errorKey = '', array $viewingUser = null)
	{
		return $this->getContentModel()->canDeleteAnyContent('soft', $viewingUser);
	}

	public function undeleteContents(array $contentIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$contents = $this->getContentsAndParentData($contentIds, $viewingUser);

		if (!$this->canUndeleteContentsData($contents, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateContentsState($contents, 'visible', 'deleted');

		return true;
	}

	public function canApproveUnapproveContents(array $contentIds, &$errorKey = '', array $viewingUser = null)
	{
		return $this->getContentModel()->canEditAnyContent($viewingUser);
	}

	public function canApproveUnapproveContentsData(array $contents, &$errorKey = '', array $viewingUser = null)
	{
		return $this->getContentModel()->canEditAnyContent($viewingUser);
	}

	public function approveContents(array $contentIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$contents = $this->getContentsAndParentData($contentIds, $viewingUser);

		if (!$this->canApproveUnapproveContentsData($contents, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateContentsState($contents, 'visible', 'moderated');

		return true;
	}


	public function unapproveContents(array $contentIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$contents = $this->getContentsAndParentData($contentIds, $viewingUser);

		if (!$this->canApproveUnapproveContentsData($contents, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateContentsState($contents, 'moderated', 'visible');

		return true;
	}

	public function canMoveContents(array $contentIds, $targetAlbumId, &$errorKey = '', array $viewingUser = null)
	{
		return $this->_getAlbumModel()->canEditAnyAlbum($viewingUser);
	}

	public function canMoveContentsData(array $contents, $targetAlbumId, &$errorKey = '', array $viewingUser = null)
	{
		return $this->_getAlbumModel()->canEditAnyAlbum($viewingUser);
	}

	public function moveContents(array $contentIds, $targetAlbumId, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$contents = $this->getContentsAndParentData($contentIds, $viewingUser);

		if (!$this->canMoveContentsData($contents, $targetAlbumId, $errorKey, $viewingUser))
		{
			return false;
		}
		if ($targetAlbumId < 1)
		{
			return false;
		}

		$options = array_merge(
			array(
				'checkSameAlbum' => true
			),
			$options
		);

		if ($options['checkSameAlbum'])
		{
			$allSameAlbum = true;
			foreach ($contents AS $content)
			{
				if ($content['album_id'] != $targetAlbumId)
				{
					$allSameAlbum = false;
					break;
				}
			}

			if ($allSameAlbum)
			{
				$errorKey = 'sonnb_xengallery_all_Contents_in_destination_album';
				return false;
			}
		}

		$updateOptions = array('album_id' => $targetAlbumId);

		$this->_updateContentsBulk($contents, $updateOptions);

		if ($this->enableLogging)
		{
			foreach ($contents AS $content)
			{
				if ($content['album_id'] != $targetAlbumId)
				{
					XenForo_Model_Log::logModeratorAction('sonnb_xengallery_'.$content['content_type'], $content, 'move', array('from' => $content['album']['title']));
				}
			}
		}

		return true;
	}

	protected function _updateContentsState(array $contents, $newState, $expectedOldState = false)
	{
		switch ($newState)
		{
			case 'visible':
				switch (strval($expectedOldState))
				{
					case 'visible':
						return;
					case 'moderated':
						$logAction = 'approve';
						break;
					case 'deleted':
						$logAction = 'undelete';
						break;
					default:
						$logAction = 'undelete';
						break;
				}
				break;

			case 'moderated':
				switch (strval($expectedOldState))
				{
					case 'visible':
						$logAction = 'unapprove';
						break;
					case 'moderated':
						return;
					case 'deleted':
						$logAction = 'unapprove';
						break;
					default:
						$logAction = 'unapprove';
						break;
				}
				break;

			case 'deleted':
				switch (strval($expectedOldState))
				{
					case 'visible':
						$logAction = 'delete_soft';
						break;
					case 'moderated':
						$logAction = 'delete_soft';
						break;
					case 'deleted':
						return;
					default:
						$logAction = 'delete_soft';
						break;
				}
				break;

			default:
				return;
		}

		foreach ($contents AS $content)
		{
			if ($expectedOldState && $content['content_state'] != $expectedOldState)
			{
				continue;
			}

			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Content', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($content);
			$dw->set('content_state', $newState);

			$dw->save();

			if ($this->enableLogging)
			{
				XenForo_Model_Log::logModeratorAction('sonnb_xengallery_'.$content['content_type'], $content, $logAction);
			}
		}
	}

	protected function _updateContentsBulk(array $contents, array $updates, $logAction = '')
	{
		foreach ($contents AS $content)
		{
			$dw = XenForo_DataWriter::create('sonnb_XenGallery_DataWriter_Content', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($content);
			$dw->bulkSet($updates);

			$dw->save();

			if ($dw->hasChanges() && $this->enableLogging && $logAction)
			{
				XenForo_Model_Log::logModeratorAction('sonnb_xengallery_'.$content['content_type'], $content, $logAction);
			}
		}
	}

	public function getContentsAndParentData(array $contentIds, array $viewingUser = null)
	{
		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Content::FETCH_USER
					| sonnb_XenGallery_Model_Content::FETCH_ALBUM
					| sonnb_XenGallery_Model_Content::FETCH_DATA
		);

		$contents = $this->getContentModel()->getContentsByIds($contentIds, $fetchOptions);

		return $this->getContentModel()->prepareContents($contents, $fetchOptions, $viewingUser);
	}

	/**
	 * @return sonnb_XenGallery_Model_Content
	 */
	protected function getContentModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Content');
	}

	/**
	 * @return sonnb_XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Album');
	}

	/**
	 * @return sonnb_XenGallery_Model_Gallery
	 */
	protected function _getGalleryModel()
	{
		return $this->getModelFromCache('sonnb_XenGallery_Model_Gallery');
	}
}