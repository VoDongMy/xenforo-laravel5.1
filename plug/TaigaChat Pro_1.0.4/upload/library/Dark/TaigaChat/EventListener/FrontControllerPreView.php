<?php //Nulled by VxF.cc

class Dark_TaigaChat_EventListener_FrontControllerPreView
{
	public static function listen(XenForo_FrontController $fc, XenForo_ControllerResponse_Abstract &$controllerResponse, XenForo_ViewRenderer_Abstract &$viewRenderer, array &$containerParams)
	{		
		return;
		/*
		//$options = XenForo_Application::get('options');
		
		if(($controllerResponse->controllerName == 'Dark_TaigaChat_ControllerPublic_TaigaChat' || $controllerResponse->controllerName == 'XenForo_ControllerPublic_Index') && !isset($controllerResponse->params['taigachat'])){
			$action = $controllerResponse->controllerAction;
			$action[0] = strtolower($action[0]);
			//Dark_TaigaChat_Helper_Global::getTaigaChatStuff($controllerResponse, $action);
		}*/
	}
}