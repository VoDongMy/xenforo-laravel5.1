<?php //Nulled by VxF.cc
  
class Dark_TaigaChat_Option_Motd {
	
	
	public static function verifyOption(&$value, XenForo_DataWriter $dw, $fieldName)
	{
		/** @var Dark_TaigaChat_Model_TaigaChat */
		$taigaModel = XenForo_Model::create("Dark_TaigaChat_Model_TaigaChat");
		$taigaModel->regeneratePublicHtml($value);
		return true;
	}
}
