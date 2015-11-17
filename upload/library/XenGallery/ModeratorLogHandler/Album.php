<?php

class XenGallery_ModeratorLogHandler_Album extends XenForo_ModeratorLogHandler_Abstract
{
	protected function _log(array $logUser, array $content, $action, array $actionParams = array(), $parentContent = null)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ModeratorLog');
		$dw->bulkSet(array(
			'user_id' => $logUser['user_id'],
			'content_type' => 'xengallery_album',
			'content_id' => $content['album_id'],
			'content_user_id' => $content['album_user_id'],
			'content_username' => $content['album_username'],
			'content_title' => utf8_substr($content['album_title'], 0, 150),
			'content_url' => XenForo_Link::buildPublicLink('xengallery/albums', $content),
			'discussion_content_type' => 'xengallery_album',
			'discussion_content_id' => $content['album_id'],
			'action' => $action,
			'action_params' => $actionParams
		));
		$dw->save();

		return $dw->get('moderator_log_id');
	}

	protected function _prepareEntry(array $entry)
	{
		$elements = json_decode($entry['action_params'], true);

		switch ($entry['action'])
		{
			case 'edit':

				$entry['actionText'] = new XenForo_Phrase(
					'moderator_log_xengallery_album_edit',
					array('elements' => implode(', ', array_keys($elements)))
				);
				break;

			case 'permission':

				if ($elements['access_type'] == 'shared')
				{
					$usernames = array();

					if (!empty($elements['share_users']))
					{
						$users = XenForo_Model::create('XenForo_Model_User')->getUsersByIds(array_keys($elements['share_users']));
						foreach ($users AS $user)
						{
							$usernames[] = $user['username'];
						}
					}

					$entry['actionText'] = new XenForo_Phrase(
						'moderator_log_xengallery_album_permission_shared_' . $elements['permission'],
						array('users' => implode(', ', $usernames))
					);
				}
				else
				{
					$entry['actionText'] = new XenForo_Phrase(
						'moderator_log_xengallery_album_permission'
							. '_' . $elements['access_type']
							. '_' . $elements['permission']
					);
				}

				break;
		}

		return $entry;
	}
}