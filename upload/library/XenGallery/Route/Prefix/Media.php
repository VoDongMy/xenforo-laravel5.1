<?php

/**
 * Route prefix handler for XenForo Media Gallery.
 */
class XenGallery_Route_Prefix_Media implements XenForo_Route_Interface
{
	protected $_subComponents = array(
		'photos' => array(
			'intId' => 'media_id',
			'title' => 'media_title',
			'controller' => 'XenGallery_ControllerPublic_Media'
		),
		'albums' => array(
			'intId' => 'album_id',
			'title' => 'album_title',
			'controller' => 'XenGallery_ControllerPublic_Album'
		),
		'categories' => array(
			'intId' => 'category_id',
			'title' => 'category_title',
			'controller' => 'XenGallery_ControllerPublic_Category'
		),
		'users' => array(
			'intId' => 'user_id',
			'title' => 'username',
			'controller' => 'XenGallery_ControllerPublic_User'
		),
		'files' => array(
			'intId' => 'media_id',
			'title' => 'media_title',
			'controller' => 'XenGallery_ControllerPublic_File'
		),
		'comments' => array(
			'intId' => 'comment_id',
			'controller' => 'XenGallery_ControllerPublic_Comment'
		),
		'inline-mod' => array(
			'controller' => 'XenGallery_ControllerPublic_InlineMod_Media'
		),
		'albums-inline-mod' => array(
			'controller' => 'XenGallery_ControllerPublic_InlineMod_Album'
		),
		'comments-inline-mod' => array(
			'controller' => 'XenGallery_ControllerPublic_InlineMod_Comment'
		)
	);
		
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$controller = 'XenGallery_ControllerPublic_Media';
		$action = $router->getSubComponentAction($this->_subComponents, $routePath, $request, $controller);

		if ($action === false)
		{
			$action = $router->resolveActionWithIntegerParam($routePath, $request, 'media_id');
		}

		return $router->getRouteMatch($controller, $action, 'xengallery');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{	
		$link = XenForo_Link::buildSubComponentLink($this->_subComponents, $outputPrefix, $action, $extension, $data);
		
		if (!$link)
		{
			$link = XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'media_id', 'media_title');
		}
		
		return $link;
	}
}