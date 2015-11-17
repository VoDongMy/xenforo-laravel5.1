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
class sonnb_XenGallery_NewsFeedHandler_Video extends XenForo_NewsFeedHandler_Abstract
{
	protected $_videoModel = null;

	public function getContentByIds(array $contentIds, $model, array $viewingUser)
	{
		$videoModel = $this->_getVideoModel();

		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Video::FETCH_USER |
				sonnb_XenGallery_Model_Video::FETCH_ALBUM |
				sonnb_XenGallery_Model_Video::FETCH_DATA,
			'followingUserId' => XenForo_Visitor::getUserId()
		);
		
		$videos = $videoModel->getContentsByIds($contentIds, $fetchOptions);
		$videos = $videoModel->prepareContents($videos, $fetchOptions, $viewingUser);
		
		return $videos;
	}

	public function canViewNewsFeedItem(array $item, $content, array $viewingUser)
	{
		return $this->_getVideoModel()->canViewContentAndContainer($content, $content['album'], $errorPhraseKey, $viewingUser);
	}
	
	protected function _getDefaultTemplateTitle($contentType, $action)
	{
		return 'sonnb_xengallery_news_feed_video_' . $action;
	}
	
	protected function _prepareNewsFeedItemBeforeAction(array $item, $content, array $viewingUser)
	{
		$item = parent::_prepareNewsFeedItemBeforeAction($item, $content, $viewingUser);
		
		$item['extra_data'] = @unserialize($item['extra_data']);
		
		return $item;
	}

	/**
	 * 
	 * @return sonnb_XenGallery_Model_Video
	 */
	protected function _getVideoModel()
	{
		if (!$this->_videoModel)
		{
			$this->_videoModel = XenForo_Model::create('sonnb_XenGallery_Model_Video');
		}

		return $this->_videoModel;
	}
}