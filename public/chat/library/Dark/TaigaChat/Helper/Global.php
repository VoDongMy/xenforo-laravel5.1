<?php
  
class Dark_TaigaChat_Helper_Global 
{	
	public static function getTaigaChatStuff(&$response, $action, $dis=false){
		$options = XenForo_Application::get('options');
		$visitor = XenForo_Visitor::getInstance();
		/** @var Dark_TaigaChat_Model_TaigaChat */
		$taigamodel = XenForo_Model::create("Dark_TaigaChat_Model_TaigaChat");
		
		$visitor = XenForo_Visitor::getInstance();
		/** @var Dark_TaigaChat_Model_TaigaChat */
		//$taigamodel->updateActivity($visitor['user_id'], false);
			
		$toolbar_bbcode = array();
		if($options->dark_taigachat_toolbar){
			$toolbar_bbcode_temp2 = preg_replace('#(^//.+$)#mi', '', trim($options->dark_taigachat_toolbar_bbcode));
			$toolbar_bbcode_temp2 = str_replace("\r", "", $toolbar_bbcode_temp2);
			
			if(!empty($toolbar_bbcode_temp2)){
				$toolbar_bbcode_temp = explode("\n", $toolbar_bbcode_temp2);          				
				
				foreach($toolbar_bbcode_temp as $bbcode){
					$bbcode = trim($bbcode);					
					if(!empty($bbcode)){
						$bbcode = explode(":", trim($bbcode));
						if(!empty($bbcode))
							$toolbar_bbcode[$bbcode[0]] = $bbcode[1];
					}
				}
			}			 
		}
		
		if(empty($response->params['taigachat']))
			$response->params['taigachat'] = array();
			
		if (!XenForo_Application::isRegistered('config') || !$options->dark_taigachat_externaldata)
		{
			$path = 'data';
		}
		else
		{
			$path = XenForo_Application::get('config')->externalDataPath;
		}
		
		if($path == 'data' && $options->dark_taigachat_speedmode == 'Https')
			$path = $options->boardUrl . '/' . $path;
		
		$isSidebar = $response->viewName != "Dark_TaigaChat_ViewPublic_TaigaChat_Index" && $action != 'popup' && $action != 'xenporta_alt';
		
		//$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base'));
		//$motd = new XenForo_BbCode_TextWrapper($options->dark_taigachat_motd, $bbCodeParser);
		$motd = false;
		if(!empty($options->dark_taigachat_motd)){
			$motd = " ";
		}
		
		// Don't forget to add to dark_taigachat template too
		$response->params['taigachat'] += array(
			"focusedrefreshtime" => $options->dark_taigachat_focusedrefreshtime,
			"unfocusedrefreshtime" => $options->dark_taigachat_unfocusedrefreshtime,
			"tabunfocusedrefreshtime" => $options->dark_taigachat_tabunfocusedrefreshtime,
			"enabled" => true,///$options->dark_taigachat_enabled,
			"maxlength" => $options->dark_taigachat_maxlength,
			"reverse" => $options->dark_taigachat_direction,
			"height" => $options->dark_taigachat_height,
			"route" => $options->dark_taigachat_route,
			"timedisplay" => $options->dark_taigachat_timedisplay,
			"fastactivity" => $options->dark_taigachat_fastactivity,
			"toolbar" => $options->dark_taigachat_toolbar,
			"ignorehide" => $options->dark_taigachat_ignorehide,
			"showAlert" => $options->dark_taigachat_showalert,
			"toolbar_bbcode" => $toolbar_bbcode,
			"toolbar_smilies" => $options->dark_taigachat_smilies,
			"activity_newtab" => $options->dark_taigachat_activity_newtab,
			"newtab" => $options->dark_taigachat_newtab,
			"thumbzoom" => $options->dark_taigachat_imagemode == 'ThumbZoom',
			"js_modification" => filemtime("js/dark/taigachat.js"),
			"canView" => $visitor->hasPermission('dark_taigachat', 'view'),
			"canPost" => $visitor->hasPermission('dark_taigachat', 'post'),
			"canBan" => $visitor->hasPermission('dark_taigachat', 'ban'),
			"canColor" => $taigamodel->canUseColor(),
			"color" => $visitor->taigachat_color,
			"canModify" => $visitor->hasPermission('dark_taigachat', 'modify'),
			"canModifyAll" => $visitor->hasPermission('dark_taigachat', 'modifyAll'),
			"canMotd" => $visitor->hasPermission('dark_taigachat', 'motd'),
			"motd" => $motd,
			//"numInChat" => $taigamodel->getActivityUserCount(),
			"sidebar" => $isSidebar,
			"popup" => $action == 'popup',
			"limit" => $isSidebar ? $options->dark_taigachat_sidebarperpage : $options->dark_taigachat_fullperpage,
			"speed" => $options->dark_taigachat_speedmode != 'Disabled',
			"speedurl" => $isSidebar ? ($path.'/taigachat/messagesmini.html') : ($path.'/taigachat/messages.html'),
		);        
	}
	
	
	public static function processMessagesForView(&$params, &$view){		
		
		$options = XenForo_Application::get('options');
		$formatter = XenForo_BbCode_Formatter_Base::create('Dark_TaigaChat_BbCode_Formatter_Tenori', array('view' => $view));
		
		switch($options->dark_taigachat_bbcode){
			case 'Full':
				$formatter->displayableTags = true;
				break;
			case 'Basic':
			default:
				$formatter->displayableTags = array('img', 'url', 'email', 'b', 'u', 'i', 's', 'color');			
				break;
			case 'None':
				$formatter->displayableTags = array('url', 'email');			
				break;
		}
		$formatter->getTagsAgain();
		
		$parser = new XenForo_BbCode_Parser($formatter);
		
		if($options->dark_taigachat_imagemode == 'Link')
			foreach($params['taigachat']['messages'] as &$message){
				$message['message'] = str_ireplace(array("[img]", "[/img]"), array("[url]", "[/url]"), $message['message']);
			}
		
		$maxUpdate = $params['taigachat']['lastrefresh'];
		foreach($params['taigachat']['messages'] as &$message){
			
			if($options->dark_taigachat_bbcode == 'Full')
				$message['message'] = XenForo_Helper_String::autoLinkBbCode($message['message']);
			else 
			{
				// We don't want to parse youtube etc. urls if [media] is disabled
				$autoLinkParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Dark_TaigaChat_BbCode_Formatter_BbCode_AutoLink', false));
				$message['message'] = $autoLinkParser->render($message['message']);	
			}
				
			if($message['last_update'] > $maxUpdate)
				$maxUpdate = $message['last_update'];
				
			if(substr($message['message'], 0, 3) == '/me'){
				$message['message'] = substr($message['message'], 4);
				$message['me'] = true;
			}
		}
		
		if($options->dark_taigachat_smilies)
			XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($params['taigachat']['messages'], $parser);
		else
			XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($params['taigachat']['messages'], $parser, array("states" => array("stopSmilies" => true)));
			
		
		if($options->dark_taigachat_direction)
			$params['taigachat']['messages'] = array_reverse($params['taigachat']['messages']);
			
		return max($maxUpdate, XenForo_Application::getSimpleCacheData('taigachat_lastUpdate'));

	}
}