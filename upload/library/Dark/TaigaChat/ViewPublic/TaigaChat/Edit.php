<?php

class Dark_TaigaChat_ViewPublic_TaigaChat_Edit extends XenForo_ViewPublic_Base
{
	public function renderJson(){
		
		$options = XenForo_Application::get('options');		
		
		Dark_TaigaChat_Helper_Global::processMessagesForView($this->_params, $this);		
		$this->_params['message'] = $this->_params['taigachat']['messages'][0];
		
		$template = $this->createTemplateObject($this->_templateName, $this->_params);
		$template->setParams($this->_params);
		$rendered = $template->render();
				
		$params = array(
			"templateHtml" => $rendered,
			"editId" => $this->_params['taigachat']['editid'],
		);
				
		$derp = XenForo_ViewRenderer_Json::jsonEncodeForOutput($params, true);
				
		return $derp;
	}
	
}
