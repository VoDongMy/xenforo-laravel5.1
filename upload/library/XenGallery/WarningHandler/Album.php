<?php

class XenGallery_WarningHandler_Album extends XenForo_WarningHandler_Abstract
{
	protected function _canView(array $content, array $viewingUser)
	{
		$albumModel = $this->_getAlbumModel();
		$content = $albumModel->prepareAlbumWithPermissions($content);
		return $albumModel->canViewAlbum($content, $null, $viewingUser);
	}

	protected function _canWarn($userId, array $content, array $viewingUser)
	{
		return $this->_getAlbumModel()->canWarnAlbum($content, $null, $viewingUser);
	}

	protected function _canDeleteContent(array $content, array $viewingUser)
	{
		return $this->_getAlbumModel()->canDeleteAlbum($content);
	}

	protected function _getContent(array $contentIds, array $viewingUser)
	{
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');
		$albumModel = $this->_getAlbumModel();

		$conditions = array(
			'privacyUserId' => $viewingUser['user_id'],
			'deleted' => $albumModel->canViewDeletedAlbums($null, $viewingUser),
			'viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($viewingUser),
			'album_id' => $contentIds
		);
		$fetchOptions = array(
			'join' => XenGallery_Model_Album::FETCH_PRIVACY
				| XenGallery_Model_Album::FETCH_USER
		);

		return $albumModel->getAlbums($conditions, $fetchOptions);
	}

	public function getContentTitle(array $content)
	{
		return $content['album_title'];
	}

	public function getContentTitleForDisplay($title)
	{
		// will be escaped in template
		return new XenForo_Phrase('xengallery_album_x', array('album' => $title), false);
	}

	public function getContentUrl(array $content, $canonical = false)
	{
		return XenForo_Link::buildPublicLink(($canonical ? 'canonical:' : '') . 'xengallery/albums', $content);
	}

	protected function _warn(array $warning, array $content, $publicMessage, array $viewingUser)
	{
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);
		if ($dw->setExistingData($content))
		{
			$dw->set('album_warning_id', $warning['warning_id']);
			$dw->set('album_warning_message', $publicMessage);
			$dw->save();
		}
	}

	protected function _reverseWarning(array $warning, array $content)
	{
		if ($content)
		{
			$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);
			if ($dw->setExistingData($content))
			{
				$dw->set('album_warning_id', 0);
				$dw->set('album_warning_message', '');
				$dw->save();
			}
		}
	}

	protected function _deleteContent(array $content, $reason, array $viewingUser)
	{
		$dw = XenForo_DataWriter::create('XenGallery_DataWriter_Album', XenForo_DataWriter::ERROR_SILENT);
		if ($dw->setExistingData($content))
		{
			$dw->setExtraData(XenGallery_DataWriter_Album::DATA_DELETE_REASON, $reason);
			$dw->set('album_state', 'deleted');
			$dw->save();
		}
	}

	public function canPubliclyDisplayWarning()
	{
		return true;
	}

	/**
	 * @return XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		return XenForo_Model::create('XenGallery_Model_Album');
	}
}