<?php
class Dark_TaigaChat_Model_TaigaChat extends XenForo_Model
{	
	
	// maybe one day this will be completed
	public function getRooms($regen = false){       
		/** @var XenForo_Model_DataRegistry */
		$registryModel = $this->getModelFromCache('XenForo_Model_DataRegistry');		
		
		$rooms = array();
		if($regen)
			$rooms = $registryModel->get('dark_taigachat_rooms');
		
		if(empty($rooms) || $regen){       					
			
			$rooms = $this->fetchAllKeyed(
				"
					SELECT *
					FROM dark_taigachat_rooms
					ORDER BY display_order asc
				"
			, 'id');

			foreach($rooms as &$room){					
				if(!empty($room['group_whitelist']))
					$room['group_whitelist'] = unserialize($room['group_whitelist']);
				else
					$room['group_whitelist'] = array();			
			
				$room['title'] = new XenForo_Phrase($this->getRoomTitlePhraseName($room['id']));
			}
		
			$registryModel->set('dark_taigachat_rooms', $rooms);
		}
		return $rooms;
	}
	
	
	public function getDataPath(){
		
		$options = XenForo_Application::get('options');
		if(!$options->dark_taigachat_externaldata){
			return XenForo_Helper_File::getExternalDataPath();
		} else {
			return 'data';
		}
	}
		
	public function deletePublicHtml(){		
		@unlink($this->getDataPath().'/taigachat/messages.html');
		@unlink($this->getDataPath().'/taigachat/messagesmini.html');
	}	
	
		
	/**
	* @param mixed $overrideMotd set motd pre-cache-update
	* @param mixed $unsync if not due to new message set true
	*/
	public function regeneratePublicHtml($overrideMotd = false, $unsync = false){		
		
		$viewParams = array();
		$options = XenForo_Application::get('options');
		$visitor = XenForo_Visitor::getInstance();
		
		if($options->dark_taigachat_speedmode == 'Disabled')
			return;
			
		if($unsync){
			/** @var XenForo_Model_DataRegistry */
			$registryModel = $this->getModelFromCache('XenForo_Model_DataRegistry');		
			$lastUnsync = $registryModel->get('dark_taigachat_unsync');
			if(!empty($lastUnsync) && $lastUnsync > time() - 30){				
				return;
			}		
			$registryModel->set('dark_taigachat_unsync', time());
		}
			
		// swap timezone to default temporarily
		$oldTimeZone = XenForo_Locale::getDefaultTimeZone()->getName();
		XenForo_Locale::setDefaultTimeZone($options->guestTimeZone);
		
		$messages = $this->getMessages(1, array(
			"page" => 1, 
			"perPage" => $options->dark_taigachat_fullperpage,
			"lastRefresh" => 0,
		));
		$messagesMini = $this->getMessages(1, array(
			"page" => 1, 
			"perPage" => $options->dark_taigachat_sidebarperpage,
			"lastRefresh" => 0,
		));		
		$messageIds = $this->getMessageIds(1, array(
			"page" => 1, 
			"perPage" => $options->dark_taigachat_fullperpage,
			"lastRefresh" => 0,
		));
		
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base'));
		$motd = new XenForo_BbCode_TextWrapper($overrideMotd !== false ? $overrideMotd : $options->dark_taigachat_motd, $bbCodeParser);
				
		$onlineUsersTaiga = null;
		if($options->dark_taigachat_sidebar){	
			$onlineUsersTaiga = $this->getActivityUserList($visitor->toArray());
		}		
		
		$viewParams = array(
			'taigachat' => array(
				"messages" => $messages,
				"sidebar" => false,
				"messageIds" => $messageIds,
				"editside" => $options->dark_taigachat_editside,
				"timedisplay" => $options->dark_taigachat_timedisplay,
				"miniavatar" => $options->dark_taigachat_miniavatar,
				"lastrefresh" => 0,
				"numInChat" => $this->getActivityUserCount(),
				"motd" => $motd,
				"online" => $onlineUsersTaiga,
				"route" => $options->dark_taigachat_route,
				
				"publichtml" => true,
				'canView' => true,
				'enabled' => true,				
			),
		);		
				
		$dep = new Dark_TaigaChat_Dependencies();
		$dep->preLoadData();
		$dep->preloadTemplate('dark_taigachat_list');
		
		$viewRenderer = new Dark_TaigaChat_ViewRenderer_JsonInternal($dep, new Zend_Controller_Response_Http(), new Zend_Controller_Request_Http());
	
		if(!file_exists($this->getDataPath().'/taigachat'))
			XenForo_Helper_File::createDirectory($this->getDataPath().'/taigachat', true);			
			
		$innerContent = $viewRenderer->renderView('Dark_TaigaChat_ViewPublic_TaigaChat_List', $viewParams, 'dark_taigachat_list');
		$filename = $this->getDataPath().'/taigachat/messages.html';
		$yayForNoLocking = mt_rand(0, 10000000);
		if(file_put_contents($filename.".{$yayForNoLocking}.tmp", $innerContent, LOCK_EX) === false)
			throw new XenForo_Exception("Failed writing TaigaChat messages to {$filename}.{$yayForNoLocking}.tmp");
		if(!@rename($filename.".{$yayForNoLocking}.tmp", $filename))
			@unlink($filename.".{$yayForNoLocking}.tmp");
		XenForo_Helper_File::makeWritableByFtpUser($filename);
		
		
		$viewParams['taigachat']['messages'] = $messagesMini;
		$viewParams['taigachat']['sidebar'] = true;
		
		$innerContent = $viewRenderer->renderView('Dark_TaigaChat_ViewPublic_TaigaChat_List', $viewParams, 'dark_taigachat_list');
		$filename = $this->getDataPath().'/taigachat/messagesmini.html';
		if(file_put_contents($filename.".{$yayForNoLocking}.tmp", $innerContent, LOCK_EX) === false)
			throw new XenForo_Exception("Failed writing TaigaChat messages to {$filename}.{$yayForNoLocking}.tmp");
			
		// The only reason this could fail is if the file is being hammered, hence no worries ignoring failure
		if(!@rename($filename.".{$yayForNoLocking}.tmp", $filename))
			@unlink($filename.".{$yayForNoLocking}.tmp");
		XenForo_Helper_File::makeWritableByFtpUser($filename);		
		
		// put things back to how they was
		XenForo_Locale::setDefaultTimeZone($oldTimeZone);
	}
	
