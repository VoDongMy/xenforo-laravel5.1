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
class sonnb_XenGallery_Route_Prefix_XenGallery implements XenForo_Route_Interface
{
	/**
	 * @var array
	 */
	protected $_subComponents = array(
			'authors' => array(
				'intId' => 'user_id',
				'title' => 'username',
				'controller' => 'sonnb_XenGallery_ControllerPublic_XenGallery_Author'
			),
			'members' => array(
				'intId' => 'user_id',
				'title' => 'username',
				'controller' => 'sonnb_XenGallery_ControllerPublic_XenGallery_Member'
			),
			'albums' => array(
				'intId' => 'album_id',
				'title' => 'title',
				'controller' => 'sonnb_XenGallery_ControllerPublic_XenGallery_Album'
			),
			'photos' => array(
				'intId' => 'content_id',
				'title' => 'title',
				'controller' => 'sonnb_XenGallery_ControllerPublic_XenGallery_Photo'
			),
			'videos' => array(
				'intId' => 'content_id',
				'title' => 'title',
				'controller' => 'sonnb_XenGallery_ControllerPublic_XenGallery_Video'
			),
			'comments' => array(
				'intId' => 'comment_id',
				'title' => '',
				'controller' => 'sonnb_XenGallery_ControllerPublic_XenGallery_Comment'
			),
			'histories' => array(
				'intId' => 'history_id',
				'title' => '',
				'controller' => 'sonnb_XenGallery_ControllerPublic_XenGallery_History'
			),
			'categories' => array(
				'intId' => 'category_id',
				'title' => 'title',
				'controller' => 'sonnb_XenGallery_ControllerPublic_XenGallery_Category'
			),
			'locations' => array(
				'stringId' => 'location_url',
				'controller' => 'sonnb_XenGallery_ControllerPublic_XenGallery_Location'
			),
			'tags' => array(
				'intId' => 'tag_id',
				'title' => 'tag_name',
				'controller' => 'sonnb_XenGallery_ControllerPublic_XenGallery_Tag'
			),
			'my-playlists' => array(
				'intId' => 'playlist_id',
				'title' => 'title',
				'controller' => 'sonnb_XenGallery_ControllerPublic_XenGallery_MyPlaylist'
			),
			'cameras' => array(
				'stringId' => 'camera_url',
				'controller' => 'sonnb_XenGallery_ControllerPublic_XenGallery_Camera'
			),
			'streams' => array(
				'stringId' => 'stream_name',
				'title' => 'title',
				'controller' => 'sonnb_XenGallery_ControllerPublic_XenGallery_Stream'
			),
			'collections' => array(
				'intId' => 'collection_id',
				'title' => 'title',
				'controller' => 'sonnb_XenGallery_ControllerPublic_XenGallery_Collection'
			),
			'inline-mod-content' => array(
				'controller' => 'sonnb_XenGallery_ControllerPublic_InlineMod_Content'
			),
			'inline-mod-album' => array(
				'controller' => 'sonnb_XenGallery_ControllerPublic_InlineMod_Album'
			),
			'inline-mod-comment' => array(
				'controller' => 'sonnb_XenGallery_ControllerPublic_InlineMod_Comment'
			)
	);

	/**
	 * @param $routePath
	 * @param Zend_Controller_Request_Http $request
	 * @param XenForo_Router $router
	 * @return false|XenForo_RouteMatch
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
    {
    	$controller = 'sonnb_XenGallery_ControllerPublic_XenGallery';
		$action = $router->getSubComponentAction($this->_subComponents, $routePath, $request, $controller);
		if ($action === false)
		{
			$action = $router->resolveActionWithIntegerParam($routePath, $request, 'user_id');
		}

		return $router->getRouteMatch($controller, $action, 'sonnb_xengallery');
    }

	/**
	 * @param $originalPrefix
	 * @param $outputPrefix
	 * @param $action
	 * @param $extension
	 * @param $data
	 * @param array $extraParams
	 * @return false|string
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
    {
	    $parts = explode('/', $action, 2);
	    $subComponentName = strtolower($parts[0]);

	    if ($subComponentName === 'photos')
	    {
		    if (isset($data['photo_id']) && !isset($data['content_id']))
		    {
			    $data['content_id'] = $data['photo_id'];
		    }

		    if (isset($data['title']) && $data['title'] == $data['content_id'])
		    {
				$data['title'] = '';
		    }
	    }

	    if ($subComponentName === 'videos')
	    {
		    if (isset($data['title']) && $data['title'] == $data['content_id'])
		    {
			    $data['title'] = '';
		    }
	    }

	    if ($subComponentName === 'locations' && isset($data['location_url']))
	    {
		    $data['location_url'] = sonnb_XenGallery_Model_Gallery::getTitleForUrl($data['location_url']);
	    }

	    if ($subComponentName === 'cameras' && isset($data['camera_url']))
	    {
		    $data['camera_url'] = sonnb_XenGallery_Model_Gallery::getTitleForUrl($data['camera_url']);
	    }

    	$link = XenForo_Link::buildSubComponentLink($this->_subComponents, $outputPrefix, $action, $extension, $data);

		if (!$link)
		{
			$link = XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, '');
		}
		
		return $link;
	}
}