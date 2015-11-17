<?php
  
class Dark_TaigaChat_Model_SessionOverride extends XFCP_Dark_TaigaChat_Model_SessionOverride {
	
	public function processLastActivityUpdateForLogOut($userId){
		parent::processLastActivityUpdateForLogOut($userId);
		
		if (!$userId)
			return;

		/** @var Dark_TaigaChat_Model_TaigaChat */
		$taigaModel = XenForo_Model::create("Dark_TaigaChat_Model_TaigaChat");
		$taigaModel->updateActivityForLogOut($userId);
	}

}