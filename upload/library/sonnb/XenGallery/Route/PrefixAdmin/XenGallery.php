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
class sonnb_XenGallery_Route_PrefixAdmin_XenGallery implements XenForo_Route_Interface
{
	/**
	 * @var array
	 */
	protected $_subComponents = array(
		'categories' => array(
			'intId' => 'category_id',
			'title' => 'title',
			'controller' => 'sonnb_XenGallery_ControllerAdmin_Category'
		),
		'locations' => array(
			'stringId' => 'location_url',
			'title' => '',
			'controller' => 'sonnb_XenGallery_ControllerAdmin_Location'
		),
		'cameras' => array(
			'stringId' => 'camera_id',
			'title' => 'camera_name',
			'controller' => 'sonnb_XenGallery_ControllerAdmin_Camera'
		),
		'streams' => array(
			'stringId' => 'stream_name',
			'title' => 'title',
			'controller' => 'sonnb_XenGallery_ControllerAdmin_Stream'
		),
		'collections' => array(
			'intId' => 'collection_id',
			'title' => 'title',
			'controller' => 'sonnb_XenGallery_ControllerAdmin_Collection'
		),
		'imports' => array(
			'intId' => '',
			'title' => '',
			'controller' => 'sonnb_XenGallery_ControllerAdmin_Import'
		),
		'albums' => array(
			'intId' => 'album_id',
			'title' => 'title',
			'controller' => 'sonnb_XenGallery_ControllerAdmin_Album'
		),
		'fields' => array(
			'stringId' => 'field_id',
			'title' => 'title',
			'controller' => 'sonnb_XenGallery_ControllerAdmin_Field'
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
    	$controller = 'sonnb_XenGallery_ControllerAdmin_XenGallery';
		$action = $router->getSubComponentAction($this->_subComponents, $routePath, $request, $controller);

		if ($action === false)
		{
			$action = $router->resolveActionWithIntegerParam($routePath, $request, '');
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
    	$link = XenForo_Link::buildSubComponentLink($this->_subComponents, $outputPrefix, $action, $extension, $data);
    	
		if (!$link)
		{
			$link = XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, '');
		}
		
		return $link;
	}
}