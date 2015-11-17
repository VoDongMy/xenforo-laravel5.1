<?php

class Dark_TaigaChat_DataWriter_DiscussionMessage_Post extends XFCP_Dark_TaigaChat_DataWriter_DiscussionMessage_Post
{
	protected function _messagePostSave(){
		
		parent::_messagePostSave();				
		
		if(!empty($this->_newData['xf_post']['thread_id']) && !empty($this->_newData['xf_post']['post_id'])){	
			
			$options = XenForo_Application::get('options');			
			if($options->dark_taigachat_activity != 'None'){
				
				$visitor = XenForo_Visitor::getInstance();
				$isThread = $this->_newData['xf_post']['position'] == 0;
				
				/** @var XenForo_Model_Thread */
				$threadModel = XenForo_Model::create("XenForo_Model_Thread");
				$thread = $threadModel->getThreadById($this->_newData['xf_post']['thread_id']);
			
				/** @var XenForo_Model_Node */
				$nodeModel = XenForo_Model::create("XenForo_Model_Node");
				$node = $nodeModel->getNodeById($thread['node_id']);
				
				$ok = false;
								
				// making the not-too-risky assumption that 1 will be guest group
				$permissionCombinationId = 1;
				if($options->dark_taigachat_activity_userid > 0){			
					/** @var XenForo_Model_User */
					$userModel = XenForo_Model::create("XenForo_Model_User");		
					$activityUser = $userModel->getUserById($options->dark_taigachat_activity_userid);
					$permissionCombinationId = $activityUser['permission_combination_id'];
				}				
				
				$nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination($permissionCombinationId);
				foreach($nodePermissions as $nodeId => $nodePermission){
					if($nodeId == $node['node_id'] && XenForo_Permission::hasContentPermission($nodePermission, 'view') && XenForo_Permission::hasContentPermission($nodePermission, 'viewOthers'))
						$ok = true;
				}
				
				if($isThread){
					$discussionDw = $this->getDiscussionDataWriter();
					if($discussionDw->get('discussion_state') != 'visible')
						$ok = false;
				} else {
					if($this->get('message_state') != 'visible')
						$ok = false;
				}
				
				if($ok){
				
					$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_BbCode_Strip', false);
					$formatter->stripAllBbCode(true);
					$parser = XenForo_BbCode_Parser::create($formatter);
					$title = $parser->render($thread['title']);
								
					if($isThread){
						$activityMessage = new XenForo_Phrase('dark_posted_new_thread_in_x_x', array(
							'forum' => "[url='".XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink("forums", $node), true)."']".$node['title']."[/url]",
							'thread' => "[url='".XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink("threads", $thread), true)."']".$title."[/url]",
						), false);		
					} else {
						$activityMessage = new XenForo_Phrase('dark_replied_to_x', array(
							'thread' => "[url='".XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink("posts", $this->_newData['xf_post']), true)."']".$title."[/url]",
						), false);						
					}			
					
					if($isThread || $options->dark_taigachat_activity == 'Both'){
						$dw = XenForo_DataWriter::create('Dark_TaigaChat_DataWriter_Message');
						$dw->setOption(Dark_TaigaChat_DataWriter_Message::OPTION_IS_AUTOMATED, true);
						$dw->set('user_id', $this->get('user_id'));
						$username = $this->get('username');
						if(empty($username))
							$username = new XenForo_Phrase('guest');
						$dw->set('username', $username);
						$dw->set('message', $activityMessage);
						$dw->set('activity', 1);
						$dw->save();	
					}
				}
			}
				
		}
		
	}
}