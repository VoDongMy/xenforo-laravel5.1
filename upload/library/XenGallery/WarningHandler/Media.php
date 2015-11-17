<?php

class XenGallery_WarningHandler_Media extends XenForo_WarningHandler_Abstract
{
	protected function _canView(array $content, array $viewingUser)
	{
		return $this->_getMediaModel()->canViewMediaItem($content, $null, $viewingUser);
	}

	protected function _canWarn($userId, array $content, array $viewingUser)
	{
		return $this->_getMediaModel()->canWarnMediaItem($content, $null, $viewingUser);
	}

	protected function _canDeleteContent(array $content, array $viewingUser)
	{
		return $this->_getMediaModel()->canDeleteMedia($content);
	}

	protected function _getContent(array $contentIds, array $viewingUser)
	{
		$mediaModel = $this->_getMediaModel();

		$conditions = array(
			'media_id' => $contentIds,
			'privacyUserId' => $viewingUser['user_id'],
			'deleted' => $mediaModel->canViewDeletedMedia($null, $viewingUser),
			'moderated' => $mediaModel->canViewUnapprovedMedia($null, $viewingUser),
			'viewAlbums' => XenForo_Permission::hasPermission($viewingUser['permissions'], 'xengallery', 'viewAlbums'),
			'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($viewingUser)
		);
		$fetchOptions = array(
			'join' => XenGallery_Model_Media::FETCH_USER
				| XenGallery_Model_Media::FETCH_ALBUM
				| XenGallery_Model_Media::FETCH_CATEGORY
				| XenGallery_Model_Media::FETCH_PRIVACY
		);

		return $mediaModel->getMedia($conditions, $fetchOptions);
	}

	public function getContentTitle(array $content)
	{
		return $content['media_title'];
	}

	public function getContentTitleForDisplay($title)
	{
		// will be escaped in template
		return new XenForo_Phrase('xengallery_media_x', array('media' => $title), false);
	}

	public function getContentUrl(array $content, $canonical = false)
	{
		return XenForo_Link::buildPublicLink(($canonical ? 'canonical:' : '') . 'xengallery', $content);
	}

	protected function _warn(array $warning, array $content, $publicMessage, array $viewingUser)
	{
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Media', XenForo_DataWriter::ERROR_SILENT);
		if ($dw->setExistingData($content))
		{
			$dw->set('warning_id', $warning['warning_id']);
			$dw->set('warning_message', $publicMessage);
			$dw->save();
		}
	}

	protected function _reverseWarning(array $warning, array $content)
	{
		if ($content)
		{
			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Media', XenForo_DataWriter::ERROR_SILENT);
			if ($dw->setExistingData($content))
			{
				$dw->set('warning_id', 0);
				$dw->set('warning_message', '');
				$dw->save();
			}
		}
	}

	protected function _deleteContent(array $content, $reason, array $viewingUser)
	{
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Media', XenForo_DataWriter::ERROR_SILENT);
		if ($dw->setExistingData($content))
		{
			$dw->setExtraData(XenGallery_DataWriter_Media::DATA_DELETE_REASON, $reason);
			$dw->set('media_state', 'deleted');
			$dw->save();
		}
	}

	public function canPubliclyDisplayWarning()
	{
		return true;
	}

	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		return XenForo_Model::create('XenGallery_Model_Media');
	}
}