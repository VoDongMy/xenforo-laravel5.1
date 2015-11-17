<?php

class XenGallery_ModeratorLogHandler_Media extends XenForo_ModeratorLogHandler_Abstract
{
	protected function _log(array $logUser, array $content, $action, array $actionParams = array(), $parentContent = null)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ModeratorLog');
		$dw->bulkSet(array(
			'user_id' => $logUser['user_id'],
			'content_type' => 'xengallery_media',
			'content_id' => $content['media_id'],
			'content_user_id' => $content['user_id'],
			'content_username' => $content['username'],
			'content_title' => utf8_substr($content['media_title'], 0, 150),
			'content_url' => XenForo_Link::buildPublicLink('xengallery', $content),
			'discussion_content_type' => 'xengallery_media',
			'discussion_content_id' => $content['media_id'],
			'action' => $action,
			'action_params' => $actionParams
		));
		$dw->save();

		return $dw->get('moderator_log_id');
	}

	protected function _prepareEntry(array $entry)
	{
		$elements = json_decode($entry['action_params'], true);

		if ($entry['action'] == 'edit')
		{
			$entry['actionText'] = new XenForo_Phrase(
				'moderator_log_xengallery_media_edit',
				array('elements' => implode(', ', array_keys($elements)))
			);
		}

		return $entry;
	}
}