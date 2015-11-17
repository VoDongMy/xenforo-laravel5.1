<?php

class XenGallery_Route_Prefix_Redirects_XenMedio implements XenForo_Route_Interface
{
	protected $_subComponents = array(
		'user' => array(
			'intId' => 'user_id',
			'actionPrefix' => 'user'
		),
		'keyword' => array(
			'stringId' => 'keyword_text',
			'actionPrefix' => 'keyword'
		),
		'category' => array(
			'intId' => 'category_id',
			'actionPrefix' => 'category'
		),
		'playlist' => array(
			'intId' => 'playlist_id',
			'actionPrefix' => 'playlist'
		)
	);

	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$controller = 'XenGallery_ControllerPublic_Redirects_XenMedio';
		$action = $router->getSubComponentAction($this->_subComponents, $routePath, $request, $controller);

		if ($action == false)
		{
			$action = $router->resolveActionWithIntegerParam($routePath, $request, 'media_id');
		}

		return $router->getRouteMatch($controller, $action);
	}
}