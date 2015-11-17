<?php
  
class Dark_TaigaChat_Dependencies extends XenForo_Dependencies_Public {
	
	/**
	 * Pre-loads globally required data for the system.
	 */
	public function preLoadData()
	{		
		
		// skip this stuff
		return;		
		
	}
	
	
	protected function _handleCustomPreloadedData(array &$data)
	{
		if (!is_array($data['routesPublic']))
		{
			$data['routesPublic'] = XenForo_Model::create('XenForo_Model_RoutePrefix')->rebuildRoutePrefixTypeCache('public');
		}

		if (!is_array($data['bannedIps']))
		{
			$data['bannedIps'] = XenForo_Model::create('XenForo_Model_Banning')->rebuildBannedIpCache();
		}

		if (!is_array($data['discouragedIps']))
		{
			$data['discouragedIps'] = XenForo_Model::create('XenForo_Model_Banning')->rebuildDiscouragedIpCache();
		}

		if (!is_array($data['styles']))
		{
			$data['styles'] = XenForo_Model::create('XenForo_Model_Style')->rebuildStyleCache();
		}

		if (!is_array($data['nodeTypes']))
		{
			$data['nodeTypes'] = XenForo_Model::create('XenForo_Model_Node')->rebuildNodeTypeCache();
		}

		if (!is_array($data['smilies']))
		{
			$data['smilies'] = XenForo_Model::create('XenForo_Model_Smilie')->rebuildSmilieCache();
		}
		
		if (!is_array($data['bbCode']))
		{
			$data['bbCode'] = XenForo_Model::create('XenForo_Model_BbCode')->rebuildBbCodeCache();
		}

		if (!is_array($data['threadPrefixes']))
		{
			$data['threadPrefixes'] = XenForo_Model::create('XenForo_Model_ThreadPrefix')->rebuildPrefixCache();
		}

		if (!is_array($data['displayStyles']))
		{
			$data['displayStyles'] = XenForo_Model::create('XenForo_Model_UserGroup')->rebuildDisplayStyleCache();
		}

		if (!is_array($data['trophyUserTitles']))
		{
			$data['trophyUserTitles'] = XenForo_Model::create('XenForo_Model_Trophy')->rebuildTrophyUserTitleCache();
		}

		if (!is_array($data['notices']))
		{
			$data['notices'] = XenForo_Model::create('XenForo_Model_Notice')->rebuildNoticeCache();
		}

		if (!is_array($data['userFieldsInfo']))
		{
			$data['userFieldsInfo'] = XenForo_Model::create('XenForo_Model_UserField')->rebuildUserFieldCache();
		}
	}

}
