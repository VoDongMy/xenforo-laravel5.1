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
class sonnb_XenGallery_AlertHandler_Album extends XenForo_AlertHandler_Abstract
{
	/**
	 * @var sonnb_XenGallery_Model_Album
	 */
	protected $_albumModel = null;

	/**
	 * @param array $contentIds
	 * @param XenForo_Model $model
	 * @param int $userId
	 * @param array $viewingUser
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
	{
		$albumModel = $this->_getAlbumModel();

		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Album::FETCH_USER,
			'followingUserId' => XenForo_Visitor::getUserId()
		);
		$albums = $albumModel->getAlbumsByIds($contentIds, $fetchOptions);
		$albums = $albumModel->prepareAlbums($albums, $fetchOptions);

		return $albums;
	}

	/**
	 * @param array $alert
	 * @param mixed $content
	 * @param array $viewingUser
	 * @return bool
	 */
	public function canViewAlert(array $alert, $content, array $viewingUser)
	{
		return $this->_getAlbumModel()->canViewAlbum($content, $errorPhraseKey, $viewingUser);
	}

	/**
	 * @param array $item
	 * @param $content
	 * @param array $viewingUser
	 * @return array
	 */
	protected function _prepareAlertBeforeAction(array $item, $content, array $viewingUser)
	{
		$item = parent::_prepareAlertBeforeAction($item, $content, $viewingUser);
		
		$item['extra_data'] = @unserialize($item['extra_data']);
		
		return $item;
	}

	/**
	 * @param string $contentType
	 * @param string $action
	 * @return string
	 */
	protected function _getDefaultTemplateTitle($contentType, $action)
	{
		return 'sonnb_xengallery_album_alert_' . $action;
	}

	/**
	 * 
	 * @return sonnb_XenGallery_Model_Album
	 */
	protected function _getAlbumModel()
	{
		if (!$this->_albumModel)
		{
			$this->_albumModel = XenForo_Model::create('sonnb_XenGallery_Model_Album');
		}

		return $this->_albumModel;
	}
}