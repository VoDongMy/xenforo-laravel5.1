<?php
  
class Dark_TaigaChat_Option_SpeedMode {
	
	
	public static function verifyOption(&$value, XenForo_DataWriter $dw, $fieldName)
	{
		$options = XenForo_Application::get('options');
		$options->dark_taigachat_speedmode = $value;
		
		/** @var Dark_TaigaChat_Model_TaigaChat */
		$taigaModel = XenForo_Model::create("Dark_TaigaChat_Model_TaigaChat");
		
		if($value == 'Enabled' || $value == 'Https'){
			$taigaModel->regeneratePublicHtml();
		} else {
			$taigaModel->deletePublicHtml();
		}		
		
		return true;
	}
}
