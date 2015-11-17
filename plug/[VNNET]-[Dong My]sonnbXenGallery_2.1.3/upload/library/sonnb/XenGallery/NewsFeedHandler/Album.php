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
class sonnb_XenGallery_NewsFeedHandler_Album extends XenForo_NewsFeedHandler_Abstract
{
	/**
	 * @var sonnb_XenGallery_Model_Album
	 */
	protected $_albumModel = null;

	/**
	 * @var sonnb_XenGallery_Model_Photo
	 */
	protected $_photoModel = null;

	/**
	 * @var sonnb_XenGallery_Model_Video
	 */
	protected $_videoModel = null;

	/**
	 * @param array $contentIds
	 * @param XenForo_Model_NewsFeed $model
	 * @param array $viewingUser
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, array $viewingUser)
	{
		$albumModel = $this->_getAlbumModel();

		$fetchOptions = array(
			'join' => sonnb_XenGallery_Model_Album::FETCH_USER,
			'followingUserId' => XenForo_Visitor::getUserId()
		);
		
		$albums = $albumModel->getAlbumsByIds($contentIds, $fetchOptions);
		$albums = $albumModel->prepareAlbums($albums, $fetchOptions);
		$albums = $albumModel->attachContentsToAlbums($albums);

		foreach ($albums as $albumId => $album)
		{
			if (empty($album['contents']))
			{
				unset($albums[$albumId]);
			}
		}

		return $albums;
	}

	/**
	 * @param array $item
	 * @param mixed $content
	 * @param array $viewingUser
	 * @return bool
	 */
	public function canViewNewsFeedItem(array $item, $content, array $viewingUser)
	{
		return $this->_getAlbumModel()->canViewAlbum($content, $errorPhraseKey, $viewingUser);
	}

	/**
	 * @param array $item
	 * @param $content
	 * @param array $viewingUser
	 * @return array
	 */
	protected function _prepareNewsFeedItemBeforeAction(array $item, $content, array $viewingUser)
	{
		$item = parent::_prepareNewsFeedItemBeforeAction($item, $content, $viewingUser);
		
		$item['extra_data'] = @unserialize($item['extra_data']);
		
		if ($item['action'] === 'add_photo')
		{
			if (!empty($item['extra_data']['photoIds']))
			{
				$fetchOptions = array(
					'join' => sonnb_XenGallery_Model_Photo::FETCH_DATA
				);
				$photos = $this->_getPhotoModel()->getContentsByIds($item['extra_data']['photoIds'], $fetchOptions);
				$photos = $this->_getPhotoModel()->prepareContents($photos, $fetchOptions);
				
				foreach ($photos as $photoId=>$photo)
				{
					if (!$photo['canView'])
					{
						unset($photos[$photoId]);
					}
				}
				
				$item['content']['newPhotos'] = $photos;
			}
		}
		if ($item['action'] === 'add_video')
		{
			if (!empty($item['extra_data']['videoIds']))
			{
				$fetchOptions = array(
					'join' => sonnb_XenGallery_Model_Video::FETCH_DATA
				);
				$videos = $this->_getVideoModel()->getContentsByIds($item['extra_data']['videoIds'], $fetchOptions);
				$videos = $this->_getVideoModel()->prepareContents($videos, $fetchOptions);

				foreach ($videos as $videoId=>$video)
				{
					if (!$video['canView'])
					{
						unset($videos[$videoId]);
					}
				}

				$item['content']['newVideos'] = $videos;
			}
		}
		
		return $item;
	}
	
	protected function _getDefaultTemplateTitle($contentType, $action)
	{
		return 'sonnb_xengallery_news_feed_album_' . $action;
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