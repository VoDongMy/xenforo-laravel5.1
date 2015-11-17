<?php //Nulled by VxF.cc
  
  
class Dark_TaigaChat_EventListener_Listener
{
	
	public static function LoadClassDataWriter($class, array &$extend){		
		if($class == 'XenForo_DataWriter_DiscussionMessage_Post')
			$extend[] = 'Dark_TaigaChat_DataWriter_DiscussionMessage_Post';
		elseif($class == 'XenForo_DataWriter_User')
			$extend[] = 'Dark_TaigaChat_DataWriter_User';
			
	}
	
	
	public static function TemplateCreate($templateName, array &$params, XenForo_Template_Abstract $template){		
		if($templateName == 'dark_taigachat'){
			$response = new stdClass();
			$response->viewName = "";
			$response->params = array();
			Dark_TaigaChat_Helper_Global::getTaigaChatStuff($response, "");
			//Zend_Debug::dump($response->params);
			$params = array_merge_recursive($params, $response->params);			
		}
	}
		
	
	public static function TemplateHook($hookName, &$content, array $hookParams, XenForo_Template_Abstract $template){		
		
		if ($hookName == 'dark_taigachat' || $hookName == 'dark_taigachat_alt')
		{
			$params = $template->getParams();
			//$params = array();
			if($hookName == 'dark_taigachat_alt')
				$params['taigachat']['alt'] = true;
			$content .= $template->create('dark_taigachat', $params)->render();
			
			
		}
				
	}
	
	public static function WidgetFrameworkReady(&$renderers){
		$renderers[]= "Dark_TaigaChat_WidgetRenderer_Sidebar";
		$renderers[]= "Dark_TaigaChat_WidgetRenderer_Alt";
		$renderers[]= "Dark_TaigaChat_WidgetRenderer_Online";
	}
	
}