	public function getMessages($roomId = 1, array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchAll($this->limitQueryResults(
			"
				SELECT *, IF(user.username IS NULL, taigachat.username, user.username) AS username, IF(DATEDIFF(NOW(), FROM_UNIXTIME(date)) = 0, 1, 0) AS today
				FROM dark_taigachat AS taigachat
				LEFT JOIN xf_user AS user ON
					(user.user_id = taigachat.user_id)
				WHERE taigachat.last_update >= ? and taigachat.room_id = ?
				ORDER BY taigachat.id DESC
			", $limitOptions['limit'], $limitOptions['offset']
		), array($fetchOptions['lastRefresh'], $roomId));
	}
	
	public function getMessageIds($roomId = 1, array $fetchOptions = array())
	{
		$options = XenForo_Application::get('options');		
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$results = $this->_getDb()->fetchAll($this->limitQueryResults(
			"
				SELECT id
				FROM dark_taigachat AS taigachat
				WHERE taigachat.room_id = ?
				ORDER BY taigachat.id DESC
			", $limitOptions['limit'], $limitOptions['offset']
		), array($roomId));
		
		$ids = array();
		foreach($results as $result){
			$ids[]=$result['id'];
		}
		if($options->dark_taigachat_reverse){
			$ids = array_reverse($ids);
		}
		return $ids;
	}
	
