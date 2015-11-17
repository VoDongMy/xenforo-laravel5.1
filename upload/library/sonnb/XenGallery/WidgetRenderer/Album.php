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
class sonnb_XenGallery_WidgetRenderer_Album extends WidgetFramework_WidgetRenderer
{
	/**
	 * @return array
	 */
	protected function _getConfiguration ()
	{
		return array(
			'name' => '[Gallery] Top Albums',
			'useCache' => true,
			'useUserCache' => true,
			'cacheSeconds' => 300,
			'options' => array(
				'type' => XenForo_Input::STRING,
				'wrapper' => XenForo_Input::UINT,
				'limit' => XenForo_Input::UINT,
				'category_id' => XenForo_Input::UINT,
				'collection_id' => XenForo_Input::UINT,
				'likes' => XenForo_Input::UINT,
				'view_count' => XenForo_Input::UINT,
				'comment_count' => XenForo_Input::UINT,
				'user_id' => XenForo_Input::STRING,
				'album_streams' => XenForo_Input::STRING,
				'album_location' => XenForo_Input::STRING,
				'thumbnail_width' => XenForo_Input::UINT,
				'thumbnail_height' => XenForo_Input::UINT,
				'title_limit' => XenForo_Input::UINT,
				'enable_scrollable' => XenForo_Input::UINT,
				'disableWrapper' => XenForo_Input::UINT,
				'hideAuthor' => XenForo_Input::UINT,
                'hideTitle' => XenForo_Input::UINT
			),
		);
	}

	public function useWrapper(array $widget)
	{
		return empty($widget['options']['disableWrapper']);
	}

	/**
	 * @return string
	 */
	protected function _getOptionsTemplate ()
	{
		return 'sonnb_xengallery_widget_album';
	}

	/**
	 * @param array $widget
	 * @param string $positionCode
	 * @param array $params
	 * @return string
	 */
	protected function _getRenderTemplate (array $widget, $positionCode, array $params)
	{
		return 'sonnb_xengallery_widget_album';
	}

	/**
	 * @param XenForo_Template_Abstract $template
	 * @return bool|void
	 */
	protected function _renderOptions (XenForo_Template_Abstract $template)
	{
		$core = WidgetFramework_Core::getInstance();
		/* @var $categoryModel sonnb_XenGallery_Model_Category */
		$categoryModel = $core->getModelFromCache('sonnb_XenGallery_Model_Category');
		/* @var $collectionModel sonnb_XenGallery_Model_Collection */
		$collectionModel = $core->getModelFromCache('sonnb_XenGallery_Model_Collection');

		$template->setParams(array(
			'categories' => $categoryModel->getAllCachedCategories(),
			'collections' => $collectionModel->getAllCachedCollections()
		));
	}

	/**
	 * @param $optionKey
	 * @param $optionValue
	 * @return bool
	 * @throws XenForo_Exception
	 */
	protected function _validateOptionValue ($optionKey, &$optionValue)
	{
		$validType = array('new', 'lastUpdate', 'mostLiked', 'mostViewed', 'mostCommented', 'random', 'recentlyLiked');

		switch ($optionKey)
		{
			case 'type':
				if (!in_array($optionValue, $validType))
				{
					throw new XenForo_Exception(new XenForo_Phrase('sonnb_xengallery_invalid_type'), true);
				}
				break;
			case 'wrapper':
				if (empty($optionValue))
				{
					$optionValue = false;
				}
				else
				{
					$optionValue = true;
				}
				break;
			case 'limit':
				if (empty($optionValue))
				{
					$optionValue = 10;
				}
				break;
			case 'thumbnail_width':
				if ($optionValue < 10)
				{
					$optionValue = 220;
				}
				break;
			case 'thumbnail_height':
				if ($optionValue < 10)
				{
					$optionValue = 110;
				}
				break;
			case 'enable_scrollable':
				if ($optionValue)
				{
					$optionValue = true;
				}
				else
				{
					$optionValue = false;
				}
				break;
		}

		return true;
	}

