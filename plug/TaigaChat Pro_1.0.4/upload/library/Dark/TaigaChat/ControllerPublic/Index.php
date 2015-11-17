<?php //Nulled by VxF.cc
  
class Dark_TaigaChat_ControllerPublic_Index extends XFCP_Dark_TaigaChat_ControllerPublic_Index {
	
	/*
	public function actionIndex(){
		if(method_exists('XFCP_Dark_TaigaChat_ControllerPublic_Index', 'actionIndex')){
			$response = parent::actionIndex();	
			if ($response instanceof XenForo_ControllerResponse_View){				
				$this->getTaigaChatStuff($response, 'index', $this);			
			}
			return $response;		
		}
	}
		
	public function actionThreads(){
		if(method_exists('XFCP_Dark_TaigaChat_ControllerPublic_Index', 'actionThreads')){
			$response = parent::actionThreads();    
			if ($response instanceof XenForo_ControllerResponse_View){                
				$this->getTaigaChatStuff($response, 'index', $this);            
			}
			return $response;       
		} 
	}*/
	
	public function actionPopup(){		
		if(method_exists('XFCP_Dark_TaigaChat_ControllerPublic_Index', 'actionPopup')){
			$response = parent::actionPopup();		
			if ($response instanceof XenForo_ControllerResponse_View){				
				//$this->getTaigaChatStuff($response, 'popup', $this);			
				Dark_TaigaChat_Helper_Global::getTaigaChatStuff($response, 'popup', $this);
			}
			return $response;	
		}	
	}
	
		
}