	public function getMessagesToday()
	{
		return $this->_getDb()->fetchAll(
			"
				SELECT *, IF(user.username IS NULL, taigachat.username, user.username) AS username
				FROM dark_taigachat AS taigachat
				LEFT JOIN xf_user AS user ON
					(user.user_id = taigachat.user_id)
				WHERE taigachat.date > UNIX_TIMESTAMP()-60*60*24 and taigachat.room_id=1 and taigachat.activity=0 
				ORDER BY taigachat.date DESC                
			"
		);
	}
	
	public function getRoomDefinitionById($id){
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM dark_taigachat_rooms
			WHERE id = ?
		', $id);
	}
	
	public function getRoomTitlePhraseName($id)
	{
		return 'dark_taigachat_room_' . $id . '_title';
	}
	
	public function getMessageById($id, array $fetchOptions = array())
	{
		return $this->_getDb()->fetchRow('		
			SELECT *, IF(user.username IS NULL, taigachat.username, user.username) AS username, IF(DATEDIFF(NOW(), FROM_UNIXTIME(date)) = 0, 1, 0) AS today
			FROM dark_taigachat AS taigachat
			LEFT JOIN xf_user AS user ON
				(user.user_id = taigachat.user_id)
			WHERE taigachat.id = ?
		', $id);
	}
	
	public function deleteMessage($id){
		XenForo_Application::setSimpleCacheData('taigachat_lastUpdate', XenForo_Application::$time);
		return $this->_getDb()->query('		
			DELETE FROM dark_taigachat 
			WHERE id = ?
		', $id);
	}	
	
	public function deleteOldMessages(){
		$rooms = $this->_getDb()->fetchCol("select room_id from dark_taigachat");
		foreach($rooms as $room){
			$this->_getDb()->query("
				select @goat := date from dark_taigachat where room_id=? order by date desc limit 1000;
			", array($room));
			$this->_getDb()->query("
				delete from dark_taigachat where room_id=? and date < @goat		
			", array($room));
		}		
	}	
	
	public function deleteOldActivity(){		
		return $this->_getDb()->query("
			delete from dark_taigachat_activity where date < UNIX_TIMESTAMP()-30*60		
		");		
		XenForo_Application::setSimpleCacheData('taigachat_lastUpdate', XenForo_Application::$time);
	}
	
	
	public function getActivityUserList(array $viewingUser=array())
	{
		$records = $this->_getDb()->fetchAll(
			"
				SELECT *
				FROM dark_taigachat_activity AS activity
				LEFT JOIN xf_user AS user ON
					(user.user_id = activity.user_id)
				WHERE activity.date > UNIX_TIMESTAMP()-150
				ORDER BY activity.date DESC
			"
		);
		
		$output = array(
			'guests' => 0,
			'members' => 0,
		);
		
		foreach ($records AS $key => &$record)
		{
			if(!$record['visible']){
				unset($records[$key]);
				continue;
			}	
			$output['members']++;
		}
		
		$output['limit'] = 99999999;
		$output['total'] = $output['members'];
		$output['records'] = $records;
		
		return $output;
	}
	
	public function getActivityUserCount(){
		return $this->_getDb()->fetchOne(
			"
				SELECT count(*)
				FROM dark_taigachat_activity AS activity
				LEFT JOIN xf_user AS user ON
					(user.user_id = activity.user_id)
				WHERE activity.date > UNIX_TIMESTAMP()-150
					   AND user.visible=1
			"
		);
	}
	
	public function floodCheck($user = null){
		
		$visitor = XenForo_Visitor::getInstance();
		$options = XenForo_Application::get('options');
		
		if($options->dark_taigachat_flood_time == 0)
			return 0;
		
		$this->standardizeViewingUserReference($user);
		if(XenForo_Permission::hasPermission($user['permissions'], 'dark_taigachat', 'flood'))
			return 0;		
		
		$date = $this->_getDb()->fetchOne(
			"
				SELECT date
				FROM dark_taigachat
				WHERE user_id = ?
				order by id desc
				limit 1
			", array($visitor->getUserId())
		);
		
		if(empty($date))
			return 0;
			
		if(time() - $date < $options->dark_taigachat_flood_time){
			return $options->dark_taigachat_flood_time - (time() - $date);
		}
	
		return 0;
	}
	
	public function updateActivity($user_id, $updateHtml = true, $unsync = false){
		// triple check this only runs once per request, spaghetti everywhere thanks to xenporta etc.
		if(empty($GLOBALS['taigachat_updated_activity'])){
			$GLOBALS['taigachat_updated_activity'] = true;
			if($user_id > 0){
				$this->_getDb()->query("
					replace into dark_taigachat_activity
					set user_id = ?, date = UNIX_TIMESTAMP()
				", array($user_id));
			}
			XenForo_Application::setSimpleCacheData('taigachat_lastUpdate', XenForo_Application::$time);
			if($updateHtml)
				$this->regeneratePublicHtml(false, $unsync);
		}
	}
	
	public function updateActivityForLogOut($user_id){		
		$this->_getDb()->query("
			delete from dark_taigachat_activity
			where user_id = ?
		", array($user_id));
		
		XenForo_Application::setSimpleCacheData('taigachat_lastUpdate', XenForo_Application::$time);
		$this->regeneratePublicHtml(false, false);
	}	
	
	public function pruneShoutbox(){
		Dark_TaigaChat_CronEntry_CleanUp::runDailyCleanUp();
		$this->_getDb()->query("
			delete from dark_taigachat	
		");		
		
		$visitor = XenForo_Visitor::getInstance();
		$dw = XenForo_DataWriter::create('Dark_TaigaChat_DataWriter_Message');
		$dw->setOption(Dark_TaigaChat_DataWriter_Message::OPTION_IS_AUTOMATED, true);
		$dw->set('user_id', $visitor['user_id']);
		$dw->set('username', $visitor['user_id'] > 0 ? $visitor['username'] : new XenForo_Phrase('guest'));
		$dw->set('message', new XenForo_Phrase('dark_taigachat_pruned'));
		$dw->save();    
		
		XenForo_Application::setSimpleCacheData('taigachat_lastUpdate', XenForo_Application::$time);   
	}		
	
	public function canModifyMessage(array $message, array $user = null)
	{
		$this->standardizeViewingUserReference($user);

		if ($user['user_id'] == $message['user_id'])
		{
			return XenForo_Permission::hasPermission($user['permissions'], 'dark_taigachat', 'modify');
		}
		else
		{
			return XenForo_Permission::hasPermission($user['permissions'], 'dark_taigachat', 'modifyAll');
		}
	}
	
	public function canViewMessages(array $user = null)
	{
		$this->standardizeViewingUserReference($user);

		return XenForo_Permission::hasPermission($user['permissions'], 'dark_taigachat', 'view');		
	}
	
	public function canPostMessages(array $user = null)
	{
		$this->standardizeViewingUserReference($user);
		
		return XenForo_Permission::hasPermission($user['permissions'], 'dark_taigachat', 'post');		
	}
	
	public function canPruneShoutbox(array $user = null)
	{
		$this->standardizeViewingUserReference($user);
		
		return XenForo_Permission::hasPermission($user['permissions'], 'dark_taigachat', 'prune');		
	}
	
	public function canBanFromShoutbox(array $user = null)
	{
		$this->standardizeViewingUserReference($user);
		
		return XenForo_Permission::hasPermission($user['permissions'], 'dark_taigachat', 'ban');		
	}
	
	public function canUseColor(array $user = null)
	{
		$this->standardizeViewingUserReference($user);
		
		return $user['user_id'] > 0 && XenForo_Permission::hasPermission($user['permissions'], 'dark_taigachat', 'color');		
	}
	
	public function canEditMotd(array $user = null)
	{
		$this->standardizeViewingUserReference($user);
		
		return XenForo_Permission::hasPermission($user['permissions'], 'dark_taigachat', 'motd');		
	}
	
}