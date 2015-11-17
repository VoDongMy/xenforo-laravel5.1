<?php

class XenGallery_Route_Prefix_Redirects_XFRUserAlbums implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$routePath = trim($routePath, '/');

		$parts = explode('/', $routePath);
		@list($id, $action) = $parts;

		$field = $this->getField($action);

		$action = $router->resolveActionWithIntegerParam($routePath, $request, $field[0]);
		$param = '';

		return $router->getRouteMatch('XenGallery_ControllerPublic_Redirects_XFRUserAlbums', $action);
	}

	private function getField($key)
	{
		$field = '';
		switch ($key)
		{
			case 'list':
				$field = array('user_id', 'username');
				break;

			case 'standalone':
			case 'view-image':
			case 'show-image':
			case 'imagebox':
				$field = array('image_id', 'title');
				break;

			case 'image-comments':
				$field = array('comment_id', null);
				break;

			default:
				$field = array('album_id', 'title');
				break;
		}

		return $field;
	}
}