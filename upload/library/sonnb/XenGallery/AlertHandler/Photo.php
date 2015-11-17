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
class sonnb_XenGallery_AlertHandler_Photo extends XenForo_AlertHandler_Abstract
{
	/**
	 * @var sonnb_XenGallery_Model_Photo
	 */
	protected $_photoModel = null;

	/**
	 * @param array $contentIds
	 * @param XenForo_Model_Alert $model
	 * @param int $userId
	 * @param array $viewingUser
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
	{
		$photoModel = $this->_getPhotoModel();

		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Photo::FETCH_USER |
						sonnb_XenGallery_Model_Photo::FETCH_ALBUM |
						sonnb_XenGallery_Model_Photo::FETCH_DATA,
			'followingUserId' => XenForo_Visitor::getUserId()
		);
		
		$photos = $photoModel->getContentsByIds($contentIds, $fetchOptions);
		$photos = $photoModel->prepareContents($photos, $fetchOptions);

		return $photos;
	}

	/**
	 * @param array $alert
	 * @param mixed $content
	 * @param array $viewingUser
	 * @return bool
	 */
	public function canViewAlert(array $alert, $content, array $viewingUser)
	{
		return $this->_getPhotoModel()->canViewContentAndContainer($content, $content['album'], $errorPhraseKey, $viewingUser);
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
		return 'sonnb_xengallery_photo_alert_' . $action;
	}

	/**
	 * 
	 * @return sonnb_XenGallery_Model_Photo
	 */
	protected function _getPhotoModel()
	{
		if (!$this->_photoModel)
		{
			$this->_photoModel = XenForo_Model::create('sonnb_XenGallery_Model_Photo');
		}

		return $this->_photoModel;
	}
}