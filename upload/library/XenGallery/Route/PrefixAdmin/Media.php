<?php

/**
 * Route prefix handler for XenForo Media Gallery in the admin control panel.
 */
class XenGallery_Route_PrefixAdmin_Media implements XenForo_Route_Interface
{
	protected $_subComponents = array(
		'categories' => array(
			'intId' => 'category_id',
			'title' => 'category_title',
			'actionPrefix' => 'category'
		),
		'options' => array(
			'actionPrefix' => 'option'
		),
		'permissions' => array(
			'intId' => 'user_group_id',
			'title' => 'title',
			'actionPrefix' => 'permission'
		),
		'fields' => array(
			'stringId' => 'field_id',
			'actionPrefix' => 'field'
		)
	);

	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$controller = 'XenGallery_ControllerAdmin_Media';
		$action = $router->getSubComponentAction($this->_subComponents, $routePath, $request, $controller);

		if ($action === false)
		{
			$action = $router->resolveActionWithIntegerParam($routePath, $request, '');
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
			$link = XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, '');
		}

		return $link;
	}
}