<?php
  
class Dark_TaigaChat_WidgetRenderer_Alt extends WidgetFramework_WidgetRenderer
{
	protected function _getConfiguration(){
		return array(
			'name' => 'TaigaChat Pro Shoutbox (Full Width)',
			'useCache' => false,
			'useWrapper' => false,
		);
	}
	
	protected function _getOptionsTemplate() {
		return false;
	}
	
	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject){
		$params = $renderTemplateObject->getParams();
		$params['taigachat']['alt'] = true;
		$params['taigachat_alt'] = true;
		$renderTemplateObject->setParams($params);
		return $renderTemplateObject->render();
	}
	
	protected function _getRenderTemplate(array $widget, $positionCode, array $params){
		return 'dark_taigachat';
	}
		
}