<?php
  
class Dark_TaigaChat_ControllerPublic_TaigaChat extends XenForo_ControllerPublic_Abstract
{
	public function actionList(){		
		$viewParams = array();
		$taigamodel = $this->_getTaigaChatModel();
		$options = XenForo_Application::get('options');
		$visitor = XenForo_Visitor::getInstance();
		
		$sidebar = false;		
		
		if(!$taigamodel->canViewMessages()){
			//throw $this->getErrorOrNoPermissionResponseException('dark_no_permission_view_message');
			return $this->responseView('Dark_TaigaChat_ViewPublic_TaigaChat_List_Blank', 'dark_taigachat_blank'); 
		}
		
		if($this->_input->inRequest('sidebar') && $this->_input->filterSingle('sidebar', XenForo_Input::UINT))
			$sidebar = true;

		$query = array(
			"page" => 1, 
			"perPage" => $sidebar ? $options->dark_taigachat_sidebarperpage : $options->dark_taigachat_fullperpage,
			"lastRefresh" => $this->_input->filterSingle('lastrefresh', XenForo_Input::UINT)
		);
		$messages = $taigamodel->getMessages($this->_input->filterSingle('room', XenForo_Input::UINT), $query);
		$messageIds = $taigamodel->getMessageIds($this->_input->filterSingle('room', XenForo_Input::UINT), $query);
				
		foreach($messages as &$message){
			if($taigamodel->canModifyMessage($message)){
				$message['canModify'] = true;
			}
		}
		
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base'));
		$motd = new XenForo_BbCode_TextWrapper($options->dark_taigachat_motd, $bbCodeParser);
		
		if(!$this->_input->filterSingle('fake', XenForo_Input::UINT))
			$taigamodel->updateActivity($visitor['user_id']);
		
		$onlineUsersTaiga = array();		
		if($options->dark_taigachat_sidebar){	
			$onlineUsersTaiga = $taigamodel->getActivityUserList($visitor->toArray());		
		}
		
		$viewParams = array('taigachat' => array(
			"messages" => $messages,
			"sidebar" => $sidebar,
			"messageIds" => $messageIds,
			"editside" => $options->dark_taigachat_editside,
			"timedisplay" => $options->dark_taigachat_timedisplay,
			"miniavatar" => $options->dark_taigachat_miniavatar,
			"lastrefresh" => $this->_input->filterSingle('lastrefresh', XenForo_Input::UINT),
			"numInChat" => $taigamodel->getActivityUserCount(),
			"motd" => $motd,
			'online' => $onlineUsersTaiga,
			"route" => $options->dark_taigachat_route,
			"canBan" => $taigamodel->canBanFromShoutbox(),
		));
		
		
		return $this->responseView('Dark_TaigaChat_ViewPublic_TaigaChat_List', 'dark_taigachat_list', $viewParams); 
	}
	
	public function actionActivity(){		
		$viewParams = array();
		$taigamodel = $this->_getTaigaChatModel();
		$visitor = XenForo_Visitor::getInstance();
		
		$taigamodel->updateActivity($visitor['user_id']);
		
		return $this->responseView('Dark_TaigaChat_ViewPublic_TaigaChat_Activity', '', array()); 
	}
	
	public function actionIndex(){
		
		$visitor = XenForo_Visitor::getInstance();
		$sessionModel = $this->getModelFromCache('Dark_TaigaChat_Model_Session');
		$taigamodel = $this->_getTaigaChatModel();
		$options = XenForo_Application::get('options');
		
		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink($options->dark_taigachat_route)
		);

		$onlineUsers = $sessionModel->getSessionActivityQuickList(
			$visitor->toArray(),
			array('cutOff' => array('>', $sessionModel->getOnlineStatusTimeout())),
			($visitor['user_id'] ? $visitor->toArray() : null)
		);			
		$onlineUsersTaiga = array();		
			
