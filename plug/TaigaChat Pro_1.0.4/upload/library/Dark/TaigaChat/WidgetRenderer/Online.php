<?php //Nulled by VxF.cc
  
class Dark_TaigaChat_WidgetRenderer_Online extends WidgetFramework_WidgetRenderer
{
	protected function _getConfiguration(){
		return array(
			'name' => 'TaigaChat Pro Users In Chat (Sidebar)',
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
		return 'dark_taigachat_widget_online';
	}
		
}