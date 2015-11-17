<?php

class Dark_TaigaChat_ViewPublic_TaigaChat_List extends XenForo_ViewPublic_Base
{
	public function renderJson(){
		
		$options = XenForo_Application::get('options');	
		
		$maxUpdate = Dark_TaigaChat_Helper_Global::processMessagesForView($this->_params, $this);		
		
		$messages = $this->_params['taigachat']['messages'];		
		if($options->dark_taigachat_reverse){
			$messages = array_reverse($messages);
		}
		
		$twelveHour = false;
		$template = $this->createTemplateObject("dark_taigachat_robots");
		if(!empty($this->_params['taigachat']['publichtml'])){
			
			/** @var XenForo_Model_Language */
			$languageModel = XenForo_Model::create('XenForo_Model_Language');
			$language = $languageModel->getLanguageById(XenForo_Phrase::getLanguageId());
			if($language['time_format'] == 'g:i A')
				$twelveHour = true;
			
			$template->setLanguageId(XenForo_Phrase::getLanguageId());
			$template->setStyleId($options->defaultStyleId);
		}
		$robots = $template->render();
		
		$outputMessages = array();
		$previous = null;
		$template = $this->createTemplateObject("dark_taigachat_message", $this->_params);
		if(!empty($this->_params['taigachat']['publichtml'])){
			$template->setLanguageId(XenForo_Phrase::getLanguageId());
			$template->setStyleId($options->defaultStyleId);
		}
		foreach($messages as &$message){					
			$template->setParam("message", $message);
			$rendered = $template->render();
			   
			$rendered = preg_replace(
				'/\s+<\/(.*?)>\s+</si', 
				' </$1> <', $rendered);
			$rendered = preg_replace(
				'/\s+<(.*?)([ >])/si', 
				' <$1$2', $rendered);
				
			$outputMessages[]= array(
				"id" => $message['id'],
				"previous" => empty($previous) ? 0 : $previous['id'],
				"last_update" => $message['last_update'], 
				"html" => $rendered,
			);
			$previous = $message;
		}				
				
		$template = $this->createTemplateObject("dark_taigachat_online_users", $this->_params);
		if(!empty($this->_params['taigachat']['publichtml'])){
			$template->setLanguageId(XenForo_Phrase::getLanguageId());
			$template->setStyleId($options->defaultStyleId);
		}
		$outputOnlineUsers = $template->render();
		
		$params = array(
			"robots" => $robots,
			"messages" => $outputMessages,
			"messageIds" => $this->_params['taigachat']['messageIds'],
			"onlineUsers" => $outputOnlineUsers,
			"reverse" => $options->dark_taigachat_direction,
			"lastrefresh" => $maxUpdate,
			"motd" => $this->_params['taigachat']['motd'],
			"numInChat" => $this->_params['taigachat']['numInChat'],
			"twelveHour" => $twelveHour,
		);
		
		
		if(!empty($this->_params['taigachat']['publichtml'])){
			$params += array(
				"_visitor_conversationsUnread" => "IGNORE",
				"_visitor_alertsUnread" => "IGNORE",
			);
		}
		
		$jsonOutput = XenForo_ViewRenderer_Json::jsonEncodeForOutput($params, empty($this->_params['taigachat']['publichtml']));
		
		if(empty($this->_params['taigachat']['publichtml'])){
			$extraHeaders = XenForo_Application::gzipContentIfSupported($jsonOutput);
			foreach ($extraHeaders AS $extraHeader)
			{
				header("$extraHeader[0]: $extraHeader[1]", $extraHeader[2]);
			}
		}
		
		return $jsonOutput;
	}
	
	public function renderHtml(){
		$options = XenForo_Application::get('options');		
		
		$template = $this->createTemplateObject('dark_taigachat_full', $this->_params);
		$template->setParams($this->_params);
		$rendered = $template->render();
	}
	
}
