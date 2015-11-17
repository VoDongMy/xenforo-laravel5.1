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
class sonnb_XenGallery_NewsFeedHandler_Photo extends XenForo_NewsFeedHandler_Abstract
{
	protected $_photoModel = null;

	public function getContentByIds(array $contentIds, $model, array $viewingUser)
	{
		$photoModel = $this->_getPhotoModel();

		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Photo::FETCH_USER |
				sonnb_XenGallery_Model_Photo::FETCH_ALBUM |
				sonnb_XenGallery_Model_Photo::FETCH_DATA,
			'followingUserId' => XenForo_Visitor::getUserId()
		);
		
		$photos = $photoModel->getContentsByIds($contentIds, $fetchOptions);
		$photos = $photoModel->prepareContents($photos, $fetchOptions, $viewingUser);
		
		return $photos;
	}

	public function canViewNewsFeedItem(array $item, $content, array $viewingUser)
	{
		return $this->_getPhotoModel()->canViewContentAndContainer($content, $content['album'], $errorPhraseKey, $viewingUser);
	}
	
	protected function _getDefaultTemplateTitle($contentType, $action)
	{
		return 'sonnb_xengallery_news_feed_photo_' . $action;
	}
	
	protected function _prepareNewsFeedItemBeforeAction(array $item, $content, array $viewingUser)
	{
		$item = parent::_prepareNewsFeedItemBeforeAction($item, $content, $viewingUser);
		
		$item['extra_data'] = @unserialize($item['extra_data']);
		
		return $item;
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