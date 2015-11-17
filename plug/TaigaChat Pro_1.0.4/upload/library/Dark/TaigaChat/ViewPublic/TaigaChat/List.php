<?php //Nulled by VxF.cc

class Dark_TaigaChat_ViewPublic_TaigaChat_List extends XenForo_ViewPublic_Base
{
	public function renderJson(){
		
		$options = XenForo_Application::get('options');	
		
		$maxid = Dark_TaigaChat_Helper_Global::processMessagesForView($this->_params, $this);		
		
		$template = $this->createTemplateObject($this->_templateName, $this->_params);
		$template->setParams($this->_params);
		if(!empty($this->_params['taigachat']['publichtml'])){
			$template->setLanguageId(XenForo_Phrase::getLanguageId());
			$template->setStyleId($options->defaultStyleId);
		}
		$rendered = $template->render();
		   
		$rendered = preg_replace(
			'/\s+<\/(.*?)>\s+</si', 
			' </$1> <', $rendered);
		$rendered = preg_replace(
			'/\s+<(.*?)([ >])/si', 
			' <$1$2', $rendered);
		
		$params = array(
			"templateHtml" => $rendered,
			"reverse" => $options->dark_taigachat_direction,
			"lastrefresh" => $maxid,
			"motd" => $this->_params['taigachat']['motd'],
			"numInChat" => $this->_params['taigachat']['numInChat'],
		);
		
		
		if(!empty($this->_params['taigachat']['publichtml'])){
			$params += array(
				"_visitor_conversationsUnread" => "IGNORE",
				"_visitor_alertsUnread" => "IGNORE",
			);
		}
		//$rendered = str_replace(array("\r", "\n", "\t"), "", $rendered);		
		
		$derp = XenForo_ViewRenderer_Json::jsonEncodeForOutput($params, empty($this->_params['taigachat']['publichtml']));
		
		if(empty($this->_params['taigachat']['publichtml'])){
			$extraHeaders = XenForo_Application::gzipContentIfSupported($derp);
			foreach ($extraHeaders AS $extraHeader)
			{
				header("$extraHeader[0]: $extraHeader[1]", $extraHeader[2]);
			}
		}
		
		return $derp;
	}
	
	public function renderHtml(){
		
		$options = XenForo_Application::get('options');
		
		
		$template = $this->createTemplateObject('dark_taigachat_full', $this->_params);
		$template->setParams($this->_params);
		$rendered = $template->render();

	}
}
