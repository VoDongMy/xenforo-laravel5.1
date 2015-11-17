<?php

class XenGallery_AlertHandler_Album extends XenForo_AlertHandler_Abstract
{
	protected $_albumModel;

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
		$albumModel = $this->_getAlbumModel();

		$albums = $albumModel->getAlbumsByIds($contentIds, array(
			'join' => XenGallery_Model_Album::FETCH_USER
		));
		
		foreach ($albums AS $key => &$album)
		{
			$album = $albumModel->prepareAlbumWithPermissions($album);

			if (!$albumModel->canViewAlbum($album, $null, $viewingUser))
			{
				unset($albums[$key]);
			}
		}
	
		return $albums;
	}

	/**
	* Determines if the album is viewable.
	* @see XenForo_AlertHandler_Abstract::canViewAlert()
	*/
	public function canViewAlert(array $alert, $content, array $viewingUser)
	{
		if ($this->_getAlbumModel()->canViewAlbum($content, $null, $viewingUser))
		{
			return true;
		}
		
		return false;
	}

	/**
	 * @return XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		if (!$this->_albumModel)
		{
			$this->_albumModel = XenForo_Model::create('XenGallery_Model_Album');
		}

		return $this->_albumModel;
	}	
}
