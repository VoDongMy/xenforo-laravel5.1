<?php
  
class Dark_TaigaChat_EventListener_NavigationTabs
{
	public static function listen(array &$extraTabs, $selectedTabId)
	{		
		$options = XenForo_Application::get('options');
		$visitor = XenForo_Visitor::getInstance();
		if($options->dark_taigachat_navtab && $visitor->hasPermission("dark_taigachat", "view")){
			
			$counter = array();
			if($options->dark_taigachat_navtab_numinchat){
				/** @var Dark_TaigaChat_Model_TaigaChat */
				$taigamodel = XenForo_Model::create('Dark_TaigaChat_Model_TaigaChat');	
				$numInChat = $taigamodel->getActivityUserCount();
				if($numInChat > 0)
					$counter = array('counter' => $numInChat);
			}
			
			$extraTabs['taigachat'] = $counter + array(
				'title' => new Xenforo_Phrase("dark_shoutbox"),
				'href' => XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink($options->dark_taigachat_route), true),
				'selected' => ($selectedTabId == 'taigachat'),
				'linksTemplate' => 'dark_taigachat_links',
				'taigachat' => array(
					"route" => $options->dark_taigachat_route,
					"popupenabled" => $options->dark_taigachat_popupenabled,
				)
			);
			if(!$options->dark_taigachat_popupenabled)
				unset($extraTabs['taigachat']['linksTemplate']);
		}    	
	}
}