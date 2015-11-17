<?php
  
  
class Dark_TaigaChat_EventListener_Listener
{
	
	public static function LoadClassDataWriter($class, array &$extend){		
		if($class == 'XenForo_DataWriter_DiscussionMessage_Post')
			$extend[] = 'Dark_TaigaChat_DataWriter_DiscussionMessage_Post';
		elseif($class == 'XenForo_DataWriter_User')
			$extend[] = 'Dark_TaigaChat_DataWriter_User';			
	}
	
	public static function LoadClassModel($class, array &$extend){		
		if($class == 'XenForo_Model_Session')
			$extend[] = 'Dark_TaigaChat_Model_SessionOverride';			
	}
	
	public static function TemplateCreate($templateName, array &$params, XenForo_Template_Abstract $template){		
		
		switch($templateName){
			case 'dark_taigachat':			
				/** @var Dark_TaigaChat_Model_TaigaChat */
				$taigamodel = XenForo_Model::create("Dark_TaigaChat_Model_TaigaChat");		
				$visitor = XenForo_Visitor::getInstance();
				$taigamodel->updateActivity($visitor['user_id'], false);								
				// fallthrough
			case 'dark_taigachat_widget_online':
			case 'dark_taigachat_fake':
				$response = new stdClass();
				$response->viewName = "";
				$response->params = array();
				Dark_TaigaChat_Helper_Global::getTaigaChatStuff($response, "");
				$params = array_merge_recursive($params, $response->params);			
				break;
				
			case 'PAGE_CONTAINER':
				$template->preloadTemplate('dark_taigachat');
				$template->preloadTemplate('dark_taigachat_list');
				break;
		}

	}
		
	
	public static function TemplateHook($hookName, &$content, array $hookParams, XenForo_Template_Abstract $template){		
		
		switch($hookName){
			
			case 'dark_taigachat':
			case 'dark_taigachat_alt':		
				$params = $template->getParams();
				if($hookName == 'dark_taigachat_alt')
					$params['taigachat']['alt'] = true;
				$params['taigachat']['room'] = 1;
				if(isset($hookParams['room']))
					$params['taigachat']['room'] = $hookParams['room'];
				$content .= $template->create('dark_taigachat', $params)->render();
				break;				
				
			case 'dark_taigachat_online_users':				
				$params = $template->getParams();
				$content .= $template->create('dark_taigachat_widget_online', $params)->render();
				break;
				
			case 'dark_taigachat_fake':				
				$params = $template->getParams();
				$content .= $template->create('dark_taigachat_fake', $params)->render();
				break;
				
			case 'dark_taigachat_full':		
				$params = $template->getParams();
				$params['taigachat']['room'] = 1;
				if(isset($hookParams['room']))
					$params['taigachat']['room'] = $hookParams['room'];										
		
				$visitor = XenForo_Visitor::getInstance();
				$sessionModel = XenForo_Model::create('Dark_TaigaChat_Model_Session');				
				$taigamodel = XenForo_Model::create("Dark_TaigaChat_Model_TaigaChat");			
				$taigamodel->updateActivity($visitor['user_id'], false);			

				$onlineUsers = $sessionModel->getSessionActivityQuickList(
					$visitor->toArray(),
					array('cutOff' => array('>', $sessionModel->getOnlineStatusTimeout())),
					($visitor['user_id'] ? $visitor->toArray() : null)
				);			
				$onlineUsersTaiga = array();		
					
				if($options->dark_taigachat_sidebar){	
					$onlineUsersTaiga = $taigamodel->getActivityUserList($visitor->toArray());		
				}
				
				$params += array('taigachat' => array(
					'onlineUsers' => $onlineUsers,
					'sidebar_enabled' => true,
					'online' => $onlineUsersTaiga,
				));
				$response = new stdClass();
				$response->viewName = "Dark_TaigaChat_ViewPublic_TaigaChat_Index";
				$response->params = array();
				Dark_TaigaChat_Helper_Global::getTaigaChatStuff($response, "");
				$params = array_merge_recursive($params, $response->params);						
		
				$content .= $template->create('dark_taigachat_full', $params)->render();
				break;			
		}
				
	}
	
	public static function WidgetFrameworkReady(&$renderers){
		$renderers[]= "Dark_TaigaChat_WidgetRenderer_Sidebar";
		$renderers[]= "Dark_TaigaChat_WidgetRenderer_Alt";
		$renderers[]= "Dark_TaigaChat_WidgetRenderer_Online";
	}
	
}