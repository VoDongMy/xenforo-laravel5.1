<?php
  
class Dark_TaigaChat_CronEntry_CleanUp {
	
	public static function runDailyCleanUp(){
		
		$options = XenForo_Application::get('options');
		$model = XenForo_Model::create('Dark_TaigaChat_Model_TaigaChat');

		if($options->dark_taigachat_archivethread > 0){			
			
			// swap timezone to default temporarily
			$timeZone = XenForo_Locale::getDefaultTimeZone();
			if(!empty($timeZone))
				$oldTimeZone = XenForo_Locale::getDefaultTimeZone()->getName();
			XenForo_Locale::setDefaultTimeZone($options->guestTimeZone);
			
			$messages = array_reverse($model->getMessagesToday());
			
			if(count($messages) > 0){	
				
				for($i = 0; $i < count($messages); $i += $options->dark_taigachat_archive_split){
					
					$userModel = XenForo_Model::create('XenForo_Model_User');			
					$post = "";
					for($j = $i; $j < min(count($messages), $i + $options->dark_taigachat_archive_split); $j ++){
					//foreach($messages as $message){
						$message = $messages[$j];
						$message['message'] = XenForo_Helper_String::autoLinkBbCode($message['message']);
						$date = XenForo_Locale::dateTime($message['date'], 'absolute');
						if($message['user_id'] > 0){             
							$user = "[user={$message['user_id']}]{$message['username']}[/user]";
						} else {
							$user = "[b]{$message['username']}[/b]";					
						}
						
						$me = substr($message['message'], 0, 3) == '/me';
						if($me){
							$message['message'] = substr($message['message'], 3);
							$post .= "{$date} - [i]{$user} {$message['message']}[/i]\r\n";						
						} else {
							$post .= "{$date} - {$user}: {$message['message']}\r\n";
						}
					}
					if(empty($post))
						continue;
					
					$username = "TaigaChat";
					if($options->dark_taigachat_archiveuser > 0){
						$user = $userModel->getUserById($options->dark_taigachat_archiveuser);
						$username = $user['username'];
					}
					
					$writer = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
					$writer->setOption(XenForo_DataWriter_DiscussionMessage::OPTION_IS_AUTOMATED, true);
					if(defined('XenForo_DataWriter_DiscussionMessage::OPTION_CHECK_SPAM'))
						$writer->setOption(XenForo_DataWriter_DiscussionMessage::OPTION_CHECK_SPAM, false);
					$writer->setOption(XenForo_DataWriter_DiscussionMessage::OPTION_MAX_MESSAGE_LENGTH, 0);
					$writer->setOption(XenForo_DataWriter_DiscussionMessage::OPTION_MAX_IMAGES, 0);
					$writer->setOption(XenForo_DataWriter_DiscussionMessage::OPTION_MAX_MEDIA, 0);
					$writer->setOption(XenForo_DataWriter_DiscussionMessage::OPTION_VERIFY_GUEST_USERNAME, false);
					$writer->set('user_id', $options->dark_taigachat_archiveuser);
					$writer->set('username', $username);
					$writer->set('message', $post);
					$writer->set('thread_id', $options->dark_taigachat_archivethread);
					$writer->save();
					
				}
			}
				
			// put timezone back to how it was
			if(!empty($oldTimeZone))
				XenForo_Locale::setDefaultTimeZone($oldTimeZone);	
		}
		
		$model->deleteOldMessages();
	}
	
	public static function rebuildPublicHtml(){
		$taigamodel = XenForo_Model::create('Dark_TaigaChat_Model_TaigaChat');		
		$taigamodel->regeneratePublicHtml();		
		$taigamodel->deleteOldActivity();
	}
	
}