	/**
	 * @param array $widget
	 * @param string $positionCode
	 * @param array $params
	 * @param XenForo_Template_Abstract $template
	 * @return string
	 */
	protected function _render (array $widget, $positionCode, array $params, XenForo_Template_Abstract $template)
	{
		$core = WidgetFramework_Core::getInstance();
		/* @var $albumModel sonnb_XenGallery_Model_Album */
		$albumModel = $core->getModelFromCache('sonnb_XenGallery_Model_Album');
		/* @var $galleryModel sonnb_XenGallery_Model_Gallery */
		$galleryModel = $core->getModelFromCache('sonnb_XenGallery_Model_Gallery');

		if (!$galleryModel->canViewGallery())
		{
			return;
		}

		$conditions = array(
			'album_state' => 'visible',
			'content_count' => array('>', 0),
			'cover_content_id' => array('>', 0),
		);

		$fetchOptions = array(
			'orderDirection' => 'desc',
			'limit' => $widget['options']['limit']*3,
			'join' => sonnb_XenGallery_Model_Album::FETCH_USER |
				sonnb_XenGallery_Model_Album::FETCH_COVER_PHOTO,
			'followingUserId' => XenForo_Visitor::getUserId()
		);

		switch ($widget['options']['type'])
		{
			case 'new':
				$fetchOptions['order'] = 'album_date';
				break;
			case 'lastUpdate':
				$fetchOptions['order'] = 'album_updated_date';
				break;
			case 'mostViewed':
				$fetchOptions['order'] = 'view_count';
				break;
			case 'mostCommented':
				$fetchOptions['order'] = 'comment_count';
				break;
			case 'random':
				$fetchOptions['order'] = 'random';
				break;
			case 'recentlyLiked':
				$fetchOptions['order'] = 'recently_liked';
				break;
			case 'mostLiked':
			default:
				$fetchOptions['order'] = 'likes';
				break;
		}

		if ($widget['options']['category_id'])
		{
			$conditions['category_id'] = $widget['options']['category_id'];
		}

		if ($widget['options']['collection_id'])
		{
			$fetchOptions['fetchCollectionDate'] = true;
			$fetchOptions['order'] = 'collection_date';
			$conditions['collection_id'] = $widget['options']['collection_id'];
		}

		if ($widget['options']['likes'])
		{
			$conditions['likes'] = array('>', $widget['options']['likes']);
		}

		if ($widget['options']['view_count'])
		{
			$conditions['view_count'] = array('>', $widget['options']['view_count']);
		}

		if ($widget['options']['comment_count'])
		{
			$conditions['comment_count'] = array('>', $widget['options']['comment_count']);
		}

		$userIds = explode(',', $widget['options']['user_id']);
		$userIds = array_filter($userIds);
		array_walk($userIds, create_function('&$userId, $key', '$userId = trim($userId);'));

		if ($userIds)
		{
			$conditions['user_id'] = $userIds;
		}

		$albums = $albumModel->getAlbums($conditions, $fetchOptions);
		$albums = $albumModel->prepareAlbums($albums, $fetchOptions);

		if ($albums)
		{
			foreach ($albums as $albumId => $album)
			{
				if (!$albumModel->canViewAlbum($album))
				{
					unset($albums[$albumId]);
					continue;
				}
			}
		}

		if (empty($albums))
		{
			return;
		}

		$albums = array_slice($albums, 0, $widget['options']['limit']);
		$albums = $albumModel->attachCoversToAlbums($albums, $fetchOptions);

		if ($widget['options']['thumbnail_width'] && $widget['options']['thumbnail_height'])
		{
			foreach ($albums as &$album)
			{
				if (!empty($album['cover']['medium_height']) && !empty($album['cover']['medium_width']))
				{
					$widthRatio = $album['cover']['medium_width']/$widget['options']['thumbnail_width'];
					$heightRatio = $album['cover']['medium_height']/$widget['options']['thumbnail_height'];

					if ($widthRatio == $heightRatio)
					{
						$album['cover']['medium_width'] = $widget['options']['thumbnail_width'];
						$album['cover']['medium_height'] = $widget['options']['thumbnail_height'];
						$album['cover']['style'] = '';
					}
					elseif ($widthRatio < $heightRatio)
					{
						$album['cover']['medium_height'] = $album['cover']['medium_height']/($album['cover']['medium_width']/$widget['options']['thumbnail_width']);
						$album['cover']['medium_width'] = $widget['options']['thumbnail_width'];
						$album['cover']['style'] = 'top: -'.(($album['cover']['medium_height']-$widget['options']['thumbnail_height'])/2).'px;';

					}
					else
					{
						$album['cover']['medium_width'] = $album['cover']['medium_width']/($album['cover']['medium_height']/$widget['options']['thumbnail_height']);
						$album['cover']['medium_height'] = $widget['options']['thumbnail_height'];
						$album['cover']['style'] = 'left: -'.(($album['cover']['medium_width']-$widget['options']['thumbnail_width'])/2).'px;';
					}
				}
			}
		}

		$template->setParam('albums', null);
		$template->setParams(array(
			'widget' => $widget,
			'albums' => $albums
		));

		if (method_exists('WidgetFramework_Template_Trojan', 'WidgetFramework_setRequiredExternals'))
		{
			$externalRequires = array(
				'css' => array('sonnb_xengallery_widget_album')
			);

			WidgetFramework_Template_Trojan::WidgetFramework_setRequiredExternals($externalRequires);
		}

		return $template->render();
	}

	/**
	 * @param array $widget
	 * @return array
	 */
	protected function _getRequiredExternal (array $widget)
	{
		$return = array(
			array('css', 'sonnb_xengallery_widget_album')
		);

		return $return;
	}
}