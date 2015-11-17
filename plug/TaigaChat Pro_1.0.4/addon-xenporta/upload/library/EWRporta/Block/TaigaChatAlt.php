<?php //Nulled by VxF.cc
  
class EWRporta_Block_TaigaChatAlt extends XenForo_Model
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
		$response->params += array('xenporta_alt' => true, 'alt' => true, 'taigachat_alt' => true, 'taigachat' => array('alt' => true));
		return $response->params;
	}
}
