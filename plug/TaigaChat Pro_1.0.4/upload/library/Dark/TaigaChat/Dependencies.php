<?php //Nulled by VxF.cc
  
class Dark_TaigaChat_Dependencies extends XenForo_Dependencies_Public {
	
	/**
	 * Pre-loads globally required data for the system.
	 */
	public function preLoadData()
	{
		
		
		// f the police
		return;
		
		
		
		
		
		$required = array_merge(
			array('options', 'languages', 'contentTypes', 'codeEventListeners', 'cron', 'simpleCache'),
			$this->_dataPreLoadFromRegistry
		);
		$data = XenForo_Model::create('XenForo_Model_DataRegistry')->getMulti($required);
		
		// don't want any listeners at all
		
		/*if (XenForo_Application::get('config')->enableListeners)
		{
			if (!is_array($data['codeEventListeners']))
			{
				$data['codeEventListeners'] = XenForo_Model::create('XenForo_Model_CodeEvent')->rebuildEventListenerCache();
			}
			XenForo_CodeEvent::setListeners($data['codeEventListeners']);
		}*/

		if (!is_array($data['options']))
		{
			$data['options'] = XenForo_Model::create('XenForo_Model_Option')->rebuildOptionCache();
		}
		$options = new XenForo_Options($data['options']);
		//XenForo_Application::setDefaultsFromOptions($options);
		//XenForo_Application::set('options', $options);

		if (!is_array($data['languages']))
		{
			$data['languages'] = XenForo_Model::create('XenForo_Model_Language')->rebuildLanguageCache();
		}
		//XenForo_Application::set('languages', $data['languages']);

		if (!is_array($data['contentTypes']))
		{
			$data['contentTypes'] = XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
		}
		//XenForo_Application::set('contentTypes', $data['contentTypes']);

		if (!is_int($data['cron']))
		{
			$data['cron'] = XenForo_Model::create('XenForo_Model_Cron')->updateMinimumNextRunTime();
		}
		//XenForo_Application::set('cron', $data['cron']);

		if (!is_array($data['simpleCache']))
		{
			$data['simpleCache'] = array();
			XenForo_Model::create('XenForo_Model_DataRegistry')->set('simpleCache', $data['simpleCache']);
		}
		//XenForo_Application::set('simpleCache', $data['simpleCache']);

		$this->_handleCustomPreloadedData($data);
	}
	
	
	protected function _handleCustomPreloadedData(array &$data)
	{
		if (!is_array($data['routesPublic']))
		{
			$data['routesPublic'] = XenForo_Model::create('XenForo_Model_RoutePrefix')->rebuildRoutePrefixTypeCache('public');
		}
		//XenForo_Link::setHandlerInfoForGroup('public', $data['routesPublic']);

		if (!is_array($data['bannedIps']))
		{
			$data['bannedIps'] = XenForo_Model::create('XenForo_Model_Banning')->rebuildBannedIpCache();
		}
		//XenForo_Application::set('bannedIps', $data['bannedIps']);

		if (!is_array($data['discouragedIps']))
		{
			$data['discouragedIps'] = XenForo_Model::create('XenForo_Model_Banning')->rebuildDiscouragedIpCache();
		}
		//XenForo_Application::set('discouragedIps', $data['discouragedIps']);

		if (!is_array($data['styles']))
		{
			$data['styles'] = XenForo_Model::create('XenForo_Model_Style')->rebuildStyleCache();
		}
		//XenForo_Application::set('styles', $data['styles']);

		if (!is_array($data['nodeTypes']))
		{
			$data['nodeTypes'] = XenForo_Model::create('XenForo_Model_Node')->rebuildNodeTypeCache();
		}
		//XenForo_Application::set('nodeTypes', $data['nodeTypes']);

		if (!is_array($data['smilies']))
		{
			$data['smilies'] = XenForo_Model::create('XenForo_Model_Smilie')->rebuildSmilieCache();
		}
		//XenForo_Application::set('smilies', $data['smilies']);

		if (!is_array($data['bbCode']))
		{
			$data['bbCode'] = XenForo_Model::create('XenForo_Model_BbCode')->rebuildBbCodeCache();
		}
		//XenForo_Application::set('bbCode', $data['bbCode']);

		if (!is_array($data['threadPrefixes']))
		{
			$data['threadPrefixes'] = XenForo_Model::create('XenForo_Model_ThreadPrefix')->rebuildPrefixCache();
		}
		//XenForo_Application::set('threadPrefixes', $data['threadPrefixes']);
		//XenForo_Template_Helper_Core::setThreadPrefixes($data['threadPrefixes']);

		if (!is_array($data['displayStyles']))
		{
			$data['displayStyles'] = XenForo_Model::create('XenForo_Model_UserGroup')->rebuildDisplayStyleCache();
		}
		//XenForo_Application::set('displayStyles', $data['displayStyles']);
		//XenForo_Template_Helper_Core::setDisplayStyles($data['displayStyles']);

		if (!is_array($data['trophyUserTitles']))
		{
			$data['trophyUserTitles'] = XenForo_Model::create('XenForo_Model_Trophy')->rebuildTrophyUserTitleCache();
		}
		//XenForo_Application::set('trophyUserTitles', $data['trophyUserTitles']);
		//XenForo_Template_Helper_Core::setUserTitles($data['trophyUserTitles']);

		if (!is_array($data['notices']))
		{
			$data['notices'] = XenForo_Model::create('XenForo_Model_Notice')->rebuildNoticeCache();
		}
		//XenForo_Application::set('notices', $data['notices']);

		if (!is_array($data['userFieldsInfo']))
		{
			$data['userFieldsInfo'] = XenForo_Model::create('XenForo_Model_UserField')->rebuildUserFieldCache();
		}
		//XenForo_Application::set('userFieldsInfo', $data['userFieldsInfo']);

		if (is_array($data['reportCounts']))
		{
			//XenForo_Application::set('reportCounts', $data['reportCounts']);
		}
		if (is_array($data['moderationCounts']))
		{
			//XenForo_Application::set('moderationCounts', $data['moderationCounts']);
		}
	}

}
