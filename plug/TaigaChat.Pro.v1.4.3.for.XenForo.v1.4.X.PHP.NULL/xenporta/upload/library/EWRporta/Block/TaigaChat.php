<?php
  
class EWRporta_Block_TaigaChat extends XenForo_Model
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
		
		Dark_TaigaChat_Helper_Global::getTaigaChatStuff($response, 'index');
		$response->params += array('xenporta' => true);
		return $response->params;
	}
}