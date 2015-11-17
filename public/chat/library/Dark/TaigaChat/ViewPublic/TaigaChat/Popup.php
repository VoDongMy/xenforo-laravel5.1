<?php

class Dark_TaigaChat_ViewPublic_TaigaChat_Popup extends XenForo_ViewPublic_Base
{	
	public function renderHtml(){
		
		$options = XenForo_Application::get('options');		
		$template = $this->createTemplateObject($this->_templateName, $this->_params);

		// Mini page bootstrapper :3
		
		$dep = new XenForo_Dependencies_Public();
		$this->_params = $dep->getEffectiveContainerParams($this->_params, $this->_params['request']);
		$this->_params['serverTimeInfo'] = XenForo_Locale::getDayStartTimestamps();
		$template->setParams($this->_params);

		echo $template->render();
		exit;
	}
	
}