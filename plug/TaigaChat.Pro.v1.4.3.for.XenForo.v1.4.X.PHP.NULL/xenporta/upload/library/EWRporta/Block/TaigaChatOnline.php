<?php
  
class EWRporta_Block_TaigaChatOnline extends XenForo_Model
{
	public function getModule(&$options)
	{
		if ((!$addon = $this->getModelFromCache('XenForo_Model_AddOn')->getAddOnById('TaigaChat')) || empty($addon['active']))
		{
			return "killModule";
		}

		$response = new XenForo_ControllerResponse_View();
		$response->viewName = 'derp';
		$response->params = array();
		
		// underscore works around xenporta bug		
		$options_ = XenForo_Application::get('options');
		if($options_->dark_taigachat_sidebar){	
			$taigamodel = $this->getModelFromCache('Dark_TaigaChat_Model_TaigaChat');
			$onlineUsersTaiga = $taigamodel->getActivityUserList();		
			
			$response->params += array('taigachat' => array(
				'online' => $onlineUsersTaiga,
			));
		}
		
		$response->params += array('xenporta' => true);
		return $response->params;
	}
}