		if($options->dark_taigachat_sidebar){	
			$onlineUsersTaiga = $taigamodel->getActivityUserList($visitor->toArray());		
		}
		
		$viewParams = array('taigachat' => array(
			'onlineUsers' => $onlineUsers,
			'sidebar_enabled' => true,
			'online' => $onlineUsersTaiga,
		));

		$taigamodel->updateActivity($visitor['user_id'], false);
			
		$response = new stdClass();
		$response->viewName = "Dark_TaigaChat_ViewPublic_TaigaChat_Index";
		$response->params = array();
		Dark_TaigaChat_Helper_Global::getTaigaChatStuff($response, "index");
		$viewParams['taigachat'] = array_merge_recursive($viewParams['taigachat'], $response->params['taigachat']);		
			
		return $this->responseView('Dark_TaigaChat_ViewPublic_TaigaChat_Index', 'dark_taigachat_full', $viewParams); 
	}
	
	public function actionPopup(){
				
		$visitor = XenForo_Visitor::getInstance();
		$options = XenForo_Application::get('options');
		$taigamodel = $this->_getTaigaChatModel();
		
		if(!$options->dark_taigachat_popupenabled)
			throw $this->getErrorOrNoPermissionResponseException('');
			
		$taigamodel->updateActivity($visitor['user_id']);
		
		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink($options->dark_taigachat_route.'/popup')
		);
		
		$viewParams = array(
			'request' => $this->_request  // HAAAAAAAAAAAAAAAAX
		);		
		
		$response = new stdClass();
		$response->viewName = "";
		$response->params = array();
		Dark_TaigaChat_Helper_Global::getTaigaChatStuff($response, "popup");
		$viewParams['taigachat'] = $response->params['taigachat'];		

		return $this->responseView('Dark_TaigaChat_ViewPublic_TaigaChat_Popup', 'dark_taigachat_popup', $viewParams); 
	}
	
	public function actionPost(){
		$this->_assertPostOnly();
		$viewParams = array();		
		$visitor = XenForo_Visitor::getInstance();
		$taigamodel = $this->_getTaigaChatModel();
		$sessionmodel = $this->getModelFromCache('Dark_TaigaChat_Model_Session');
		/** @var XenForo_Model_User */
		$usermodel = $this->getModelFromCache('XenForo_Model_User');
		$options = XenForo_Application::get('options');
		
		if(!$taigamodel->canPostMessages()){
			throw $this->getErrorOrNoPermissionResponseException('dark_no_permission_post_message');
		}
				
		$input = $this->_input->filter(array(
			'message' => XenForo_Input::STRING,
			'color' => XenForo_Input::STRING,
			'sidebar' => XenForo_Input::BINARY,
			'room' => XenForo_Input::UINT,
		));
		
		// Keep users 'online' if they are posting in the shoutbox
		$user_id = $visitor->getUserId();
		if($user_id >= 1){
			$usermodel->updateSessionActivity($user_id, $_SERVER['REMOTE_ADDR'], 'Dark_TaigaChat_ControllerPublic_TaigaChat', 'post', 'valid', array());
			$taigamodel->updateActivity($user_id, false);
		}
		
		if(trim($input['message'] == '/prune')){
			if($taigamodel->canPruneShoutbox()){
				$taigamodel->pruneShoutbox();
			}
			
			
		} elseif(preg_match('#^\s*/unban (.*?)\s*$#', $input['message'], $matches)){			
			
			if(!$taigamodel->canBanFromShoutbox()){
				throw $this->getErrorOrNoPermissionResponseException('');
			}
		
			$user = $usermodel->getUserByNameOrEmail($matches[1]);
						
			// prevent breaking guest permissions
			if(!$user){
				throw $this->getErrorOrNoPermissionResponseException('dark_user_not_found');			
			}
			
			// prevent unbanning self
			if($user['user_id'] == $visitor['user_id']){
				throw $this->getErrorOrNoPermissionResponseException('');			
			}
			
			if($options->dark_taigachat_ban_group > 0){
				
				// unban via secondary group
				 
				/** @var XenForo_DataWriter_User */
				$userWriter = XenForo_DataWriter::create('XenForo_DataWriter_User'); 
				$userWriter->setExistingData($user['user_id']);
				
				$secondaryGroups = explode(',', $userWriter->get('secondary_group_ids'));	
				if(($k = array_search($options->dark_taigachat_ban_group, $secondaryGroups)) !== false) {
					unset($secondaryGroups[$k]);
				}
			
				$userWriter->setSecondaryGroups($secondaryGroups);
				$userWriter->save();			
				
			} else {
				
				// unban via user permissions (individual)
				
				/** @var XenForo_Model_Permission */
				$permissionModel = $this->getModelFromCache('XenForo_Model_Permission');
				$permissionModel->updateGlobalPermissionsForUserCollection(
					array(
						'dark_taigachat' => array(
							'view' => 'unset',
							'post' => 'unset',
							'color' => 'unset',
							'modify' => 'unset',
							'modifyAll' => 'unset',
							'prune' => 'unset',
							'ban' => 'unset',
							'motd' => 'unset',
						)
					)
				, 0, $user['user_id']);
				
			}			
			
			
		} else {
			
			
			if(($floodSeconds = $taigamodel->floodCheck()) > 0){
				return $this->responseView('Dark_TaigaChat_ViewPublic_TaigaChat_Post', '', array(
					'too_fast' => true,
					'phrase' => (string)(new XenForo_Phrase('dark_posting_too_fast_wait_x_seconds', array('seconds' => $floodSeconds))),					
				)); 
			}			 
			
			if(!empty($input['color']) && $taigamodel->canUseColor()){
				if(substr($input['message'], 0, 4) == '/me '){
					$messageText = substr($input['message'], 3);
					if(!empty($messageText))
						$input['message'] = "/me [color=#{$input['color']}]{$messageText}[/color]";					
				} else {					
					$input['message'] = "[color=#{$input['color']}]".$input['message']."[/color]";
				}
			}

			$dw = XenForo_DataWriter::create('Dark_TaigaChat_DataWriter_Message');
			$dw->set('user_id', $visitor['user_id']);
			$dw->set('username', $visitor['user_id'] > 0 ? $visitor['username'] : new XenForo_Phrase('guest'));
			$dw->set('room_id', $input['room']);
			$dw->set('message', $input['message']);
			$dw->save();			
		}
		
		// if fast mode just redirect to newly updated file
		if($options->dark_taigachat_speedmode != 'Disabled' && $input['room'] == 1){
			header("Location: ".XenForo_Link::convertUriToAbsoluteUri("data/taigachat/messages".($input['sidebar'] ? 'mini' : '').".html?".time(), true));
			exit;
		} else {
			return $this->responseReroute("Dark_TaigaChat_ControllerPublic_TaigaChat", "list");
		}
		
	}
	
	public function actionEdit(){
		$taigamodel = $this->_getTaigaChatModel();
		$visitor = XenForo_Visitor::getInstance();
		$id = $this->_input->filterSingle('id', XenForo_Input::UINT);
		$options = XenForo_Application::get('options');
				
		$message = $taigamodel->getMessageById($id);
		
		if(!$message)
			throw $this->getErrorOrNoPermissionResponseException('dark_invalid_message');
			
		if(!$taigamodel->canModifyMessage($message)){
			throw $this->getErrorOrNoPermissionResponseException('dark_no_permission_modify_message');
		}
		
		if($this->_input->inRequest("message")){
			$input = $this->_input->filter(array(
				'message' => XenForo_Input::STRING,
				'isJavascript' => XenForo_Input::BINARY,
			));

			$dw = XenForo_DataWriter::create('Dark_TaigaChat_DataWriter_Message');
			$dw->setExistingData($id);
			$dw->set('message', $input['message']);
			$dw->save();
			
			if(empty($input['isJavascript'])){		
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$this->getDynamicRedirect($options->dark_taigachat_route) // attempt to stay on index
				);	
					
			} else { 
				
				$message = $taigamodel->getMessageById($id);
				$messages = array();
				$messages[] = $message;
				
				$viewParams = array('taigachat' => array(
					"messages" => $messages,
					"editside" => $options->dark_taigachat_editside,
					"timedisplay" => $options->dark_taigachat_timedisplay,
					"miniavatar" => $options->dark_taigachat_miniavatar,
					"lastrefresh" => 0,
					"editid" => $id,
				));
						
				return $this->responseView('Dark_TaigaChat_ViewPublic_TaigaChat_Edit', 'dark_taigachat_single', $viewParams); 
				
			}
			//return $this->responseView('Dark_TaigaChat_ViewPublic_TaigaChat_Edit', ''); 
			
		} else {			
		
			$viewParams = array('taigachat' => array(
				"message" => $taigamodel->getMessageById($id),
				"route" => $options->dark_taigachat_route
			));
					
			return $this->responseView('Dark_TaigaChat_ViewPublic_TaigaChat_EditForm', 'dark_taigachat_edit', $viewParams); 
		}
	}
	
	public function actionMotd(){
		$taigamodel = $this->_getTaigaChatModel();
		$visitor = XenForo_Visitor::getInstance();
		$id = $this->_input->filterSingle('id', XenForo_Input::UINT);
		$options = XenForo_Application::get('options');
				
		if(!$taigamodel->canEditMotd()){
			throw $this->getErrorOrNoPermissionResponseException('dark_no_permission_modify_message');
		}
		
		if($this->_input->inRequest("message")){
			$input = $this->_input->filter(array(
				'message' => XenForo_Input::STRING
			));			
			
			$optionModel = $this->getModelFromCache('XenForo_Model_Option');
			$optionModel->updateOptions(array('dark_taigachat_motd' => $input['message']));
			
			$options->dark_taigachat_motd = $input['message'];			
			
			$taigamodel->regeneratePublicHtml($input['message']);
		
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect($options->dark_taigachat_route) // attempt to stay on index
			);		
						
		} else {			
		
			$viewParams = array('taigachat' => array(
				"message" => $options->dark_taigachat_motd,
				"route" => $options->dark_taigachat_route
			));
					
			return $this->responseView('Dark_TaigaChat_ViewPublic_TaigaChat_Motd', 'dark_taigachat_motd', $viewParams); 
		}
	}
	
	public function actionDelete(){
		$taigamodel = $this->_getTaigaChatModel();
		$visitor = XenForo_Visitor::getInstance();
		$id = $this->_input->filterSingle('id', XenForo_Input::UINT);
		
		$message = $taigamodel->getMessageById($id);
		
		if(!$message)
			throw $this->getErrorOrNoPermissionResponseException('dark_invalid_message');
			
		if(!$taigamodel->canModifyMessage($message)){
			throw $this->getErrorOrNoPermissionResponseException('dark_no_permission_modify_message');
		}
				
		$taigamodel->deleteMessage($id);
		$taigamodel->regeneratePublicHtml();
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS, 
			$this->getDynamicRedirect('taigachat') // attempt to stay on index
		);
		
		//return $this->responseView('Dark_TaigaChat_ViewPublic_TaigaChat_Delete', ''); 
	}
	
	
	public function actionColorPicker(){
		$taigamodel = $this->_getTaigaChatModel();
		$visitor = XenForo_Visitor::getInstance();
		
		if(!$taigamodel->canUseColor()){
			throw $this->getErrorOrNoPermissionResponseException('');
		}
		
		$viewParams = array(
			'js_modification' => filemtime("js/dark/taigachat_color_picker.js"),
			'current_color' => $visitor->get('taigachat_color'),
		);
		
		return $this->responseView('Dark_TaigaChat_ViewPublic_TaigaChat_ColorPicker', 'dark_taigachat_color_picker', $viewParams); 
	}
	
	
	public function actionSaveColor(){
		$this->_assertPostOnly();
		$taigamodel = $this->_getTaigaChatModel();
		$visitor = XenForo_Visitor::getInstance();
		
		if(!$taigamodel->canUseColor()){
			throw $this->getErrorOrNoPermissionResponseException('');
		}
		$input = $this->_input->filter(array(
			'color' => XenForo_Input::STRING
		));
	
		$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$userDw->setOption(XenForo_DataWriter_User::OPTION_LOG_CHANGES, false);
		$userDw->setExistingData($visitor['user_id']);
		$userDw->set('taigachat_color', $input['color']);
		$userDw->save();		
		
		$viewParams = array(
			'color' => $userDw->get('taigachat_color'),
			'saved' => new XenForo_Phrase('dark_saved_color'),
		);
		
		return $this->responseView('Dark_TaigaChat_ViewPublic_TaigaChat_SaveColor', '', $viewParams); 
	}
	
	
	public function actionBan(){
		$taigamodel = $this->_getTaigaChatModel();
		$options = XenForo_Application::get('options');
		$visitor = XenForo_Visitor::getInstance();
		$id = $this->_input->filterSingle('id', XenForo_Input::UINT);
		
		$message = $taigamodel->getMessageById($id);
		
		if(!$message)
			throw $this->getErrorOrNoPermissionResponseException('dark_invalid_message');
			
		if(!$taigamodel->canBanFromShoutbox()){
			throw $this->getErrorOrNoPermissionResponseException('');
		}
		
		// don't let guests be banned, this screws up the permissions table!!
		if(!$message['user_id']){
			throw $this->getErrorOrNoPermissionResponseException('');			
		}
		
		// probably shouldn't let people ban themselves either...
		if($message['user_id'] == $visitor['user_id']){
			throw $this->getErrorOrNoPermissionResponseException('');			
		}
		
		// prevent banning super admins
		/** @var XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');
		if($userModel->isUserSuperAdmin($userModel->getUserById($message['user_id']))){
			throw $this->getErrorOrNoPermissionResponseException('');			
		}
				
		if($options->dark_taigachat_ban_group > 0){			
			// ban via secondary group
						 
			/** @var XenForo_DataWriter_User */
			$userWriter = XenForo_DataWriter::create('XenForo_DataWriter_User'); 
			$userWriter->setExistingData($message['user_id']);
			
			$secondaryGroups = explode(',', $userWriter->get('secondary_group_ids'));			
			if(!in_array($options->dark_taigachat_ban_group, $secondaryGroups)){
				$secondaryGroups[] = $options->dark_taigachat_ban_group;
			}
			
			$userWriter->setSecondaryGroups($secondaryGroups);
			$userWriter->save();			
			
		} else {
			
			// ban via user permissions (individual)
			
			/** @var XenForo_Model_Permission */
			$permissionModel = $this->getModelFromCache('XenForo_Model_Permission');
			$permissionModel->updateGlobalPermissionsForUserCollection(
				array(
					'dark_taigachat' => array(
						'view' => 'deny',
						'post' => 'deny',
						'color' => 'deny',
						'modify' => 'deny',
						'modifyAll' => 'deny',
						'prune' => 'deny',
						'ban' => 'deny',
						'motd' => 'deny',
					)
				)
			, 0, $message['user_id']);
			
		}
		
		$taigamodel->regeneratePublicHtml();
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS, 
			$this->getDynamicRedirect('taigachat') // attempt to stay on index
		);
	}
	
	/**
	* @return Dark_TaigaChat_Model_TaigaChat
	*/
	protected function _getTaigaChatModel(){
		return $this->getModelFromCache('Dark_TaigaChat_Model_TaigaChat');
	}
	
	static public function getSessionActivityDetailsForList(array $activities){
		return new XenForo_Phrase('dark_viewing_shoutbox');
	}
}
