<?php
  
class Dark_TaigaChat_WidgetRenderer_Sidebar extends WidgetFramework_WidgetRenderer
{
	protected function _getConfiguration(){
		return array(
			'name' => 'TaigaChat Pro Shoutbox (Sidebar)',
			'useCache' => false,
			'useWrapper' => false,
		);
	}
	
	protected function _getOptionsTemplate() {
		return false;
	}
	
	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject){		
		return $renderTemplateObject->render();
	}
	
	protected function _getRenderTemplate(array $widget, $positionCode, array $params){
		return 'dark_taigachat';
	}
		
}