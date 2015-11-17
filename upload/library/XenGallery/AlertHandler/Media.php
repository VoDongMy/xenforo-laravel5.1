<?php

class XenGallery_AlertHandler_Media extends XenForo_AlertHandler_Abstract
{
	protected $_mediaModel;
	protected $_attachmentModel;

	/**
	 * Fetches the content required by alerts.
	 *
	 * @param array $contentIds
	 * @param XenForo_Model_Alert $model Alert model invoking this
	 * @param integer $userId User ID the alerts are for
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
	{
		$mediaModel = $this->_getMediaModel();

		$media = $mediaModel->getMediaByIds($contentIds, array(
			'join' => XenGallery_Model_Media::FETCH_ATTACHMENT
				 | XenGallery_Model_Media::FETCH_CATEGORY
				 | XenGallery_Model_Media::FETCH_USER
				 | XenGallery_Model_Media::FETCH_ALBUM
		));
		
		foreach ($media AS $key => &$_media)
		{
			if (!$mediaModel->canViewMediaItem($_media, $null, $viewingUser))
			{
				unset($media[$key]);
			}
			else
			{
				$_media = $mediaModel->prepareMedia($_media);
			}
		}

		return $media;
	}

	/**
	* Determines if the media is viewable.
	* @see XenForo_AlertHandler_Abstract::canViewAlert()
	*/
	public function canViewAlert(array $alert, $content, array $viewingUser)
	{	
		return true;
	}

	/**
	 * @return XenGallery_Model_Media
	 */
	protected function _getMediaModel()
	{
		if (!$this->_mediaModel)
		{
			$this->_mediaModel = XenForo_Model::create('XenGallery_Model_Media');
		}

		return $this->_mediaModel;
	}

	/**
	 * @return XenForo_Model_Attachment
	 */
	protected function _getAttachmentModel()
	{
		if (!$this->_attachmentModel)
		{
			$this->_attachmentModel = XenForo_Model::create('XenForo_Model_Attachment');
		}
	
		return $this->_attachmentModel;
	}
}
