<?php

class Dark_TaigaChat_ViewRenderer_JsonInternal extends XenForo_ViewRenderer_Json
{
	
	public function __construct(XenForo_Dependencies_Abstract $dependencies, Zend_Controller_Response_Http $response, Zend_Controller_Request_Http $request)
	{
		$this->_dependencies = $dependencies;
		$this->_response = $response;
		$this->_request = $request;

		$this->_preloadContainerData();
	}
	
	public function renderError($error)
	{
		return '';
	}
	
	protected static function _addDefaultParams(array &$params = array()){
		$params['_visitor_conversationsUnread'] = "IGNORE";
		$params['_visitor_alertsUnread'] = "IGNORE";
		return $params;	
	}
	
	public function renderView($viewName, array $params = array(), $templateName = '', XenForo_ControllerResponse_View $subView = null)
	{
		if ($subView)
		{
			return $this->renderSubView($subView);
		}

		$viewOutput = $this->renderViewObject($viewName, 'Json', $params, $templateName);

		if (is_array($viewOutput))
		{
			return self::jsonEncodeForOutput($viewOutput, false);
		}
		else if ($viewOutput === null)
		{
			return self::jsonEncodeForOutput(
				$this->getDefaultOutputArray($viewName, $params, $templateName), false
			);
		}
		else
		{
			return $viewOutput;
		}
	}
	
	
	
}