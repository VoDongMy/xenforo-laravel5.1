<?php
  
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
		$options = XenForo_Application::get('options');
		if($options->dark_taigachat_sidebar){	
			$taigamodel = XenForo_Model::create('Dark_TaigaChat_Model_TaigaChat');
			$onlineUsersTaiga = $taigamodel->getActivityUserList();		
			
			$renderTemplateObject->setParam('taigachat', array(
				'online' => $onlineUsersTaiga,
			));
		}
		
		return $renderTemplateObject->render();
	}
	
	protected function _getRenderTemplate(array $widget, $positionCode, array $params){
		return 'dark_taigachat_widget_online';
	}
		
}