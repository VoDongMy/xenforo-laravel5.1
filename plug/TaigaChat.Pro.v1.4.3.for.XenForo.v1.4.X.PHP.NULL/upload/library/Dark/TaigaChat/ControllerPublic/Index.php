<?php
  
class Dark_TaigaChat_ControllerPublic_Index extends XFCP_Dark_TaigaChat_ControllerPublic_Index {
	
	public function actionPopup(){		
		if(method_exists('XFCP_Dark_TaigaChat_ControllerPublic_Index', 'actionPopup')){
			$response = parent::actionPopup();		
			if ($response instanceof XenForo_ControllerResponse_View){	
				Dark_TaigaChat_Helper_Global::getTaigaChatStuff($response, 'popup', $this);
			}
			return $response;	
		}	
	}	
		
}