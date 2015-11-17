<?php

class Dark_TaigaChat_Route_Prefix_TaigaChat implements XenForo_Route_Interface
{
	
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'id');
		$routeMatch = $router->getRouteMatch('Dark_TaigaChat_ControllerPublic_TaigaChat', $action, 'taigachat', $routePath);
		//$routeMatch->setResponseType('jsonText');
		return $routeMatch;
	}

	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'id');
	}
}
